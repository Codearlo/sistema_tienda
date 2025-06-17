<?php
/**
 * API DE PRODUCTOS
 * Archivo: backend/api/productos.php
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

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
            if (isset($_GET['id'])) {
                // Obtener producto específico
                $productId = intval($_GET['id']);
                $product = $db->single(
                    "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     WHERE p.id = ? AND p.business_id = ? AND p.status != ?",
                    [$productId, $business_id, STATUS_DELETED]
                );
                
                if (!$product) {
                    throw new Exception('Producto no encontrado');
                }
                
                echo json_encode(['success' => true, 'data' => $product]);
            } else {
                // Listar productos
                $page = intval($_GET['page'] ?? 1);
                $limit = intval($_GET['limit'] ?? 50);
                $search = cleanInput($_GET['search'] ?? '');
                $category = intval($_GET['category'] ?? 0);
                $offset = ($page - 1) * $limit;
                
                $whereConditions = ["p.business_id = ?", "p.status != ?"];
                $params = [$business_id, STATUS_DELETED];
                
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
                                WHEN p.track_stock = 1 AND p.current_stock <= p.min_stock THEN 1 
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
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Crear nuevo producto
            $input = json_decode(file_get_contents('php://input'), true);
            
            $required_fields = ['name', 'selling_price'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            
            // Verificar SKU único
            if (!empty($input['sku'])) {
                $exists = $db->single(
                    "SELECT id FROM products WHERE sku = ? AND business_id = ?",
                    [$input['sku'], $business_id]
                );
                if ($exists) {
                    throw new Exception('El SKU ya está en uso');
                }
            }
            
            // Crear producto
            $productData = [
                'business_id' => $business_id,
                'name' => cleanInput($input['name']),
                'selling_price' => floatval($input['selling_price']),
                'cost_price' => floatval($input['cost_price'] ?? 0),
                'wholesale_price' => floatval($input['wholesale_price'] ?? 0),
                'category_id' => $input['category_id'] ?? null,
                'sku' => $input['sku'] ?? null,
                'barcode' => $input['barcode'] ?? null,
                'description' => cleanInput($input['description'] ?? ''),
                'min_stock' => intval($input['min_stock'] ?? 0),
                'current_stock' => intval($input['current_stock'] ?? 0),
                'unit' => cleanInput($input['unit'] ?? 'unit'),
                'track_stock' => isset($input['track_stock']) ? 1 : 0,
                'status' => STATUS_ACTIVE,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $productId = $db->insert('products', $productData);
            
            if (!$productId) {
                throw new Exception('Error al crear el producto');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'data' => ['id' => $productId]
            ]);
            break;
            
        case 'PUT':
            // Actualizar producto
            $productId = intval($_GET['id'] ?? 0);
            if (!$productId) {
                throw new Exception('ID de producto no válido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Verificar que el producto existe y pertenece al negocio
            $product = $db->single(
                "SELECT * FROM products WHERE id = ? AND business_id = ?",
                [$productId, $business_id]
            );
            
            if (!$product) {
                throw new Exception('Producto no encontrado');
            }
            
            // Verificar SKU único si cambió
            if (!empty($input['sku']) && $input['sku'] != $product['sku']) {
                $exists = $db->single(
                    "SELECT id FROM products WHERE sku = ? AND business_id = ? AND id != ?",
                    [$input['sku'], $business_id, $productId]
                );
                if ($exists) {
                    throw new Exception('El SKU ya está en uso');
                }
            }
            
            // Actualizar datos
            $updateData = [
                'name' => cleanInput($input['name']),
                'selling_price' => floatval($input['selling_price']),
                'cost_price' => floatval($input['cost_price'] ?? 0),
                'wholesale_price' => floatval($input['wholesale_price'] ?? 0),
                'category_id' => $input['category_id'] ?? null,
                'sku' => $input['sku'] ?? null,
                'barcode' => $input['barcode'] ?? null,
                'description' => cleanInput($input['description'] ?? ''),
                'min_stock' => intval($input['min_stock'] ?? 0),
                'unit' => cleanInput($input['unit'] ?? 'unit'),
                'track_stock' => isset($input['track_stock']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update('products', $updateData, 'id = ?', [$productId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto actualizado exitosamente'
            ]);
            break;
            
        case 'DELETE':
            // Eliminar producto
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
                $message = 'Producto desactivado (tiene ventas asociadas)';
            } else {
                // Hard delete
                $db->delete('products', 'id = ?', [$productId]);
                $message = 'Producto eliminado permanentemente';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>