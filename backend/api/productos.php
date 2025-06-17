<?php
/**
 * API DE PRODUCTOS - Versión Corregida
 * Archivo: backend/api/productos.php
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

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$business_id = $_SESSION['business_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $business_id);
            break;
            
        case 'POST':
            handlePostRequest($db, $business_id);
            break;
            
        case 'PUT':
            handlePutRequest($db, $business_id);
            break;
            
        case 'DELETE':
            handleDeleteRequest($db, $business_id);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en productos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

// ===== FUNCIONES DE MANEJO =====

function handleGetRequest($db, $business_id) {
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
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        echo json_encode(['success' => true, 'data' => $product]);
    } else {
        // Listar productos con filtros
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
        
        // Obtener productos
        $products = $db->fetchAll(
            "SELECT p.*, c.name as category_name,
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
        
        echo json_encode([
            'success' => true,
            'data' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

function handlePostRequest($db, $business_id) {
    // Crear nuevo producto
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }
    
    // Validar campos requeridos
    $required_fields = ['name', 'selling_price'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "El campo '{$field}' es requerido"]);
            return;
        }
    }
    
    // Verificar SKU único si se proporciona
    if (!empty($input['sku'])) {
        $exists = $db->single(
            "SELECT id FROM products WHERE sku = ? AND business_id = ? AND status = 1",
            [$input['sku'], $business_id]
        );
        if ($exists) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El SKU ya está en uso']);
            return;
        }
    }
    
    // Generar SKU automático si no se proporciona
    $sku = !empty($input['sku']) ? $input['sku'] : generateSKU($db, $business_id);
    
    // Preparar datos del producto
    $productData = [
        'business_id' => $business_id,
        'name' => cleanInput($input['name']),
        'sku' => $sku,
        'barcode' => cleanInput($input['barcode'] ?? ''),
        'description' => cleanInput($input['description'] ?? ''),
        'category_id' => !empty($input['category_id']) ? intval($input['category_id']) : null,
        'cost_price' => floatval($input['cost_price'] ?? 0),
        'selling_price' => floatval($input['selling_price']),
        'wholesale_price' => floatval($input['wholesale_price'] ?? 0),
        'stock_quantity' => intval($input['stock_quantity'] ?? 0),
        'min_stock' => intval($input['min_stock'] ?? 0),
        'track_stock' => !empty($input['track_stock']) ? 1 : 0,
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        $productId = $db->insert('products', $productData);
        
        // Registrar movimiento de inventario inicial si hay stock
        if ($productData['stock_quantity'] > 0) {
            $db->insert('inventory_movements', [
                'business_id' => $business_id,
                'product_id' => $productId,
                'movement_type' => 'in',
                'quantity' => $productData['stock_quantity'],
                'reason' => 'Stock inicial',
                'user_id' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        http_response_code(201);
        echo json_encode([
            'success' => true, 
            'message' => 'Producto creado exitosamente',
            'data' => ['id' => $productId]
        ]);
        
    } catch (Exception $e) {
        error_log("Error creando producto: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el producto']);
    }
}

function handlePutRequest($db, $business_id) {
    // Actualizar producto existente
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
        return;
    }
    
    $productId = intval($_GET['id']);
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }
    
    // Verificar que el producto existe y pertenece al negocio
    $existingProduct = $db->single(
        "SELECT * FROM products WHERE id = ? AND business_id = ? AND status = 1",
        [$productId, $business_id]
    );
    
    if (!$existingProduct) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        return;
    }
    
    // Verificar SKU único si se cambió
    if (!empty($input['sku']) && $input['sku'] !== $existingProduct['sku']) {
        $exists = $db->single(
            "SELECT id FROM products WHERE sku = ? AND business_id = ? AND id != ? AND status = 1",
            [$input['sku'], $business_id, $productId]
        );
        if ($exists) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El SKU ya está en uso']);
            return;
        }
    }
    
    // Preparar datos de actualización
    $updateData = [
        'name' => cleanInput($input['name'] ?? $existingProduct['name']),
        'sku' => cleanInput($input['sku'] ?? $existingProduct['sku']),
        'barcode' => cleanInput($input['barcode'] ?? $existingProduct['barcode']),
        'description' => cleanInput($input['description'] ?? $existingProduct['description']),
        'category_id' => !empty($input['category_id']) ? intval($input['category_id']) : $existingProduct['category_id'],
        'cost_price' => isset($input['cost_price']) ? floatval($input['cost_price']) : $existingProduct['cost_price'],
        'selling_price' => isset($input['selling_price']) ? floatval($input['selling_price']) : $existingProduct['selling_price'],
        'wholesale_price' => isset($input['wholesale_price']) ? floatval($input['wholesale_price']) : $existingProduct['wholesale_price'],
        'min_stock' => isset($input['min_stock']) ? intval($input['min_stock']) : $existingProduct['min_stock'],
        'track_stock' => isset($input['track_stock']) ? (!empty($input['track_stock']) ? 1 : 0) : $existingProduct['track_stock'],
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        $db->update('products', $updateData, "id = ? AND business_id = ?", [$productId, $business_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Producto actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando producto: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto']);
    }
}

function handleDeleteRequest($db, $business_id) {
    // Eliminar (marcar como eliminado) producto
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
        return;
    }
    
    $productId = intval($_GET['id']);
    
    // Verificar que el producto existe y pertenece al negocio
    $product = $db->single(
        "SELECT id FROM products WHERE id = ? AND business_id = ? AND status = 1",
        [$productId, $business_id]
    );
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        return;
    }
    
    try {
        // Marcar como eliminado en lugar de borrar físicamente
        $db->update(
            'products', 
            ['status' => 0, 'updated_at' => date('Y-m-d H:i:s')], 
            "id = ? AND business_id = ?", 
            [$productId, $business_id]
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Producto eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error eliminando producto: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el producto']);
    }
}

// ===== FUNCIONES AUXILIARES =====

function generateSKU($db, $business_id) {
    // Generar SKU automático
    $prefix = 'PROD';
    $attempts = 0;
    $maxAttempts = 10;
    
    do {
        $number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $sku = $prefix . $number;
        
        $exists = $db->single(
            "SELECT id FROM products WHERE sku = ? AND business_id = ?",
            [$sku, $business_id]
        );
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($exists) {
        // Si después de varios intentos sigue existiendo, usar timestamp
        $sku = $prefix . time();
    }
    
    return $sku;
}

function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}
?>