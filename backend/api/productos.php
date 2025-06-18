<?php
/**
 * API DE PRODUCTOS
 * Archivo: backend/api/products.php
 * Maneja todas las operaciones relacionadas con productos
 */

session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            handleGetProducts($db, $business_id);
            break;
            
        case 'get':
            if (isset($_GET['id'])) {
                handleGetProduct($db, $_GET['id'], $business_id);
            }
            break;
            
        case 'update_stock':
            handleUpdateStock($db, $business_id);
            break;
            
        default:
            $method = $_SERVER['REQUEST_METHOD'];
            
            switch ($method) {
                case 'POST':
                    handleCreateProduct($db, $business_id);
                    break;
                    
                case 'PUT':
                    if (isset($_GET['id'])) {
                        handleUpdateProduct($db, $_GET['id'], $business_id);
                    }
                    break;
                    
                case 'DELETE':
                    if (isset($_GET['id'])) {
                        handleDeleteProduct($db, $_GET['id'], $business_id);
                    }
                    break;
                    
                default:
                    handleGetProducts($db, $business_id);
                    break;
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en products.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

/**
 * Obtener lista de productos
 */
function handleGetProducts($db, $business_id) {
    try {
        $products = $db->fetchAll("
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
            WHERE p.business_id = ? AND p.status = 1 
            ORDER BY p.name ASC
        ", [$business_id]);
        
        echo json_encode([
            'success' => true,
            'products' => $products // Corregido: 'product_id' a 'products' para una lista
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo productos: " . $e->getMessage()); // Corregido: mensaje de log
        http_response_code(500); // Cambiado a 500 para errores internos
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos']); // Corregido: mensaje de error
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
            $input['category_id'] ?? null,
            $input['image_url'] ?? null
        ]);
        
        if (!$product_id) {
            throw new Exception('Error al crear el producto');
        }
        
        // Registrar movimiento inicial de inventario si hay stock
        if (($input['stock_quantity'] ?? 0) > 0) {
            $db->insert("
                INSERT INTO inventory_movements (
                    business_id, product_id, movement_type, quantity, 
                    reference_type, reference_id, notes, created_at
                ) VALUES (?, ?, 'in', ?, 'initial_stock', ?, 'Stock inicial', NOW())
            ", [
                $business_id,
                $product_id,
                $input['stock_quantity'],
                $product_id
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'product_id' => $product_id
        ]);
        
    } catch (Exception $e) {
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
            $input['category_id'] ?? null,
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
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE si.product_id = ? AND s.business_id = ? 
            AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", [$product_id, $business_id]);
        
        if ($recent_sales['count'] > 0) {
            throw new Exception('No se puede eliminar un producto con ventas recientes');
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
        
        if (!$product_id || $quantity < 0) {
            throw new Exception('Datos de ajuste de stock inválidos');
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
        
        $old_stock = $product['stock_quantity'];
        $new_stock = $old_stock;
        
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
                $movement_type = $quantity > $old_stock ? 'in' : 'out';
                $movement_quantity = abs($quantity - $old_stock);
                break;
                
            default:
                throw new Exception('Tipo de ajuste inválido');
        }
        
        // Actualizar stock
        $updated = $db->execute("
            UPDATE products 
            SET stock_quantity = ?, updated_at = NOW()
            WHERE id = ? AND business_id = ?
        ", [$new_stock, $product_id, $business_id]);
        
        if (!$updated) {
            throw new Exception('Error al actualizar stock');
        }
        
        // Registrar movimiento de inventario
        if ($movement_quantity > 0) {
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
                $reason ?: "Ajuste de stock: {$type}"
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock actualizado exitosamente',
            'old_stock' => $old_stock,
            'new_stock' => $new_stock
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error actualizando stock: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}