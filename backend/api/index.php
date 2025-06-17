<?php
/**
 * API PRINCIPAL - TREINTA POS
 * Archivo: backend/api/index.php
 * Enrutador principal de la API REST
 */

// Headers CORS y configuración
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejo de preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inicializar sesión y configuración
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Función para responder con JSON
function jsonResponse($data, $status = 200, $message = null) {
    http_response_code($status);
    
    $response = [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'data' => $data
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    echo json_encode($response);
    exit();
}

// Función para manejar errores
function apiError($message, $status = 400, $details = null) {
    $response = [
        'success' => false,
        'status' => $status,
        'error' => $message
    ];
    
    if ($details && defined('APP_DEBUG') && APP_DEBUG) {
        $response['details'] = $details;
    }
    
    jsonResponse(null, $status, $message);
}

// Verificar autenticación para rutas protegidas
function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
        apiError('Acceso no autorizado', 401);
    }
}

// Obtener método y ruta
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/backend/api/', '', $path);
$path = trim($path, '/');

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$params = array_merge($_GET, $_POST, $input);

try {
    $db = getDB();
} catch (Exception $e) {
    apiError('Error de conexión a la base de datos', 500);
}

// Enrutador principal
switch ($path) {
    // ===== AUTENTICACIÓN =====
    case 'auth/login':
        if ($method !== 'POST') {
            apiError('Método no permitido', 405);
        }
        
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';
        
        if (!$email || !$password) {
            apiError('Email y contraseña son requeridos');
        }
        
        try {
            $user = $db->fetchOne(
                "SELECT u.*, b.name as business_name, b.status as business_status 
                 FROM users u 
                 LEFT JOIN businesses b ON u.business_id = b.id 
                 WHERE u.email = ? AND u.status = 1",
                [$email]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['business_status'] != 1) {
                    apiError('Negocio inactivo o suspendido');
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['business_id'] = $user['business_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                jsonResponse([
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'business_id' => $user['business_id'],
                        'business_name' => $user['business_name']
                    ]
                ], 200, 'Login exitoso');
            } else {
                apiError('Credenciales inválidas', 401);
            }
        } catch (Exception $e) {
            apiError('Error en el login', 500, $e->getMessage());
        }
        break;
        
    case 'auth/logout':
        session_destroy();
        jsonResponse(null, 200, 'Logout exitoso');
        break;
        
    case 'auth/me':
        requireAuth();
        
        try {
            $user = $db->fetchOne(
                "SELECT u.*, b.name as business_name 
                 FROM users u 
                 LEFT JOIN businesses b ON u.business_id = b.id 
                 WHERE u.id = ?",
                [$_SESSION['user_id']]
            );
            
            if ($user) {
                jsonResponse([
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'business_id' => $user['business_id'],
                    'business_name' => $user['business_name']
                ]);
            } else {
                apiError('Usuario no encontrado', 404);
            }
        } catch (Exception $e) {
            apiError('Error obteniendo datos del usuario', 500);
        }
        break;
        
    // ===== DASHBOARD =====
    case 'dashboard/stats':
        requireAuth();
        
        try {
            $businessId = $_SESSION['business_id'];
            $today = date('Y-m-d');
            $thisMonth = date('Y-m');
            
            // Ventas de hoy
            $todaySales = $db->fetchOne(
                "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
                 FROM sales 
                 WHERE business_id = ? AND DATE(created_at) = ?",
                [$businessId, $today]
            );
            
            // Ventas del mes
            $monthSales = $db->fetchOne(
                "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
                 FROM sales 
                 WHERE business_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?",
                [$businessId, $thisMonth]
            );
            
            // Productos con stock bajo
            $lowStock = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM products 
                 WHERE business_id = ? AND stock <= min_stock AND status = 1",
                [$businessId]
            );
            
            // Total de productos activos
            $totalProducts = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM products 
                 WHERE business_id = ? AND status = 1",
                [$businessId]
            );
            
            jsonResponse([
                'today_sales' => [
                    'count' => (int)$todaySales['count'],
                    'total' => (float)$todaySales['total']
                ],
                'month_sales' => [
                    'count' => (int)$monthSales['count'],
                    'total' => (float)$monthSales['total']
                ],
                'low_stock_count' => (int)$lowStock['count'],
                'total_products' => (int)$totalProducts['count']
            ]);
        } catch (Exception $e) {
            apiError('Error obteniendo estadísticas', 500);
        }
        break;
        
    // ===== PRODUCTOS =====
    case 'products':
        requireAuth();
        
        if ($method === 'GET') {
            // Listar productos
            try {
                $page = max(1, (int)($params['page'] ?? 1));
                $limit = min(100, max(10, (int)($params['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $search = $params['search'] ?? '';
                $category = $params['category'] ?? '';
                
                $where = ["business_id = ?"];
                $whereParams = [$_SESSION['business_id']];
                
                if ($search) {
                    $where[] = "(name LIKE ? OR barcode LIKE ?)";
                    $whereParams[] = "%$search%";
                    $whereParams[] = "%$search%";
                }
                
                if ($category) {
                    $where[] = "category_id = ?";
                    $whereParams[] = $category;
                }
                
                $whereClause = implode(' AND ', $where);
                
                // Obtener productos
                $products = $db->fetchAll(
                    "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE $whereClause 
                     ORDER BY p.name ASC 
                     LIMIT ? OFFSET ?",
                    array_merge($whereParams, [$limit, $offset])
                );
                
                // Contar total
                $total = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM products WHERE $whereClause",
                    $whereParams
                )['count'];
                
                jsonResponse([
                    'products' => $products,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            } catch (Exception $e) {
                apiError('Error obteniendo productos', 500);
            }
            
        } elseif ($method === 'POST') {
            // Crear producto
            try {
                $required = ['name', 'price'];
                foreach ($required as $field) {
                    if (!isset($params[$field]) || trim($params[$field]) === '') {
                        apiError("El campo '$field' es requerido");
                    }
                }
                
                $productId = $db->insert('products', [
                    'business_id' => $_SESSION['business_id'],
                    'name' => trim($params['name']),
                    'description' => trim($params['description'] ?? ''),
                    'price' => (float)$params['price'],
                    'cost' => (float)($params['cost'] ?? 0),
                    'stock' => (int)($params['stock'] ?? 0),
                    'min_stock' => (int)($params['min_stock'] ?? 0),
                    'barcode' => trim($params['barcode'] ?? ''),
                    'category_id' => (int)($params['category_id'] ?? 0) ?: null,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                jsonResponse(['id' => $productId], 201, 'Producto creado exitosamente');
            } catch (Exception $e) {
                apiError('Error creando producto', 500);
            }
        } else {
            apiError('Método no permitido', 405);
        }
        break;
        
    // ===== CATEGORÍAS =====
    case 'categories':
        requireAuth();
        
        if ($method === 'GET') {
            try {
                $categories = $db->fetchAll(
                    "SELECT * FROM categories 
                     WHERE business_id = ? AND status = 1 
                     ORDER BY name ASC",
                    [$_SESSION['business_id']]
                );
                
                jsonResponse($categories);
            } catch (Exception $e) {
                apiError('Error obteniendo categorías', 500);
            }
            
        } elseif ($method === 'POST') {
            try {
                $name = trim($params['name'] ?? '');
                if (!$name) {
                    apiError('El nombre de la categoría es requerido');
                }
                
                $categoryId = $db->insert('categories', [
                    'business_id' => $_SESSION['business_id'],
                    'name' => $name,
                    'description' => trim($params['description'] ?? ''),
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                jsonResponse(['id' => $categoryId], 201, 'Categoría creada exitosamente');
            } catch (Exception $e) {
                apiError('Error creando categoría', 500);
            }
        } else {
            apiError('Método no permitido', 405);
        }
        break;
        
    // ===== VENTAS =====
    case 'sales':
        requireAuth();
        
        if ($method === 'GET') {
            // Listar ventas
            try {
                $page = max(1, (int)($params['page'] ?? 1));
                $limit = min(100, max(10, (int)($params['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                $dateFrom = $params['date_from'] ?? '';
                $dateTo = $params['date_to'] ?? '';
                
                $where = ["business_id = ?"];
                $whereParams = [$_SESSION['business_id']];
                
                if ($dateFrom) {
                    $where[] = "DATE(created_at) >= ?";
                    $whereParams[] = $dateFrom;
                }
                
                if ($dateTo) {
                    $where[] = "DATE(created_at) <= ?";
                    $whereParams[] = $dateTo;
                }
                
                $whereClause = implode(' AND ', $where);
                
                $sales = $db->fetchAll(
                    "SELECT s.*, u.name as user_name, c.name as customer_name 
                     FROM sales s 
                     LEFT JOIN users u ON s.user_id = u.id 
                     LEFT JOIN customers c ON s.customer_id = c.id 
                     WHERE $whereClause 
                     ORDER BY s.created_at DESC 
                     LIMIT ? OFFSET ?",
                    array_merge($whereParams, [$limit, $offset])
                );
                
                $total = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM sales WHERE $whereClause",
                    $whereParams
                )['count'];
                
                jsonResponse([
                    'sales' => $sales,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            } catch (Exception $e) {
                apiError('Error obteniendo ventas', 500);
            }
            
        } elseif ($method === 'POST') {
            // Crear venta
            try {
                $items = $params['items'] ?? [];
                if (!$items || !is_array($items)) {
                    apiError('Se requieren items para la venta');
                }
                
                $db->beginTransaction();
                
                // Calcular totales
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $item['quantity'] * $item['price'];
                }
                
                $tax = $subtotal * 0.18; // IGV 18%
                $total = $subtotal + $tax;
                
                // Crear venta
                $saleId = $db->insert('sales', [
                    'business_id' => $_SESSION['business_id'],
                    'user_id' => $_SESSION['user_id'],
                    'customer_id' => (int)($params['customer_id'] ?? 0) ?: null,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => (float)($params['discount'] ?? 0),
                    'total' => $total - (float)($params['discount'] ?? 0),
                    'payment_method' => $params['payment_method'] ?? 'cash',
                    'notes' => trim($params['notes'] ?? ''),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Crear items de venta y actualizar stock
                foreach ($items as $item) {
                    $db->insert('sale_items', [
                        'sale_id' => $saleId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['quantity'] * $item['price']
                    ]);
                    
                    // Reducir stock
                    $db->execute(
                        "UPDATE products SET stock = stock - ? WHERE id = ? AND business_id = ?",
                        [$item['quantity'], $item['product_id'], $_SESSION['business_id']]
                    );
                }
                
                $db->commit();
                
                jsonResponse(['id' => $saleId], 201, 'Venta registrada exitosamente');
            } catch (Exception $e) {
                $db->rollback();
                apiError('Error registrando venta: ' . $e->getMessage(), 500);
            }
        } else {
            apiError('Método no permitido', 405);
        }
        break;
        
    // ===== RUTA NO ENCONTRADA =====
    default:
        apiError('Endpoint no encontrado', 404);
}
?>