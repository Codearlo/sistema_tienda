<?php
/**
 * API Principal - Treinta App
 * Archivo: api/index.php
 */

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración
require_once '../config/config.php';

// Clase principal de la API
class APIHandler {
    private $db;
    private $method;
    private $endpoint;
    private $params;
    
    public function __construct() {
        $this->db = getDB();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->parseRequest();
    }
    
    private function parseRequest() {
        $request = $_SERVER['REQUEST_URI'];
        $path = parse_url($request, PHP_URL_PATH);
        // Ajuste para manejar diferentes rutas base en desarrollo
        $path = preg_replace('/^.*?\/api\//', '', $path);
        $path = trim($path, '/');
        
        $parts = explode('/', $path);
        $this->endpoint = $parts[0] ?: 'index';
        $this->params = array_slice($parts, 1);
    }
    
    public function handleRequest() {
        try {
            // Verificar autenticación para endpoints protegidos
            if ($this->endpoint !== 'auth' && $this->endpoint !== 'test') {
                $this->checkAuth();
            }
            
            // Redirigir al método apropiado
            switch ($this->endpoint) {
                case 'index':
                case 'test':
                    return $this->test();
                case 'auth':
                    return $this->handleAuth();
                case 'dashboard':
                    return $this->handleDashboard();
                case 'products':
                    return $this->handleProducts();
                case 'sales':
                    return $this->handleSales();
                case 'customers':
                    return $this->handleCustomers();
                case 'expenses':
                    return $this->handleExpenses();
                case 'debts':
                    return $this->handleDebts();
                case 'reports':
                    return $this->handleReports();
                default:
                    return $this->error('Endpoint no encontrado', 404);
            }
        } catch (Exception $e) {
            $code = is_int($e->getCode()) && $e->getCode() > 0 ? $e->getCode() : 500;
            error_log("API Error: " . $e->getMessage());
            return $this->error('Error interno del servidor', $code);
        }
    }
    
    // ===== MÉTODOS DE UTILIDAD =====
    
    private function checkAuth() {
        if (!isLoggedIn()) {
            throw new Exception('No autorizado', 401);
        }
    }
    
    private function getInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }
    
    private function success($data = null, $message = 'Operación exitosa') {
        return $this->response([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    private function error($message = 'Error', $code = 400) {
        http_response_code($code);
        return $this->response([
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ]);
    }
    
    private function response($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ===== ENDPOINTS =====
    
    private function test() {
        return $this->success([
            'app' => APP_NAME,
            'version' => APP_VERSION,
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'params' => $this->params
        ], 'API funcionando correctamente');
    }
    
    private function handleAuth() {
        switch ($this->method) {
            case 'POST':
                return $this->login();
            case 'DELETE':
                return $this->logout();
            default:
                return $this->error('Método no permitido', 405);
        }
    }
    
    private function login() {
        $input = $this->getInput();
        $email = cleanInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return $this->error('Email y contraseña son requeridos');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Email no válido');
        }
        
        // Buscar usuario
        $user = $this->db->single(
            "SELECT u.*, b.business_name, b.status as business_status 
             FROM users u 
             LEFT JOIN businesses b ON u.business_id = b.id 
             WHERE u.email = ? AND u.status = ?",
            [$email, STATUS_ACTIVE]
        );
        
        if (!$user) {
            return $this->error('Usuario no encontrado o inactivo');
        }
        
        // Verificar bloqueo
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return $this->error('Cuenta temporalmente bloqueada. Intente más tarde.');
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            $attempts = $user['login_attempts'] + 1;
            $updateData = ['login_attempts' => $attempts];
            
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $updateData['locked_until'] = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            }
            
            $this->db->update('users', $updateData, 'id = ?', [$user['id']]);
            
            return $this->error('Credenciales incorrectas');
        }
        
        // Login exitoso
        $this->db->update('users', 
            ['login_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );
        
        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['business_id'] = $user['business_id'];
        $_SESSION['business_name'] = $user['business_name'];
        $_SESSION['logged_in_at'] = time();
        
        return $this->success([
            'user' => [
                'id' => $user['id'],
                'name' => $_SESSION['user_name'],
                'email' => $user['email'],
                'type' => $user['user_type'],
                'business_id' => $user['business_id'],
                'business_name' => $user['business_name']
            ]
        ], 'Login exitoso');
    }
    
    private function logout() {
        session_destroy();
        return $this->success(null, 'Logout exitoso');
    }
    
    private function handleDashboard() {
        if ($this->method !== 'GET') {
            return $this->error('Método no permitido', 405);
        }
        
        $businessId = $_SESSION['business_id'];
        $today = date('Y-m-d');
        
        $stats = [
            'sales_today' => $this->db->single(
                "SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count 
                 FROM sales 
                 WHERE business_id = ? AND DATE(sale_date) = ? AND status = 1",
                [$businessId, $today]
            ),
            'products_sold_today' => $this->db->single(
                "SELECT COALESCE(SUM(si.quantity), 0) as total 
                 FROM sale_items si 
                 JOIN sales s ON si.sale_id = s.id 
                 WHERE s.business_id = ? AND DATE(s.sale_date) = ? AND s.status = 1",
                [$businessId, $today]
            ),
            'low_stock_products' => $this->db->fetchAll(
                "SELECT id, name, stock_quantity, min_stock, unit 
                 FROM products 
                 WHERE business_id = ? AND track_stock = 1 AND stock_quantity <= min_stock AND status = 1 
                 ORDER BY stock_quantity ASC 
                 LIMIT 5",
                [$businessId]
            ),
            'pending_debts' => $this->db->single(
                "SELECT COALESCE(SUM(remaining_amount), 0) as total, COUNT(*) as count 
                 FROM debts 
                 WHERE business_id = ? AND type = 'receivable' AND status IN ('pending', 'partial', 'overdue')",
                [$businessId]
            )
        ];
        
        $recent_sales = $this->db->fetchAll(
            "SELECT s.id, s.sale_date, s.total_amount, s.payment_status, 
                    COALESCE(c.first_name, 'Cliente') as customer_name,
                    (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             WHERE s.business_id = ? AND s.status = 1 
             ORDER BY s.sale_date DESC 
             LIMIT 5",
            [$businessId]
        );
        
        $upcoming_debts = $this->db->fetchAll(
            "SELECT d.id, d.remaining_amount, d.due_date, d.status,
                    COALESCE(c.first_name, 'N/A') as customer_name
             FROM debts d 
             LEFT JOIN customers c ON d.customer_id = c.id 
             WHERE d.business_id = ? AND d.type = 'receivable' 
             AND d.status IN ('pending', 'partial', 'overdue') 
             ORDER BY d.due_date ASC 
             LIMIT 5",
            [$businessId]
        );
        
        return $this->success([
            'stats' => $stats,
            'recent_sales' => $recent_sales,
            'upcoming_debts' => $upcoming_debts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function handleProducts() {
        $businessId = $_SESSION['business_id'];
        
        switch ($this->method) {
            case 'GET':
                return $this->getProducts($businessId);
            case 'POST':
                return $this->createProduct($businessId);
            case 'PUT':
                return $this->updateProduct($businessId);
            case 'DELETE':
                return $this->deleteProduct($businessId);
            default:
                return $this->error('Método no permitido', 405);
        }
    }
    
    private function getProducts($businessId) {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? RECORDS_PER_PAGE);
        $search = cleanInput($_GET['search'] ?? '');
        $category = intval($_GET['category'] ?? 0);
        
        $offset = ($page - 1) * $limit;
        
        $where = "p.business_id = ? AND p.status = 1";
        $params = [$businessId];
        
        if ($search) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($category) {
            $where .= " AND p.category_id = ?";
            $params[] = $category;
        }
        
        $products = $this->db->fetchAll(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE $where 
             ORDER BY p.name ASC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        $total = $this->db->single("SELECT COUNT(*) as total FROM products p WHERE $where", $params)['total'];
        
        return $this->success([
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'records_per_page' => $limit
            ]
        ]);
    }

    private function createProduct($businessId) {
        $input = $this->getInput();
        $required = ['name', 'selling_price'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                return $this->error("El campo '$field' es requerido");
            }
        }
        
        $data = [
            'business_id' => $businessId,
            'category_id' => !empty($input['category_id']) ? intval($input['category_id']) : null,
            'sku' => !empty($input['sku']) ? cleanInput($input['sku']) : null,
            'name' => cleanInput($input['name']),
            'selling_price' => floatval($input['selling_price']),
            'cost_price' => floatval($input['cost_price'] ?? 0),
            'stock_quantity' => intval($input['stock_quantity'] ?? 0),
            'min_stock' => intval($input['min_stock'] ?? 0),
            'track_stock' => isset($input['track_stock']) ? 1 : 0
        ];
        
        if ($data['sku']) {
            if ($this->db->single("SELECT id FROM products WHERE business_id = ? AND sku = ?", [$businessId, $data['sku']])) {
                return $this->error('El SKU ya existe');
            }
        }
        
        try {
            $this->db->beginTransaction();
            $productId = $this->db->insert('products', $data);
            if ($data['stock_quantity'] > 0 && $data['track_stock']) {
                $this->db->insert('inventory_movements', [
                    'business_id' => $businessId,
                    'product_id' => $productId,
                    'user_id' => $_SESSION['user_id'],
                    'movement_type' => 'in',
                    'quantity' => $data['stock_quantity'],
                    'reason' => 'Stock inicial',
                    'movement_date' => date('Y-m-d H:i:s')
                ]);
            }
            $this->db->commit();
            return $this->success(['product_id' => $productId], 'Producto creado');
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function handleSales() {
        // Implementar lógica para ventas
        return $this->error('Endpoint de ventas no implementado', 501);
    }
    
    private function handleCustomers() {
        // Implementar lógica para clientes
        return $this->error('Endpoint de clientes no implementado', 501);
    }

    // Añadir aquí los demás handlers: handleExpenses, handleDebts, handleReports
    private function handleExpenses() {
        return $this->error('Endpoint no implementado', 501);
    }
    private function handleDebts() {
        return $this->error('Endpoint no implementado', 501);
    }
    private function handleReports() {
        return $this->error('Endpoint no implementado', 501);
    }

    // Placeholder para update y delete de productos
    private function updateProduct($businessId) {
        return $this->error('Endpoint no implementado', 501);
    }
    private function deleteProduct($businessId) {
        return $this->error('Endpoint no implementado', 501);
    }
}

// Ejecutar API
$api = new APIHandler();
$api->handleRequest();