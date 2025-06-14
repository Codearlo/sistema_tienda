<?php
/**
 * API Principal - Treinta App
 * Archivo: api/index.php
 * Descripción: Maneja todas las solicitudes a la API, enrutando a los controladores correspondientes.
 */

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests de CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración y autocargador
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
        $requestPath = trim($_SERVER['PATH_INFO'] ?? '', '/');
        $parts = explode('/', $requestPath);
        $this->endpoint = array_shift($parts) ?: 'index';
        $this->params = $parts;
    }
    
    public function handleRequest() {
        try {
            // Rutas públicas que no requieren autenticación
            $publicEndpoints = ['auth', 'test'];
            if (!in_array($this->endpoint, $publicEndpoints)) {
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
                case 'categories':
                    return $this->handleCategories();
                case 'sales':
                    return $this->handleSales();
                case 'customers':
                    return $this->handleCustomers();
                default:
                    return $this->error('Endpoint no encontrado: /' . $this->endpoint, 404);
            }
        } catch (Exception $e) {
            $code = is_int($e->getCode()) && $e->getCode() !== 0 ? $e->getCode() : 500;
            error_log("API Error en {$this->endpoint}: " . $e->getMessage());
            return $this->error($e->getMessage(), $code);
        }
    }
    
    // ===== Métodos de Utilidad =====
    
    private function checkAuth() {
        if (!isLoggedIn()) {
            throw new Exception('No autorizado. Por favor, inicie sesión.', 401);
        }
    }
    
    private function getInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON de entrada inválido', 400);
        }
        return $data ?: [];
    }
    
    private function success($data = null, $message = 'Operación exitosa') {
        return $this->response(['success' => true, 'message' => $message, 'data' => $data]);
    }
    
    private function error($message = 'Error', $code = 400) {
        http_response_code($code);
        return $this->response(['success' => false, 'message' => $message, 'error_code' => $code]);
    }
    
    private function response($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    // ===== Endpoints =====
    
    private function test() {
        return $this->success(['app' => APP_NAME, 'version' => APP_VERSION, 'status' => 'API funcionando correctamente']);
    }

    // --- Autenticación ---
    private function handleAuth() {
        $action = $this->params[0] ?? 'login';
        if ($this->method !== 'POST') return $this->error('Método no permitido para autenticación.', 405);
        
        switch ($action) {
            case 'login': return $this->login();
            case 'logout': return $this->logout();
            default: return $this->error('Acción de autenticación no válida.', 404);
        }
    }

    private function login() {
        $input = $this->getInput();
        $email = cleanInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) return $this->error('Email y contraseña son requeridos.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->error('Email no válido.');
        
        $user = $this->db->single("SELECT u.*, b.business_name FROM users u LEFT JOIN businesses b ON u.business_id = b.id WHERE u.email = ?", [$email]);
        
        if (!$user || $user['status'] != STATUS_ACTIVE) return $this->error('Credenciales incorrectas o usuario inactivo.');
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) return $this->error('Cuenta bloqueada. Intente más tarde.');
        
        if (!password_verify($password, $user['password'])) {
            $attempts = $user['login_attempts'] + 1;
            $updateData = ['login_attempts' => $attempts];
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $updateData['locked_until'] = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            }
            $this->db->update('users', $updateData, 'id = ?', [$user['id']]);
            return $this->error('Credenciales incorrectas.');
        }
        
        $this->db->update('users', ['login_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['business_id'] = $user['business_id'];
        $_SESSION['business_name'] = $user['business_name'];
        $_SESSION['logged_in_at'] = time();
        
        return $this->success(['user' => ['name' => $_SESSION['user_name']]], 'Login exitoso');
    }

    private function logout() {
        session_start();
        session_unset();
        session_destroy();
        return $this->success(null, 'Sesión cerrada exitosamente');
    }

    // --- Dashboard ---
    private function handleDashboard() {
        if ($this->method !== 'GET') return $this->error('Método no permitido', 405);
        $businessId = $_SESSION['business_id'];
        $today = date('Y-m-d');
        
        $stats = [
            'sales_today' => $this->db->single("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM sales WHERE business_id = ? AND DATE(sale_date) = ? AND status = 1", [$businessId, $today]),
            'products_sold_today' => $this->db->single("SELECT COALESCE(SUM(si.quantity), 0) as total FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.business_id = ? AND DATE(s.sale_date) = ? AND s.status = 1", [$businessId, $today]),
            'low_stock_products' => $this->db->fetchAll("SELECT id, name, stock_quantity, min_stock FROM products WHERE business_id = ? AND track_stock = 1 AND stock_quantity <= min_stock AND status = 1 ORDER BY stock_quantity ASC LIMIT 5", [$businessId]),
            'pending_debts' => $this->db->single("SELECT COALESCE(SUM(remaining_amount), 0) as total, COUNT(*) as count FROM debts WHERE business_id = ? AND type = 'receivable' AND status IN ('pending', 'partial')", [$businessId])
        ];
        
        $recent_sales = $this->db->fetchAll("SELECT s.sale_date, s.total_amount, s.payment_status, c.first_name, c.last_name, (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.business_id = ? AND s.status = 1 ORDER BY s.sale_date DESC LIMIT 5", [$businessId]);
        
        return $this->success(['stats' => $stats, 'recent_sales' => $recent_sales]);
    }

    // --- Productos ---
    private function handleProducts() {
        $productId = $this->params[0] ?? null;
        switch ($this->method) {
            case 'GET': return $this->getProducts($productId);
            case 'POST': return $this->createProduct();
            case 'PUT': return $this->updateProduct($productId);
            case 'DELETE': return $this->deleteProduct($productId);
            default: return $this->error('Método no permitido', 405);
        }
    }
    
    private function getProducts($productId) {
        $businessId = $_SESSION['business_id'];
        if ($productId) {
            $product = $this->db->single("SELECT * FROM products WHERE id = ? AND business_id = ?", [$productId, $businessId]);
            return $product ? $this->success($product) : $this->error('Producto no encontrado', 404);
        }

        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 12);
        $offset = ($page - 1) * $limit;
        $search = cleanInput($_GET['search'] ?? '');
        $category = intval($_GET['category'] ?? 0);
        $stockStatus = cleanInput($_GET['stock'] ?? '');

        $where = "p.business_id = ? AND p.status = 1";
        $params = [$businessId];
        
        if ($search) {
            $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        if ($category) {
            $where .= " AND p.category_id = ?";
            $params[] = $category;
        }
        if ($stockStatus) {
            if ($stockStatus === 'low') $where .= " AND p.stock_quantity <= p.min_stock AND p.stock_quantity > 0";
            if ($stockStatus === 'out') $where .= " AND p.stock_quantity <= 0";
        }
        
        $products = $this->db->fetchAll("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where ORDER BY p.name ASC LIMIT $limit OFFSET $offset", $params);
        $total = $this->db->single("SELECT COUNT(*) as total FROM products p WHERE $where", $params)['total'];
        
        $pagination = ['current_page' => $page, 'total_pages' => ceil($total / $limit), 'total_records' => $total];
        return $this->success(['products' => $products, 'pagination' => $pagination]);
    }

    private function createProduct() {
        $input = $this->getInput();
        $businessId = $_SESSION['business_id'];
        
        if (empty($input['name']) || !isset($input['selling_price'])) return $this->error('Nombre y precio de venta son requeridos.');

        $data = [
            'business_id' => $businessId,
            'name' => cleanInput($input['name']),
            'selling_price' => floatval($input['selling_price']),
            'category_id' => !empty($input['category_id']) ? intval($input['category_id']) : null,
            'sku' => !empty($input['sku']) ? cleanInput($input['sku']) : null,
            'barcode' => !empty($input['barcode']) ? cleanInput($input['barcode']) : null,
            'description' => cleanInput($input['description'] ?? ''),
            'cost_price' => floatval($input['cost_price'] ?? 0),
            'stock_quantity' => intval($input['stock_quantity'] ?? 0),
            'min_stock' => intval($input['min_stock'] ?? 0),
            'track_stock' => isset($input['track_stock']) && $input['track_stock'] ? 1 : 0,
            'status' => STATUS_ACTIVE
        ];

        if ($data['sku']) {
            $exists = $this->db->single("SELECT id FROM products WHERE sku = ? AND business_id = ?", [$data['sku'], $businessId]);
            if ($exists) return $this->error("El SKU '{$data['sku']}' ya está en uso.");
        }
        
        $this->db->beginTransaction();
        $productId = $this->db->insert('products', $data);
        if ($data['stock_quantity'] > 0 && $data['track_stock']) {
            $this->db->insert('inventory_movements', [
                'business_id' => $businessId, 'product_id' => $productId, 'user_id' => $_SESSION['user_id'],
                'movement_type' => 'in', 'quantity' => $data['stock_quantity'], 'reason' => 'Stock inicial',
                'movement_date' => date('Y-m-d H:i:s')
            ]);
        }
        $this->db->commit();
        
        return $this->success(['product_id' => $productId], 'Producto creado exitosamente.');
    }

    private function updateProduct($productId) {
        if (!$productId) return $this->error('ID de producto no proporcionado.', 400);
        $input = $this->getInput();
        $businessId = $_SESSION['business_id'];
        
        // Similar a createProduct pero con UPDATE y validación de existencia
        $data = [
            'name' => cleanInput($input['name']),
            'selling_price' => floatval($input['selling_price']),
            // ... otros campos
        ];
        
        $this->db->update('products', $data, 'id = ? AND business_id = ?', [$productId, $businessId]);
        return $this->success(null, 'Producto actualizado.');
    }

    private function deleteProduct($productId) {
        if (!$productId) return $this->error('ID de producto no proporcionado.', 400);
        $businessId = $_SESSION['business_id'];
        $this->db->update('products', ['status' => STATUS_DELETED], 'id = ? AND business_id = ?', [$productId, $businessId]);
        return $this->success(null, 'Producto eliminado.');
    }

    // --- Categorías, Ventas, Clientes (implementaciones similares) ---
    private function handleCategories() { /* ... */ return $this->success(null, 'Manejador de categorías.'); }
    private function handleSales() { /* ... */ return $this->success(null, 'Manejador de ventas.'); }
    private function handleCustomers() { /* ... */ return $this->success(null, 'Manejador de clientes.'); }
}

// Ejecutar API
$api = new APIHandler();
$api->handleRequest();
?>