<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php'; // Asegúrate de tener una función para verificar autenticación

// Función para enviar respuestas JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

if (!isAuthenticated()) {
    sendJsonResponse(false, 'Acceso denegado', null, 401);
}

$db = getDB();
$business_id = $_SESSION['business_id'];
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handlePostSale($db, $business_id, $user_id);
        break;
    case 'GET':
        // Puedes añadir lógica para obtener ventas si es necesario, por ejemplo para reportes
        sendJsonResponse(false, 'Método GET no implementado para ventas directas.', null, 405);
        break;
    default:
        sendJsonResponse(false, 'Método no permitido', null, 405);
        break;
}

function handlePostSale($db, $business_id, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        sendJsonResponse(false, 'No se encontraron artículos en la venta.', null, 400);
    }

    $customer_id = $input['customer_id'] ?? null;
    $payment_method = $input['payment_method'] ?? 'cash';
    $subtotal = $input['subtotal'] ?? 0; // Este es el subtotal del carrito, sin impuestos
    $tax_amount_total = $input['tax'] ?? 0; // Este es el IGV total del carrito
    $total_amount = $input['total'] ?? 0; // Este es el total final del carrito
    $cash_received = $input['cash_received'] ?? 0;
    $change_amount = $input['change_amount'] ?? 0;
    $include_igv_frontend = $input['includeIgv'] ?? true; // Estado de IGV del frontend

    // Calcular amount_paid y amount_due para la tabla `sales`
    $amount_paid = ($payment_method === 'cash') ? $cash_received : $total_amount;
    $amount_due = max(0, $total_amount - $amount_paid); 

    // Determinar payment_status y status para la tabla `sales`
    $payment_status = ($amount_due > 0) ? 'partial' : 'paid';
    if ($payment_method === 'credit') { 
        $payment_status = 'pending';
    }
    $status = 1; // Para la tabla sales, 1 = activo (asumiendo tu tinyint(1) status)

    try {
        $db->beginTransaction();

        // Generar un número de venta
        $sale_number = 'VTA-' . date('YmdHis') . rand(100, 999); 
        $sale_date = date('Y-m-d H:i:s'); // Fecha y hora actual de la venta

        // Insertar la venta principal en la tabla `sales`
        $stmt = $db->prepare("
            INSERT INTO sales (
                business_id, user_id, customer_id, sale_number, sale_date, 
                subtotal, tax_amount, discount_amount, total_amount, payment_method, 
                payment_status, amount_paid, amount_due, notes, receipt_printed, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $business_id, $user_id, $customer_id, $sale_number, $sale_date,
            $subtotal, $tax_amount_total, 0.00, $total_amount, $payment_method,
            $payment_status, $amount_paid, $amount_due, null, 0, $status
        ]);

        $sale_id = $db->lastInsertId();

        // Insertar los ítems de la venta y actualizar el stock
        foreach ($input['items'] as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $unit_price = $item['price']; // Precio de venta unitario del frontend
            $item_subtotal_base = $item['subtotal']; // Subtotal del ítem (cantidad * precio unitario) antes de impuestos

            // Obtener detalles del producto para product_sku, cost_price, y tax_rate
            $product_details = $db->fetch("SELECT sku, cost_price, tax_rate, track_stock FROM products WHERE id = ?", [$product_id]);
            $product_sku = $product_details['sku'] ?? null;
            $cost_price = $product_details['cost_price'] ?? 0.00;
            $product_tax_rate = $product_details['tax_rate'] ?? 0.00; 
            $track_stock = $product_details['track_stock'] ?? 1;

            // Calcular tax_amount para este ítem y line_total
            $item_tax_amount = 0;
            $line_total = $item_subtotal_base; // Por defecto es el subtotal base

            if ($include_igv_frontend) { // Si el POS tiene el IGV activado
                // Si el precio del item del carrito ya es el precio final (con IGV incluido)
                // y item_subtotal_base es ese precio final, necesitamos recalcular la base
                // y el impuesto individual para cada linea.
                // Asumiendo un IGV del 18% para este cálculo de linea.
                $item_tax_amount = ($item_subtotal_base / (1 + (0.18))) * 0.18;
                $line_total = $item_subtotal_base; // line_total es el total con IGV por ítem
            }


            // Insertar ítem de venta en la tabla `sale_items`
            $stmt_item = $db->prepare("
                INSERT INTO sale_items (
                    sale_id, product_id, product_name, product_sku, quantity, 
                    unit_price, cost_price, discount_amount, tax_rate, tax_amount, line_total, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt_item->execute([
                $sale_id, $product_id, $item['name'], $product_sku, $quantity, 
                $unit_price, $cost_price, 0.00, $product_tax_rate, $item_tax_amount, $line_total
            ]);

            // Actualizar stock del producto solo si track_stock es 1
            if ($track_stock == 1) { 
                $stmt_stock = $db->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ? AND business_id = ?
                ");
                $stmt_stock->execute([$quantity, $product_id, $business_id]);
            }
        }

        // Si la venta proviene de una suspendida, actualizar su estado a 'completed'
        if ($suspended_sale_id) {
            $stmt_update_suspended = $db->prepare("
                UPDATE suspended_sales 
                SET status = 'completed' 
                WHERE id = ? AND business_id = ?
            ");
            $stmt_update_suspended->execute([$suspended_sale_id, $business_id]);
        }

        $db->commit();
        sendJsonResponse(true, 'Venta registrada con éxito.', [
            'sale_id' => $sale_id,
            'sale_number' => $sale_number,
            'total' => number_format($total_amount, 2), 
            'payment_method' => $payment_method,
            'change_amount' => number_format($change_amount, 2)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(false, 'Error al procesar la venta: ' . $e->getMessage(), null, 500);
    }
}