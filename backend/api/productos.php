<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';
require_once '../config/config.php';

$db = getDB();
$business_id = $_SESSION['business_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Obtener productos con filtros
            $search = cleanInput($_GET['search'] ?? '');
            $category = intval($_GET['category'] ?? 0);
            $stock = cleanInput($_GET['stock'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, intval($_GET['limit'] ?? 20));
            $offset = ($page - 1) * $limit;
            
            // Construir consulta
            $where = "p.business_id = ? AND p.status = 1";
            $params = [$business_id];
            
            if ($search) {
                $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
                $searchParam = "%$search%";
                array_push($params, $searchParam, $searchParam, $searchParam);
            }
            
            if ($category) {
                $where .= " AND p.category_id = ?";
                $params[] = $category;
            }
            
            if ($stock === 'low') {
                $where .= " AND p.stock_quantity <= p.min_stock AND p.stock_quantity > 0";
            } elseif ($stock === 'out') {
                $where .= " AND p.stock_quantity = 0";
            } elseif ($stock === 'normal') {
                $where .= " AND p.stock_quantity > p.min_stock";
            }
            
            // Obtener productos
            $products = $db->fetchAll(
                "SELECT p.*, c.name as category_name, c.color as category_color
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE $where
                 ORDER BY p.name ASC
                 LIMIT $limit OFFSET $offset",
                $params
            );
            
            // Contar total
            $total = $db->single("SELECT COUNT(*) as count FROM products p WHERE $where", $params)['count'];
            
            echo json_encode([
                'success' => true,
                'products' => $products,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            // Crear producto
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar datos requeridos
            if (empty($data['name']) || !isset($data['selling_price'])) {
                throw new Exception('Nombre y precio de venta son requeridos');
            }
            
            // Verificar SKU único
            if (!empty($data['sku'])) {
                $exists = $db->single(
                    "SELECT id FROM products WHERE sku = ? AND business_id = ?",
                    [$data['sku'], $business_id]
                );
                if ($exists) {
                    throw new Exception('El SKU ya está en uso');
                }
            }
            
            // Preparar datos
            $productData = [
                'business_id' => $business_id,
                'category_id' => $data['category_id'] ?? null,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'name' => cleanInput($data['name']),
                'description' => cleanInput($data['description'] ?? ''),
                'cost_price' => floatval($data['cost_price'] ?? 0),
                'selling_price' => floatval($data['selling_price']),
                'wholesale_price' => floatval($data['wholesale_price'] ?? 0),
                'stock_quantity' => intval($data['stock_quantity'] ?? 0),
                'min_stock' => intval($data['min_stock'] ?? 0),
                'unit' => cleanInput($data['unit'] ?? 'unit'),
                'track_stock' => isset($data['track_stock']) ? 1 : 0,
                'status' => STATUS_ACTIVE
            ];
            
            $db->beginTransaction();
            
            // Insertar producto
            $productId = $db->insert('products', $productData);
            
            // Registrar movimiento inicial de inventario
            if ($productData['stock_quantity'] > 0 && $productData['track_stock']) {
                $db->insert('inventory_movements', [
                    'business_id' => $business_id,
                    'product_id' => $productId,
                    'user_id' => $_SESSION['user_id'],
                    'movement_type' => 'in',
                    'quantity' => $productData['stock_quantity'],
                    'reason' => 'Stock inicial',
                    'movement_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'product_id' => $productId
            ]);
            break;
            
        case 'PUT':
            // Actualizar producto
            $productId = intval($_GET['id'] ?? 0);
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verificar que el producto pertenece al negocio
            $product = $db->single(
                "SELECT * FROM products WHERE id = ? AND business_id = ?",
                [$productId, $business_id]
            );
            
            if (!$product) {
                throw new Exception('Producto no encontrado');
            }
            
            // Verificar SKU único si cambió
            if (!empty($data['sku']) && $data['sku'] != $product['sku']) {
                $exists = $db->single(
                    "SELECT id FROM products WHERE sku = ? AND business_id = ? AND id != ?",
                    [$data['sku'], $business_id, $productId]
                );
                if ($exists) {
                    throw new Exception('El SKU ya está en uso');
                }
            }
            
            // Actualizar datos
            $updateData = [
                'name' => cleanInput($data['name']),
                'selling_price' => floatval($data['selling_price']),
                'cost_price' => floatval($data['cost_price'] ?? 0),
                'wholesale_price' => floatval($data['wholesale_price'] ?? 0),
                'category_id' => $data['category_id'] ?? null,
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'description' => cleanInput($data['description'] ?? ''),
                'min_stock' => intval($data['min_stock'] ?? 0),
                'unit' => cleanInput($data['unit'] ?? 'unit'),
                'track_stock' => isset($data['track_stock']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('products', $updateData, 'id = ?', [$productId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto actualizado exitosamente'
            ]);
            break;
            
        case 'DELETE':
            // Eliminar producto (soft delete)
            $productId = intval($_GET['id'] ?? 0);
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            
            // Verificar que el producto pertenece al negocio
            $product = $db->single(
                "SELECT id FROM products WHERE id = ? AND business_id = ?",
                [$productId, $business_id]
            );
            
            if (!$product) {
                throw new Exception('Producto no encontrado');
            }
            
            // Verificar si tiene ventas asociadas
            $hasSales = $db->single(
                "SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?",
                [$productId]
            )['count'];
            
            if ($hasSales > 0) {
                // Soft delete
                $db->update('products', ['status' => STATUS_DELETED], 'id = ?', [$productId]);
            } else {
                // Hard delete si no tiene ventas
                $db->delete('products', 'id = ?', [$productId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ]);
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>