<?php
/**
 * API PRINCIPAL - CORREGIDA PARA POS
 * Archivo: backend/api/index.php
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Obtener endpoint y método
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Verificar autenticación para endpoints protegidos
$publicEndpoints = ['auth', 'register'];
if (!in_array($endpoint, $publicEndpoints)) {
    requireAuth();
}

try {
    $db = getDB();
    $params = getRequestParams();
    
    // Enrutar según endpoint
    switch ($endpoint) {
        case 'products':
            handleProducts($db, $method, $params);
            break;
            
        case 'categories':
            handleCategories($db, $method, $params);
            break;
            
        case 'stock':
            handleStock($db, $method, $params);
            break;
            
        case 'sales':
            handleSales($db, $method, $params);
            break;
            
        case 'customers':
            handleCustomers($db, $method, $params);
            break;
            
        case 'auth':
            handleAuth($db, $method, $params);
            break;
            
        default:
            apiError('Endpoint no encontrado', 404);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en API: " . $e->getMessage());
    apiError('Error interno del servidor', 500);
}

// ===== FUNCIONES DE MANEJO =====

function handleProducts($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Obtener producto específico
                $productId = intval($_GET['id']);
                $product = $db->single(
                    "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = ? AND p.business_id = ? AND p.status = 1",
                    [$productId, $business_id]
                );
                
                if (!$product) {
                    apiError('Producto no encontrado', 404);
                }
                
                jsonResponse($product);
            } else {
                // CORREGIDO: Listar productos con stock actualizado
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $search = cleanInput($_GET['search'] ?? '');
                $category = intval($_GET['category'] ?? 0);
                $offset = ($page - 1) * $limit;
                
                $whereConditions = ["p.business_id = ?", "p.status = 1"];
                $params = [$business_id];
                
                if ($search) {
                    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
                    $searchTerm = "%{$search}%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if ($category > 0) {
                    $whereConditions[] = "p.category_id = ?";
                    $params[] = $category;
                }
                
                $whereClause = implode(' AND ', $whereConditions);
                
                // CORREGIDO: Consulta que usa directamente stock_quantity de tabla products
                $products = $db->fetchAll(
                    "SELECT p.id, p.name, p.sku, p.barcode, p.selling_price, 
                            p.stock_quantity as current_stock, p.min_stock, p.track_stock,
                            c.name as category_name, p.category_id,
                            p.image, p.unit,
                            CASE 
                                WHEN p.stock_quantity <= p.min_stock THEN 1 
                                ELSE 0 
                            END as low_stock
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE {$whereClause}
                     ORDER BY p.name ASC 
                     LIMIT {$limit} OFFSET {$offset}",
                    $params
                );
                
                // Contar total
                $total = $db->single(
                    "SELECT COUNT(*) as total FROM products p WHERE " . $whereClause,
                    $params
                )['total'];
                
                jsonResponse([
                    'products' => $products,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Crear producto
            $required_fields = ['name', 'selling_price'];
            foreach ($required_fields as $field) {
                if (empty($params[$field])) {
                    apiError("El campo '{$field}' es requerido");
                }
            }
            
            // Verificar SKU único si se proporciona
            if (!empty($params['sku'])) {
                $exists = $db->single(
                    "SELECT id FROM products WHERE sku = ? AND business_id = ? AND status = 1",
                    [$params['sku'], $business_id]
                );
                if ($exists) {
                    apiError('El SKU ya está en uso');
                }
            }
            
            // Generar SKU automático si no se proporciona
            $sku = !empty($params['sku']) ? $params['sku'] : generateSKU($db, $business_id);
            
            $productData = [
                'business_id' => $business_id,
                'name' => cleanInput($params['name']),
                'sku' => $sku,
                'barcode' => cleanInput($params['barcode'] ?? ''),
                'description' => cleanInput($params['description'] ?? ''),
                'category_id' => !empty($params['category_id']) ? intval($params['category_id']) : null,
                'cost_price' => floatval($params['cost_price'] ?? 0),
                'selling_price' => floatval($params['selling_price']),
                'wholesale_price' => floatval($params['wholesale_price'] ?? 0),
                'stock_quantity' => intval($params['stock_quantity'] ?? 0),
                'min_stock' => intval($params['min_stock'] ?? 0),
                'track_stock' => !empty($params['track_stock']) ? 1 : 0,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            try {
                $db->beginTransaction();
                
                $productId = $db->insert('products', $productData);
                
                // Registrar movimiento de inventario inicial si hay stock
                if ($productData['stock_quantity'] > 0) {
                    $db->insert('inventory_movements', [
                        'business_id' => $business_id,
                        'product_id' => $productId,
                        'movement_type' => 'in',
                        'quantity' => $productData['stock_quantity'],
                        'previous_stock' => 0,
                        'new_stock' => $productData['stock_quantity'],
                        'reason' => 'Stock inicial',
                        'user_id' => $_SESSION['user_id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                $db->commit();
                
                jsonResponse(['id' => $productId], 201, 'Producto creado exitosamente');
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error creando producto: " . $e->getMessage());
                apiError('Error al crear el producto', 500);
            }
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

function handleSales($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'POST':
            // CORREGIDO: Procesar venta con validación de stock desde tabla products
            $customerId = !empty($params['customer_id']) ? intval($params['customer_id']) : null;
            $paymentMethod = cleanInput($params['payment_method'] ?? 'cash');
            $items = $params['items'] ?? [];
            
            if (empty($items)) {
                apiError('No hay productos en la venta');
            }
            
            $subtotal = 0;
            
            // Validar items y calcular subtotal
            foreach ($items as $item) {
                $productId = intval($item['product_id'] ?? 0);
                $quantity = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                
                if (!$productId || $quantity <= 0 || $price <= 0) {
                    apiError('Datos de producto inválidos');
                }
                
                // CORREGIDO: Verificar stock disponible desde tabla products
                $product = $db->single(
                    "SELECT stock_quantity, track_stock, name FROM products 
                     WHERE id = ? AND business_id = ? AND status = 1",
                    [$productId, $business_id]
                );
                
                if (!$product) {
                    apiError("Producto con ID {$productId} no encontrado");
                }
                
                if ($product['track_stock'] && $product['stock_quantity'] < $quantity) {
                    apiError("Stock insuficiente para {$product['name']}. Disponible: {$product['stock_quantity']}");
                }
                
                $itemTotal = $quantity * $price;
                $subtotal += $itemTotal;
            }
            
            $tax = $subtotal * 0.18; // 18% IGV
            $total = $subtotal + $tax;
            
            try {
                $db->beginTransaction();
                
                // Crear venta
                $saleNumber = generateSaleNumber($db, $business_id);
                $saleData = [
                    'business_id' => $business_id,
                    'customer_id' => $customerId,
                    'sale_number' => $saleNumber,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'completed',
                    'status' => 'completed',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $saleId = $db->insert('sales', $saleData);
                
                // Crear items de venta y actualizar stock
                foreach ($items as $item) {
                    $productId = intval($item['product_id']);
                    $quantity = intval($item['quantity']);
                    $price = floatval($item['price']);
                    
                    // Insertar item de venta
                    $db->insert('sale_items', [
                        'sale_id' => $saleId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $quantity * $price,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // CORREGIDO: Actualizar stock directamente en tabla products
                    $product = $db->single(
                        "SELECT stock_quantity, track_stock FROM products WHERE id = ?",
                        [$productId]
                    );
                    
                    if ($product['track_stock']) {
                        $newStock = $product['stock_quantity'] - $quantity;
                        
                        $db->execute(
                            "UPDATE products SET stock_quantity = ?, updated_at = ? WHERE id = ?",
                            [$newStock, date('Y-m-d H:i:s'), $productId]
                        );
                        
                        // Registrar movimiento de inventario
                        $db->insert('inventory_movements', [
                            'business_id' => $business_id,
                            'product_id' => $productId,
                            'movement_type' => 'out',
                            'quantity' => $quantity,
                            'previous_stock' => $product['stock_quantity'],
                            'new_stock' => $newStock,
                            'reason' => "Venta #{$saleNumber}",
                            'reference_type' => 'sale',
                            'reference_id' => $saleId,
                            'user_id' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                $db->commit();
                
                // Obtener datos completos de la venta para respuesta
                $saleResult = $db->single(
                    "SELECT s.*, 
                            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name
                     FROM sales s
                     LEFT JOIN customers c ON s.customer_id = c.id
                     WHERE s.id = ?",
                    [$saleId]
                );
                
                jsonResponse($saleResult, 201, 'Venta procesada exitosamente');
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error procesando venta: " . $e->getMessage());
                apiError('Error al procesar la venta: ' . $e->getMessage(), 500);
            }
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

function handleCategories($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'GET':
            $categories = $db->fetchAll(
                "SELECT * FROM categories 
                 WHERE business_id = ? AND status = 1 
                 ORDER BY name ASC",
                [$business_id]
            );
            
            jsonResponse($categories);
            break;
            
        case 'POST':
            $name = trim($params['name'] ?? '');
            
            if (empty($name)) {
                apiError('El nombre de la categoría es requerido');
            }
            
            $categoryData = [
                'business_id' => $business_id,
                'name' => $name,
                'description' => cleanInput($params['description'] ?? ''),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $categoryId = $db->insert('categories', $categoryData);
            
            jsonResponse(['id' => $categoryId], 201, 'Categoría creada exitosamente');
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

function handleStock($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'POST':
            // Ajustar stock
            $productId = intval($params['product_id'] ?? 0);
            $type = $params['type'] ?? '';
            $quantity = intval($params['quantity'] ?? 0);
            $reason = cleanInput($params['reason'] ?? '');
            
            if (!$productId || !$type || $quantity <= 0) {
                apiError('Datos de ajuste de stock inválidos');
            }
            
            // CORREGIDO: Obtener stock actual desde tabla products
            $product = $db->single(
                "SELECT stock_quantity FROM products WHERE id = ? AND business_id = ? AND status = 1",
                [$productId, $business_id]
            );
            
            if (!$product) {
                apiError('Producto no encontrado');
            }
            
            $currentStock = $product['stock_quantity'];
            $newStock = $currentStock;
            $movementType = '';
            $movementQuantity = $quantity;
            
            switch ($type) {
                case 'add':
                    $newStock = $currentStock + $quantity;
                    $movementType = 'in';
                    break;
                    
                case 'remove':
                    $newStock = max(0, $currentStock - $quantity);
                    $movementType = 'out';
                    break;
                    
                case 'set':
                    $newStock = $quantity;
                    $movementType = $quantity > $currentStock ? 'in' : 'out';
                    $movementQuantity = abs($quantity - $currentStock);
                    break;
                    
                default:
                    apiError('Tipo de ajuste inválido');
            }
            
            try {
                $db->beginTransaction();
                
                // CORREGIDO: Actualizar stock en tabla products
                $db->execute(
                    "UPDATE products SET stock_quantity = ?, updated_at = ? WHERE id = ?",
                    [$newStock, date('Y-m-d H:i:s'), $productId]
                );
                
                // Registrar movimiento de inventario
                $db->insert('inventory_movements', [
                    'business_id' => $business_id,
                    'product_id' => $productId,
                    'movement_type' => $movementType,
                    'quantity' => $movementQuantity,
                    'previous_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'reason' => $reason ?: "Ajuste de stock: {$type}",
                    'user_id' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $db->commit();
                
                jsonResponse([
                    'previous_stock' => $currentStock,
                    'new_stock' => $newStock
                ], 200, 'Stock ajustado exitosamente');
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error ajustando stock: " . $e->getMessage());
                apiError('Error al ajustar el stock', 500);
            }
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

// ===== FUNCIONES DE UTILIDAD =====

function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
        apiError('No autorizado', 401);
    }
}

function getRequestParams() {
    $params = [];
    
    // Obtener datos del body
    $input = file_get_contents('php://input');
    if ($input) {
        $params = json_decode($input, true) ?? [];
    }
    
    // Combinar con GET parameters
    $params = array_merge($_GET, $params);
    
    return $params;
}

function jsonResponse($data = [], $statusCode = 200, $message = '') {
    http_response_code($statusCode);
    
    $response = [
        'success' => $statusCode < 400,
        'data' => $data
    ];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    echo json_encode($response);
    exit();
}

function apiError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}

function generateSaleNumber($db, $business_id) {
    $today = date('Y-m-d');
    $prefix = date('Ymd');
    
    // Obtener último número de venta del día
    $lastSale = $db->single(
        "SELECT sale_number FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ? 
         ORDER BY id DESC LIMIT 1",
        [$business_id, $today]
    );
    
    if ($lastSale && strpos($lastSale['sale_number'], $prefix) === 0) {
        $lastNumber = intval(substr($lastSale['sale_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

function generateSKU($db, $business_id) {
    $prefix = 'PRD';
    
    // Obtener último SKU
    $lastProduct = $db->single(
        "SELECT sku FROM products 
         WHERE business_id = ? AND sku LIKE '{$prefix}%' 
         ORDER BY id DESC LIMIT 1",
        [$business_id]
    );
    
    if ($lastProduct && strpos($lastProduct['sku'], $prefix) === 0) {
        $lastNumber = intval(substr($lastProduct['sku'], 3));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
}

function cleanInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>