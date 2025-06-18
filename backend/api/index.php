<?php
/**
 * API PRINCIPAL - Punto de entrada unificado
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

function handleCategories($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Obtener categoría específica
                $categoryId = intval($_GET['id']);
                $category = $db->single(
                    "SELECT * FROM categories WHERE id = ? AND business_id = ? AND status = 1",
                    [$categoryId, $business_id]
                );
                
                if (!$category) {
                    apiError('Categoría no encontrada', 404);
                }
                
                jsonResponse($category);
            } else {
                // Listar categorías
                $categories = $db->fetchAll(
                    "SELECT * FROM categories 
                     WHERE business_id = ? AND status = 1 
                     ORDER BY name ASC",
                    [$business_id]
                );
                
                jsonResponse($categories);
            }
            break;
            
        case 'POST':
            // Crear categoría
            $name = trim($params['name'] ?? '');
            if (!$name) {
                apiError('El nombre de la categoría es requerido');
            }
            
            // Verificar nombre único
            $exists = $db->single(
                "SELECT id FROM categories WHERE name = ? AND business_id = ? AND status = 1",
                [$name, $business_id]
            );
            
            if ($exists) {
                apiError('Ya existe una categoría con ese nombre');
            }
            
            $categoryData = [
                'business_id' => $business_id,
                'name' => $name,
                'description' => trim($params['description'] ?? ''),
                'color' => trim($params['color'] ?? '#6B7280'),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $categoryId = $db->insert('categories', $categoryData);
            
            jsonResponse(['id' => $categoryId], 201, 'Categoría creada exitosamente');
            break;
            
        case 'PUT':
            // Actualizar categoría
            if (!isset($_GET['id'])) {
                apiError('ID de categoría requerido');
            }
            
            $categoryId = intval($_GET['id']);
            $name = trim($params['name'] ?? '');
            
            if (!$name) {
                apiError('El nombre de la categoría es requerido');
            }
            
            // Verificar que existe
            $category = $db->single(
                "SELECT * FROM categories WHERE id = ? AND business_id = ? AND status = 1",
                [$categoryId, $business_id]
            );
            
            if (!$category) {
                apiError('Categoría no encontrada', 404);
            }
            
            // Verificar nombre único (excepto la actual)
            $exists = $db->single(
                "SELECT id FROM categories WHERE name = ? AND business_id = ? AND id != ? AND status = 1",
                [$name, $business_id, $categoryId]
            );
            
            if ($exists) {
                apiError('Ya existe una categoría con ese nombre');
            }
            
            $updateData = [
                'name' => $name,
                'description' => trim($params['description'] ?? $category['description']),
                'color' => trim($params['color'] ?? $category['color']),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('categories', $updateData, "id = ? AND business_id = ?", [$categoryId, $business_id]);
            
            jsonResponse([], 200, 'Categoría actualizada exitosamente');
            break;
            
        case 'DELETE':
            // Eliminar categoría
            if (!isset($_GET['id'])) {
                apiError('ID de categoría requerido');
            }
            
            $categoryId = intval($_GET['id']);
            
            // Verificar que existe
            $category = $db->single(
                "SELECT id FROM categories WHERE id = ? AND business_id = ? AND status = 1",
                [$categoryId, $business_id]
            );
            
            if (!$category) {
                apiError('Categoría no encontrada', 404);
            }
            
            // Verificar que no tiene productos asociados
            $hasProducts = $db->single(
                "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 1",
                [$categoryId]
            );
            
            if ($hasProducts['count'] > 0) {
                apiError('No se puede eliminar la categoría porque tiene productos asociados');
            }
            
            // Marcar como eliminada
            $db->update(
                'categories', 
                ['status' => 0, 'updated_at' => date('Y-m-d H:i:s')], 
                "id = ? AND business_id = ?", 
                [$categoryId, $business_id]
            );
            
            jsonResponse([], 200, 'Categoría eliminada exitosamente');
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
            $reason = trim($params['reason'] ?? '');
            
            if (!$productId || !$type || $quantity <= 0) {
                apiError('Datos de ajuste incompletos');
            }
            
            if (!in_array($type, ['add', 'remove', 'set'])) {
                apiError('Tipo de ajuste inválido');
            }
            
            // Verificar que el producto existe
            $product = $db->single(
                "SELECT * FROM products WHERE id = ? AND business_id = ? AND status = 1",
                [$productId, $business_id]
            );
            
            if (!$product) {
                apiError('Producto no encontrado', 404);
            }
            
            $currentStock = intval($product['stock_quantity']);
            $newStock = $currentStock;
            $movementQuantity = $quantity;
            $movementType = 'adjustment';
            
            switch ($type) {
                case 'add':
                    $newStock = $currentStock + $quantity;
                    $movementType = 'in';
                    break;
                    
                case 'remove':
                    $newStock = max(0, $currentStock - $quantity);
                    $movementQuantity = $currentStock - $newStock; // Cantidad real removida
                    $movementType = 'out';
                    break;
                    
                case 'set':
                    $newStock = $quantity;
                    $movementQuantity = abs($newStock - $currentStock);
                    $movementType = $newStock > $currentStock ? 'in' : 'out';
                    break;
            }
            
            try {
                $db->beginTransaction();
                
                // Actualizar stock del producto
                $db->update(
                    'products',
                    ['stock_quantity' => $newStock, 'updated_at' => date('Y-m-d H:i:s')],
                    "id = ?",
                    [$productId]
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
            
        case 'GET':
            // Obtener movimientos de inventario
            $productId = intval($_GET['product_id'] ?? 0);
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereConditions = ["im.business_id = ?"];
            $params = [$business_id];
            
            if ($productId > 0) {
                $whereConditions[] = "im.product_id = ?";
                $params[] = $productId;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $movements = $db->fetchAll(
                "SELECT im.*, p.name as product_name, u.first_name, u.last_name
                 FROM inventory_movements im
                 LEFT JOIN products p ON im.product_id = p.id
                 LEFT JOIN users u ON im.user_id = u.id
                 WHERE {$whereClause}
                 ORDER BY im.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            );
            
            $total = $db->single(
                "SELECT COUNT(*) as total FROM inventory_movements im WHERE {$whereClause}",
                $params
            )['total'];
            
            jsonResponse([
                'movements' => $movements,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

function handleSales($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'GET':
            // Listar ventas
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            
            $whereConditions = ["business_id = ?"];
            $whereParams = [$business_id];
            
            if ($dateFrom) {
                $whereConditions[] = "DATE(created_at) >= ?";
                $whereParams[] = $dateFrom;
            }
            
            if ($dateTo) {
                $whereConditions[] = "DATE(created_at) <= ?";
                $whereParams[] = $dateTo;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sales = $db->fetchAll(
                "SELECT s.*, c.first_name, c.last_name, u.first_name as seller_name
                 FROM sales s
                 LEFT JOIN customers c ON s.customer_id = c.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE {$whereClause}
                 ORDER BY s.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                $whereParams
            );
            
            $total = $db->single(
                "SELECT COUNT(*) as total FROM sales WHERE {$whereClause}",
                $whereParams
            )['total'];
            
            jsonResponse([
                'sales' => $sales,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            // Crear venta
            $items = $params['items'] ?? [];
            $customerId = intval($params['customer_id'] ?? 0);
            $paymentMethod = $params['payment_method'] ?? 'cash';
            $notes = trim($params['notes'] ?? '');
            
            if (empty($items)) {
                apiError('La venta debe tener al menos un producto');
            }
            
            $subtotal = 0;
            $tax = 0;
            $total = 0;
            
            // Calcular totales y validar productos
            foreach ($items as $item) {
                $productId = intval($item['product_id'] ?? 0);
                $quantity = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                
                if (!$productId || $quantity <= 0 || $price <= 0) {
                    apiError('Datos de producto inválidos');
                }
                
                // Verificar stock disponible
                $product = $db->single(
                    "SELECT stock_quantity, track_stock FROM products WHERE id = ? AND business_id = ? AND status = 1",
                    [$productId, $business_id]
                );
                
                if (!$product) {
                    apiError("Producto con ID {$productId} no encontrado");
                }
                
                if ($product['track_stock'] && $product['stock_quantity'] < $quantity) {
                    apiError("Stock insuficiente para el producto con ID {$productId}");
                }
                
                $itemTotal = $quantity * $price;
                $subtotal += $itemTotal;
            }
            
            $tax = $subtotal * 0.18; // 18% IGV
            $total = $subtotal + $tax;
            
            try {
                $db->beginTransaction();
                
                // Crear venta
                $saleData = [
                    'business_id' => $business_id,
                    'customer_id' => $customerId ?: null,
                    'user_id' => $_SESSION['user_id'],
                    'sale_number' => generateSaleNumber($db, $business_id),
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'notes' => $notes,
                    'sale_date' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $saleId = $db->insert('sales', $saleData);
                
                // Crear items de venta y actualizar stock
                foreach ($items as $item) {
                    $productId = intval($item['product_id']);
                    $quantity = intval($item['quantity']);
                    $price = floatval($item['price']);
                    $itemTotal = $quantity * $price;
                    
                    // Insertar item de venta
                    $db->insert('sale_items', [
                        'sale_id' => $saleId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $itemTotal,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Actualizar stock
                    $db->query(
                        "UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = ? WHERE id = ?",
                        [$quantity, date('Y-m-d H:i:s'), $productId]
                    );
                    
                    // Registrar movimiento de inventario
                    $db->insert('inventory_movements', [
                        'business_id' => $business_id,
                        'product_id' => $productId,
                        'movement_type' => 'out',
                        'quantity' => $quantity,
                        'reason' => "Venta #{$saleData['sale_number']}",
                        'reference_type' => 'sale',
                        'reference_id' => $saleId,
                        'user_id' => $_SESSION['user_id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                $db->commit();
                
                jsonResponse([
                    'sale_id' => $saleId,
                    'sale_number' => $saleData['sale_number'],
                    'total' => $total
                ], 201, 'Venta registrada exitosamente');
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error creando venta: " . $e->getMessage());
                apiError('Error al procesar la venta', 500);
            }
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

function handleCustomers($db, $method, $params) {
    $business_id = $_SESSION['business_id'];
    
    switch ($method) {
        case 'GET':
            $customers = $db->fetchAll(
                "SELECT id, first_name, last_name, email, phone, document_number
                 FROM customers 
                 WHERE business_id = ? AND status = 1 
                 ORDER BY first_name, last_name",
                [$business_id]
            );
            
            jsonResponse($customers);
            break;
            
        case 'POST':
            $firstName = trim($params['first_name'] ?? '');
            $lastName = trim($params['last_name'] ?? '');
            $email = trim($params['email'] ?? '');
            $phone = trim($params['phone'] ?? '');
            
            if (!$firstName || !$lastName) {
                apiError('Nombre y apellido son requeridos');
            }
            
            $customerData = [
                'business_id' => $business_id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'document_type' => trim($params['document_type'] ?? ''),
                'document_number' => trim($params['document_number'] ?? ''),
                'address' => trim($params['address'] ?? ''),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $customerId = $db->insert('customers', $customerData);
            
            jsonResponse(['id' => $customerId], 201, 'Cliente creado exitosamente');
            break;
            
        default:
            apiError('Método no permitido', 405);
    }
}

// ===== FUNCIONES AUXILIARES =====

function requireAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }
}

function getRequestParams() {
    $input = file_get_contents('php://input');
    $params = json_decode($input, true) ?? [];
    
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
?>