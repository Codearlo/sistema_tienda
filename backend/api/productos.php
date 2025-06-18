<?php
/**
 * API DE PRODUCTOS
 * Archivo: backend/api/products.php
 * Maneja todas las operaciones relacionadas con productos
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// ===== CONFIGURACIÓN DE HEADERS =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== VERIFICACIÓN DE AUTENTICACIÓN =====
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// ===== ROUTER PRINCIPAL =====
try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Enrutamiento por acción específica
    switch ($action) {
        case 'list':
            handleGetProducts($db, $business_id);
            break;
            
        case 'get':
            if (isset($_GET['id'])) {
                handleGetProduct($db, $_GET['id'], $business_id);
            } else {
                throw new Exception('ID de producto requerido');
            }
            break;
            
        case 'update_stock':
            handleUpdateStock($db, $business_id);
            break;
            
        default:
            // Enrutamiento por método HTTP
            switch ($method) {
                case 'GET':
                    handleGetProducts($db, $business_id);
                    break;
                    
                case 'POST':
                    handleCreateProduct($db, $business_id);
                    break;
                    
                case 'PUT':
                    if (isset($_GET['id'])) {
                        handleUpdateProduct($db, $_GET['id'], $business_id);
                    } else {
                        throw new Exception('ID de producto requerido para actualizar');
                    }
                    break;
                    
                case 'DELETE':
                    if (isset($_GET['id'])) {
                        handleDeleteProduct($db, $_GET['id'], $business_id);
                    } else {
                        throw new Exception('ID de producto requerido para eliminar');
                    }
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                    break;
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en products.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ===== FUNCIONES DE MANEJO =====

/**
 * Obtener lista de productos
 */
function handleGetProducts($db, $business_id) {
    try {
        // Parámetros de filtrado
        $category_id = $_GET['category_id'] ?? null;
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 1;
        $low_stock = $_GET['low_stock'] ?? false;
        
        // Construir query base
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.description,
                p.barcode,
                p.selling_price,
                p.purchase_price,
                p.stock_quantity,
                p.min_stock,
                p.unit,
                p.category_id,
                p.image_url,
                p.status,
                p.created_at,
                p.updated_at,
                c.name as category_name
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.business_id = ?
        ";
        
        $params = [$business_id];
        
        // Aplicar filtros
        if ($status !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($category_id) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        if ($search) {
            $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($low_stock) {
            $sql .= " AND p.stock_quantity <= p.min_stock";
        }
        
        $sql .= " ORDER BY p.name ASC";
        
        $products = $db->fetchAll($sql, $params);
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'count' => count($products)
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo productos: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
    }
}

/**
 * Obtener un producto específico
 */
function handleGetProduct($db, $product_id, $business_id) {
    try {
        $product = $db->fetch("
            SELECT 
                p.*,
                c.name as category_name
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.business_id = ?
        ", [$product_id, $business_id]);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        // Obtener historial de movimientos de inventario
        $movements = $db->fetchAll("
            SELECT 
                movement_type,
                quantity,
                reference_type,
                reference_id,
                notes,
                created_at
            FROM inventory_movements 
            WHERE product_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ", [$product_id]);
        
        $product['movements'] = $movements;
        
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo producto: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener producto']);
    }
}

/**
 * Crear nuevo producto
 */
function handleCreateProduct($db, $business_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        if (empty($input['name'])) {
            throw new Exception('El nombre del producto es requerido');
        }
        
        if (!isset($input['selling_price']) || $input['selling_price'] <= 0) {
            throw new Exception('El precio de venta debe ser mayor a 0');
        }
        
        // Verificar que el código de barras no exista (si se proporciona)
        if (!empty($input['barcode'])) {
            $existing = $db->fetch("
                SELECT id FROM products 
                WHERE barcode = ? AND business_id = ? AND status = 1
            ", [$input['barcode'], $business_id]);
            
            if ($existing) {
                throw new Exception('Ya existe un producto con ese código de barras');
            }
        }
        
        $db->beginTransaction();
        
        // Crear producto
        $product_id = $db->insert("
            INSERT INTO products (
                business_id, name, description, barcode, selling_price, 
                purchase_price, stock_quantity, min_stock, unit, 
                category_id, image_url, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ", [
            $business_id,
            $input['name'],
            $input['description'] ?? '',
            $input['barcode'] ?? null,
            $input['selling_price'],
            $input['purchase_price'] ?? 0,
            $input['stock_quantity'] ?? 0,
            $input['min_stock'] ?? 5,
            $input['unit'] ?? 'unidades',
            !empty($input['category_id']) ? $input['category_id'] : null,
            $input['image_url'] ?? null
        ]);
        
        if (!$product_id) {
            throw new Exception('Error al crear el producto');
        }
        
        // Registrar movimiento inicial de inventario si hay stock
        $initial_stock = intval($input['stock_quantity'] ?? 0);
        if ($initial_stock > 0) {
            $db->insert("
                INSERT INTO inventory_movements (
                    business_id, product_id, movement_type, quantity, 
                    reference_type, reference_id, notes, created_at
                ) VALUES (?, ?, 'in', ?, 'initial_stock', ?, 'Stock inicial', NOW())
            ", [
                $business_id,
                $product_id,
                $initial_stock,
                $product_id
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'product_id' => $product_id
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Error creando producto: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Actualizar producto existente
 */
function handleUpdateProduct($db, $product_id, $business_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verificar que el producto existe
        $product = $db->fetch("
            SELECT * FROM products 
            WHERE id = ? AND business_id = ?
        ", [$product_id, $business_id]);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        // Validar datos
        if (empty($input['name'])) {
            throw new Exception('El nombre del producto es requerido');
        }
        
        if (!isset($input['selling_price']) || $input['selling_price'] <= 0) {
            throw new Exception('El precio de venta debe ser mayor a 0');
        }
        
        // Verificar código de barras único (excluyendo el producto actual)
        if (!empty($input['barcode'])) {
            $existing = $db->fetch("
                SELECT id FROM products 
                WHERE barcode = ? AND business_id = ? AND id != ? AND status = 1
            ", [$input['barcode'], $business_id, $product_id]);
            
            if ($existing) {
                throw new Exception('Ya existe otro producto con ese código de barras');
            }
        }
        
        // Actualizar producto (sin tocar stock_quantity)
        $updated = $db->execute("
            UPDATE products SET
                name = ?,
                description = ?,
                barcode = ?,
                selling_price = ?,
                purchase_price = ?,
                min_stock = ?,
                unit = ?,
                category_id = ?,
                image_url = ?,
                updated_at = NOW()
            WHERE id = ? AND business_id = ?
        ", [
            $input['name'],
            $input['description'] ?? '',
            $input['barcode'] ?? null,
            $input['selling_price'],
            $input['purchase_price'] ?? 0,
            $input['min_stock'] ?? 5,
            $input['unit'] ?? 'unidades',
            !empty($input['category_id']) ? $input['category_id'] : null,
            $input['image_url'] ?? null,
            $product_id,
            $business_id
        ]);
        
        if (!$updated) {
            throw new Exception('Error al actualizar el producto');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando producto: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Eliminar producto (soft delete)
 */
function handleDeleteProduct($db, $product_id, $business_id) {
    try {
        // Verificar que el producto existe
        $product = $db->fetch("
            SELECT * FROM products 
            WHERE id = ? AND business_id = ?
        ", [$product_id, $business_id]);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        // Verificar que no hay ventas recientes con este producto
        $recent_sales = $db->fetch("
            SELECT COUNT(*) as count 
            FROM sale_details sd
            JOIN sales s ON sd.sale_id = s.id
            WHERE sd.product_id = ? AND s.business_id = ? 
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND s.status = 1
        ", [$product_id, $business_id]);
        
        if ($recent_sales['count'] > 0) {
            throw new Exception('No se puede eliminar un producto con ventas recientes (últimos 30 días)');
        }
        
        // Soft delete
        $deleted = $db->execute("
            UPDATE products 
            SET status = 0, updated_at = NOW()
            WHERE id = ? AND business_id = ?
        ", [$product_id, $business_id]);
        
        if (!$deleted) {
            throw new Exception('Error al eliminar el producto');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error eliminando producto: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Actualizar stock de producto
 */
function handleUpdateStock($db, $business_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $product_id = $input['product_id'] ?? null;
        $type = $input['type'] ?? 'add'; // add, remove, set
        $quantity = intval($input['quantity'] ?? 0);
        $reason = $input['reason'] ?? '';
        
        // Validaciones
        if (!$product_id || $quantity < 0) {
            throw new Exception('Datos de ajuste de stock inválidos');
        }
        
        if (!in_array($type, ['add', 'remove', 'set'])) {
            throw new Exception('Tipo de ajuste inválido');
        }
        
        // Verificar que el producto existe
        $product = $db->fetch("
            SELECT * FROM products 
            WHERE id = ? AND business_id = ? AND status = 1
        ", [$product_id, $business_id]);
        
        if (!$product) {
            throw new Exception('Producto no encontrado');
        }
        
        $db->beginTransaction();
        
        $old_stock = intval($product['stock_quantity']);
        $new_stock = $old_stock;
        $movement_quantity = 0;
        $movement_type = 'in';
        
        // Calcular nuevo stock según el tipo de ajuste
        switch ($type) {
            case 'add':
                $new_stock = $old_stock + $quantity;
                $movement_type = 'in';
                $movement_quantity = $quantity;
                break;
                
            case 'remove':
                $new_stock = max(0, $old_stock - $quantity);
                $movement_type = 'out';
                $movement_quantity = min($quantity, $old_stock);
                break;
                
            case 'set':
                $new_stock = $quantity;
                if ($quantity > $old_stock) {
                    $movement_type = 'in';
                    $movement_quantity = $quantity - $old_stock;
                } else {
                    $movement_type = 'out';
                    $movement_quantity = $old_stock - $quantity;
                }
                break;
        }
        
        // Actualizar stock en la base de datos
        $updated = $db->execute("
            UPDATE products 
            SET stock_quantity = ?, updated_at = NOW()
            WHERE id = ? AND business_id = ?
        ", [$new_stock, $product_id, $business_id]);
        
        if (!$updated) {
            throw new Exception('Error al actualizar stock');
        }
        
        // Registrar movimiento de inventario si hay cambio
        if ($movement_quantity > 0) {
            $movement_notes = $reason ?: "Ajuste de stock: {$type}";
            
            $db->insert("
                INSERT INTO inventory_movements (
                    business_id, product_id, movement_type, quantity, 
                    reference_type, reference_id, notes, created_at
                ) VALUES (?, ?, ?, ?, 'adjustment', ?, ?, NOW())
            ", [
                $business_id,
                $product_id,
                $movement_type,
                $movement_quantity,
                $product_id,
                $movement_notes
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock actualizado exitosamente',
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'adjustment_type' => $type,
            'quantity_changed' => $movement_quantity
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Error actualizando stock: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>