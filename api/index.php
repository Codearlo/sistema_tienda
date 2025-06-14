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
        $path = str_replace('/api/', '', $path);
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
            error_log("API Error: " . $e->getMessage());
            return $this->error('Error interno del servidor', 500);
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
            return $this->error('Usuario no encontrado');
        }
        
        // Verificar bloqueo
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return $this->error('Cuenta temporalmente bloqueada');
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            // Incrementar intentos fallidos
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
        
        // Estadísticas del día
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
                "SELECT id, name, stock_quantity, min_stock 
                 FROM products 
                 WHERE business_id = ? AND stock_quantity <= min_stock AND status = 1 
                 ORDER BY stock_quantity ASC 
                 LIMIT 10",
                [$businessId]
            ),
            'pending_debts' => $this->db->single(
                "SELECT COALESCE(SUM(remaining_amount), 0) as total, COUNT(*) as count 
                 FROM debts 
                 WHERE business_id = ? AND type = 'receivable' AND status IN ('pending', 'partial')",
                [$businessId]
            )
        ];
        
        // Ventas recientes
        $recent_sales = $this->db->fetchAll(
            "SELECT s.*, c.first_name, c.last_name,
                    (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             WHERE s.business_id = ? AND s.status = 1 
             ORDER BY s.sale_date DESC 
             LIMIT 10",
            [$businessId]
        );
        
        // Deudas próximas a vencer
        $upcoming_debts = $this->db->fetchAll(
            "SELECT d.*, c.first_name, c.last_name 
             FROM debts d 
             LEFT JOIN customers c ON d.customer_id = c.id 
             WHERE d.business_id = ? AND d.type = 'receivable' 
             AND d.status IN ('pending', 'partial') 
             ORDER BY d.due_date ASC 
             LIMIT 10",
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
        
        $where = "business_id = ? AND status = 1";
        $params = [$businessId];
        
        if ($search) {
            $where .= " AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($category) {
            $where .= " AND category_id = ?";
            $params[] = $category;
        }
        
        // Obtener productos
        $products = $this->db->fetchAll(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE $where 
             ORDER BY p.name ASC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        // Contar total
        $total = $this->db->single("SELECT COUNT(*) as total FROM products WHERE $where", $params)['total'];
        
        // Obtener categorías para el filtro
        $categories = $this->db->fetchAll(
            "SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name",
            [$businessId]
        );
        
        return $this->success([
            'products' => $products,
            'categories' => $categories,
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
        
        // Validar datos requeridos
        $required = ['name', 'selling_price'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                return $this->error("El campo $field es requerido");
            }
        }
        
        // Preparar datos
        $data = [
            'business_id' => $businessId,
            'category_id' => $input['category_id'] ?: null,
            'sku' => $input['sku'] ?: null,
            'barcode' => $input['barcode'] ?: null,
            'name' => cleanInput($input['name']),
            'description' => cleanInput($input['description'] ?? ''),
            'cost_price' => floatval($input['cost_price'] ?? 0),
            'selling_price' => floatval($input['selling_price']),
            'wholesale_price' => floatval($input['wholesale_price'] ?? 0),
            'stock_quantity' => intval($input['stock_quantity'] ?? 0),
            'min_stock' => intval($input['min_stock'] ?? 0),
            'unit' => cleanInput($input['unit'] ?? 'unit'),
            'track_stock' => isset($input['track_stock']) ? 1 : 0
        ];
        
        // Verificar SKU único
        if ($data['sku']) {
            $existing = $this->db->single(
                "SELECT id FROM products WHERE business_id = ? AND sku = ? AND status = 1",
                [$businessId, $data['sku']]
            );
            if ($existing) {
                return $this->error('El SKU ya existe');
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            $productId = $this->db->insert('products', $data);
            
            // Registrar movimiento de inventario inicial si hay stock
            if ($data['stock_quantity'] > 0) {
                $this->db->insert('inventory_movements', [
                    'business_id' => $businessId,
                    'product_id' => $productId,
                    'user_id' => $_SESSION['user_id'],
                    'movement_type' => 'in',
                    'quantity' => $data['stock_quantity'],
                    'unit_cost' => $data['cost_price'],
                    'reference_type' => 'initial',
                    'reason' => 'Stock inicial',
                    'movement_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            
            return $this->success(['product_id' => $productId], 'Producto creado exitosamente');
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function handleSales($businessId) {
        switch ($this->method) {
            case 'GET':
                return $this->getSales($businessId);
            case 'POST':
                return $this->createSale($businessId);
            default:
                return $this->error('Método no permitido', 405);
        }
    }
    
    private function createSale($businessId) {
        $input = $this->getInput();
        
        // Validar datos
        if (empty($input['items']) || !is_array($input['items'])) {
            return $this->error('Se requieren items para la venta');
        }
        
        $customerId = $input['customer_id'] ?: null;
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $notes = cleanInput($input['notes'] ?? '');
        
        try {
            $this->db->beginTransaction();
            
            // Generar número de venta
            $saleNumber = $this->generateSaleNumber($businessId);
            
            // Calcular totales
            $subtotal = 0;
            $taxAmount = 0;
            $discountAmount = floatval($input['discount_amount'] ?? 0);
            
            foreach ($input['items'] as $item) {
                $lineTotal = floatval($item['quantity']) * floatval($item['unit_price']);
                $subtotal += $lineTotal;
                $taxAmount += $lineTotal * (floatval($item['tax_rate'] ?? 0) / 100);
            }
            
            $total = $subtotal + $taxAmount - $discountAmount;
            
            // Crear venta
            $saleData = [
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'user_id' => $_SESSION['user_id'],
                'sale_number' => $saleNumber,
                'sale_date' => date('Y-m-d H:i:s'),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'amount_paid' => $total,
                'amount_due' => 0,
                'notes' => $notes
            ];
            
            $saleId = $this->db->insert('sales', $saleData);
            
            // Procesar items
            foreach ($input['items'] as $item) {
                $productId = intval($item['product_id']);
                $quantity = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);
                $costPrice = floatval($item['cost_price'] ?? 0);
                
                // Verificar producto
                $product = $this->db->single(
                    "SELECT * FROM products WHERE id = ? AND business_id = ? AND status = 1",
                    [$productId, $businessId]
                );
                
                if (!$product) {
                    throw new Exception("Producto no encontrado: ID $productId");
                }
                
                // Verificar stock
                if ($product['track_stock'] && $product['stock_quantity'] < $quantity) {
                    throw new Exception("Stock insuficiente para: {$product['name']}");
                }
                
                // Insertar item de venta
                $itemData = [
                    'sale_id' => $saleId,
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'product_sku' => $product['sku'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost_price' => $costPrice ?: $product['cost_price'],
                    'discount_amount' => floatval($item['discount_amount'] ?? 0),
                    'tax_rate' => floatval($item['tax_rate'] ?? 0),
                    'tax_amount' => $unitPrice * $quantity * (floatval($item['tax_rate'] ?? 0) / 100),
                    'line_total' => $quantity * $unitPrice
                ];
                
                $this->db->insert('sale_items', $itemData);
                
                // Actualizar stock
                if ($product['track_stock']) {
                    $this->db->update('products', 
                        ['stock_quantity' => $product['stock_quantity'] - $quantity],
                        'id = ?',
                        [$productId]
                    );
                    
                    // Registrar movimiento de inventario
                    $this->db->insert('inventory_movements', [
                        'business_id' => $businessId,
                        'product_id' => $productId,
                        'user_id' => $_SESSION['user_id'],
                        'movement_type' => 'out',
                        'quantity' => -$quantity,
                        'unit_cost' => $costPrice,
                        'reference_type' => 'sale',
                        'reference_id' => $saleId,
                        'reason' => 'Venta',
                        'movement_date' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Si es venta a crédito, crear deuda
            if ($paymentMethod === 'credit' && $customerId) {
                $dueDate = date('Y-m-d', strtotime('+30 days')); // 30 días por defecto
                
                $this->db->insert('debts', [
                    'business_id' => $businessId,
                    'customer_id' => $customerId,
                    'sale_id' => $saleId,
                    'type' => 'receivable',
                    'description' => "Venta #{$saleNumber}",
                    'original_amount' => $total,
                    'remaining_amount' => $total,
                    'due_date' => $dueDate
                ]);
            }
            
            $this->db->commit();
            
            return $this->success([
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
                'total' => $total
            ], 'Venta registrada exitosamente');
            
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->error($e->getMessage());
        }
    }
    
    private function generateSaleNumber($businessId) {
        $today = date('Ymd');
        $lastSale = $this->db->single(
            "SELECT sale_number FROM sales 
             WHERE business_id = ? AND DATE(sale_date) = CURDATE() 
             ORDER BY id DESC LIMIT 1",
            [$businessId]
        );
        
        if ($lastSale) {
            $lastNumber = intval(substr($lastSale['sale_number'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $today . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    private function handleCustomers() {
        $businessId = $_SESSION['business_id'];
        
        switch ($this->method) {
            case 'GET':
                return $this->getCustomers($businessId);
            case 'POST':
                return $this->createCustomer($businessId);
            case 'PUT':
                return $this->updateCustomer($businessId);
            case 'DELETE':
                return $this->deleteCustomer($businessId);
            default:
                return $this->error('Método no permitido', 405);
        }
    }
    
    private function getCustomers($businessId) {
        $search = cleanInput($_GET['search'] ?? '');
        $limit = intval($_GET['limit'] ?? 50);
        
        $where = "business_id = ? AND status = 1";
        $params = [$businessId];
        
        if ($search) {
            $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $customers = $this->db->fetchAll(
            "SELECT * FROM customers WHERE $where ORDER BY first_name ASC LIMIT $limit",
            $params
        );
        
        return $this->success(['customers' => $customers]);
    }
    
    private function createCustomer($businessId) {
        $input = $this->getInput();
        
        if (empty($input['first_name'])) {
            return $this->error('El nombre es requerido');
        }
        
        $data = [
            'business_id' => $businessId,
            'first_name' => cleanInput($input['first_name']),
            'last_name' => cleanInput($input['last_name'] ?? ''),
            'email' => cleanInput($input['email'] ?? ''),
            'phone' => cleanInput($input['phone'] ?? ''),
            'document_type' => $input['document_type'] ?? 'dni',
            'document_number' => cleanInput($input['document_number'] ?? ''),
            'address' => cleanInput($input['address'] ?? ''),
            'credit_limit' => floatval($input['credit_limit'] ?? 0)
        ];
        
        // Validar email si se proporciona
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Email no válido');
        }
        
        $customerId = $this->db->insert('customers', $data);
        
        return $this->success(['customer_id' => $customerId], 'Cliente creado exitosamente');
    }
}

// Ejecutar API
try {
    $api = new APIHandler();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}