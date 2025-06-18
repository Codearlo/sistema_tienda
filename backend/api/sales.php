<?php
/**
 * API DE VENTAS
 * Archivo: backend/api/sales.php
 * Maneja todas las operaciones relacionadas con ventas
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
    $user_id = $_SESSION['user_id'];
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            handleCreateSale($db, $business_id, $user_id);
            break;
            
        case 'GET':
            if (isset($_GET['id'])) {
                handleGetSale($db, $_GET['id'], $business_id);
            } else {
                handleGetSales($db, $business_id);
            }
            break;
            
        case 'PUT':
            if (isset($_GET['id'])) {
                handleUpdateSale($db, $_GET['id'], $business_id);
            }
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                handleDeleteSale($db, $_GET['id'], $business_id);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en sales.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

/**
 * Crear nueva venta
 */
function handleCreateSale($db, $business_id, $user_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos de entrada
        if (!$input || !isset($input['items']) || empty($input['items'])) {
            throw new Exception('Datos de venta inválidos');
        }
        
        $items = $input['items'];
        $payment_method = $input['payment_method'] ?? 'cash';
        $total = floatval($input['total'] ?? 0);
        $cash_received = floatval($input['cash_received'] ?? 0);
        $change = floatval($input['change'] ?? 0);
        $customer_id = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
        $notes = $input['notes'] ?? '';
        
        // Validar total
        if ($total <= 0) {
            throw new Exception('El total de la venta debe ser mayor a 0');
        }
        
        // Validar método de pago efectivo
        if ($payment_method === 'cash' && $cash_received < $total) {
            throw new Exception('El monto recibido es insuficiente');
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Verificar stock de todos los productos antes de procesar
        foreach ($items as $item) {
            $product = $db->fetch("
                SELECT id, name, stock_quantity, selling_price 
                FROM products 
                WHERE id = ? AND business_id = ? AND status = 1
            ", [$item['product_id'], $business_id]);
            
            if (!$product) {
                throw new Exception("Producto no encontrado: " . $item['product_id']);
            }
            
            if ($product['stock_quantity'] < $item['quantity']) {
                throw new Exception("Stock insuficiente para: " . $product['name']);
            }
            
            // Validar precio (tolerancia de 0.01 para errores de redondeo)
            if (abs($product['selling_price'] - $item['price']) > 0.01) {
                throw new Exception("Precio incorrecto para: " . $product['name']);
            }
        }
        
        // Crear registro de venta
        $sale_id = $db->insert("
            INSERT INTO sales (
                business_id, user_id, customer_id, total, subtotal, tax, 
                payment_method, cash_received, change_amount, notes, 
                sale_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ", [
            $business_id,
            $user_id,
            $customer_id,
            $total,
            $total, // Subtotal igual al total por ahora
            0, // Sin impuestos por ahora
            $payment_method,
            $cash_received,
            $change,
            $notes
        ]);
        
        if (!$sale_id) {
            throw new Exception('Error al crear la venta');
        }
        
        // Procesar items de la venta
        foreach ($items as $item) {
            // Insertar detalle de venta
            $detail_id = $db->insert("
                INSERT INTO sale_details (
                    sale_id, product_id, quantity, unit_price, subtotal
                ) VALUES (?, ?, ?, ?, ?)
            ", [
                $sale_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            ]);
            
            if (!$detail_id) {
                throw new Exception('Error al insertar detalle de venta');
            }
            
            // Actualizar stock del producto
            $updated = $db->execute("
                UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND business_id = ?
            ", [
                $item['quantity'],
                $item['product_id'],
                $business_id
            ]);
            
            if (!$updated) {
                throw new Exception('Error al actualizar stock');
            }
            
            // Registrar movimiento de inventario
            $db->insert("
                INSERT INTO inventory_movements (
                    business_id, product_id, movement_type, quantity, 
                    reference_type, reference_id, notes, created_at
                ) VALUES (?, ?, 'out', ?, 'sale', ?, ?, NOW())
            ", [
                $business_id,
                $item['product_id'],
                $item['quantity'],
                $sale_id,
                "Venta #" . $sale_id
            ]);
        }
        
        // Confirmar transacción
        $db->commit();
        
        // Obtener datos completos de la venta creada
        $sale = $db->fetch("
            SELECT s.*, 
                   u.name as user_name,
                   CONCAT(c.first_name, ' ', c.last_name) as customer_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.business_id = ?
        ", [$sale_id, $business_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Venta creada exitosamente',
            'sale' => $sale
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error creando venta: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener ventas
 */
function handleGetSales($db, $business_id) {
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $_GET['date_to'] ?? date('Y-m-d');
        
        // Construir query con filtros
        $where_conditions = ["s.business_id = ?"];
        $params = [$business_id];
        
        if ($date_from) {
            $where_conditions[] = "DATE(s.sale_date) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = "DATE(s.sale_date) <= ?";
            $params[] = $date_to;
        }
        
        if (!empty($_GET['payment_method'])) {
            $where_conditions[] = "s.payment_method = ?";
            $params[] = $_GET['payment_method'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Obtener ventas
        $sales = $db->fetchAll("
            SELECT s.*, 
                   u.name as user_name,
                   CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                   COUNT(sd.id) as items_count
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN sale_details sd ON s.id = sd.sale_id
            WHERE {$where_clause}
            GROUP BY s.id
            ORDER BY s.sale_date DESC
            LIMIT ? OFFSET ?
        ", array_merge($params, [$limit, $offset]));
        
        // Obtener total de registros para paginación
        $total = $db->fetchColumn("
            SELECT COUNT(DISTINCT s.id)
            FROM sales s
            WHERE {$where_clause}
        ", $params);
        
        echo json_encode([
            'success' => true,
            'sales' => $sales,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo ventas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener ventas']);
    }
}

/**
 * Obtener una venta específica
 */
function handleGetSale($db, $sale_id, $business_id) {
    try {
        $sale = $db->fetch("
            SELECT s.*, 
                   u.name as user_name,
                   CONCAT(c.first_name, ' ', c.last_name) as customer_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.business_id = ?
        ", [$sale_id, $business_id]);
        
        if (!$sale) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
            return;
        }
        
        // Obtener detalles de la venta
        $details = $db->fetchAll("
            SELECT sd.*, p.name as product_name, p.unit
            FROM sale_details sd
            LEFT JOIN products p ON sd.product_id = p.id
            WHERE sd.sale_id = ?
            ORDER BY sd.id
        ", [$sale_id]);
        
        $sale['details'] = $details;
        
        echo json_encode([
            'success' => true,
            'sale' => $sale
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo venta: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener venta']);
    }
}

/**
 * Anular venta
 */
function handleDeleteSale($db, $sale_id, $business_id) {
    try {
        // Verificar que la venta existe y pertenece al negocio
        $sale = $db->fetch("
            SELECT * FROM sales 
            WHERE id = ? AND business_id = ? AND status = 1
        ", [$sale_id, $business_id]);
        
        if (!$sale) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
            return;
        }
        
        // Verificar que la venta sea del día actual (política de negocio)
        if (date('Y-m-d', strtotime($sale['sale_date'])) !== date('Y-m-d')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Solo se pueden anular ventas del día actual']);
            return;
        }
        
        $db->beginTransaction();
        
        // Obtener detalles de la venta
        $details = $db->fetchAll("
            SELECT * FROM sale_details WHERE sale_id = ?
        ", [$sale_id]);
        
        // Restaurar stock de productos
        foreach ($details as $detail) {
            $db->execute("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ?
            ", [$detail['quantity'], $detail['product_id']]);
            
            // Registrar movimiento de inventario de devolución
            $db->insert("
                INSERT INTO inventory_movements (
                    business_id, product_id, movement_type, quantity, 
                    reference_type, reference_id, notes, created_at
                ) VALUES (?, ?, 'in', ?, 'sale_cancellation', ?, ?, NOW())
            ", [
                $business_id,
                $detail['product_id'],
                $detail['quantity'],
                $sale_id,
                "Anulación de venta #" . $sale_id
            ]);
        }
        
        // Marcar venta como anulada
        $db->execute("
            UPDATE sales 
            SET status = 0, 
                notes = CONCAT(COALESCE(notes, ''), ' [ANULADA: ', NOW(), ']')
            WHERE id = ?
        ", [$sale_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Venta anulada exitosamente'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error anulando venta: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al anular venta']);
    }
}

/**
 * Actualizar venta (por ejemplo, agregar notas)
 */
function handleUpdateSale($db, $sale_id, $business_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verificar que la venta existe
        $sale = $db->fetch("
            SELECT * FROM sales 
            WHERE id = ? AND business_id = ?
        ", [$sale_id, $business_id]);
        
        if (!$sale) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Venta no encontrada']);
            return;
        }
        
        $notes = $input['notes'] ?? $sale['notes'];
        
        $db->execute("
            UPDATE sales 
            SET notes = ?
            WHERE id = ?
        ", [$notes, $sale_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Venta actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando venta: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar venta']);
    }
}
?>