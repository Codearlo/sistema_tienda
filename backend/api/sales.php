<?php
session_start();
require_once '../config/database.php';

// Función para enviar respuestas JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Verificar autenticación simple
if (!isset($_SESSION['user_id'])) {
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
    $subtotal = $input['subtotal'] ?? 0;
    $tax_amount_total = $input['tax'] ?? 0;
    $total_amount = $input['total'] ?? 0;
    $cash_received = $input['cash_received'] ?? 0;
    $change_amount = $input['change_amount'] ?? 0;
    $include_igv_frontend = $input['includeIgv'] ?? true;

    $amount_paid = ($payment_method === 'cash') ? $cash_received : $total_amount;
    $amount_due = max(0, $total_amount - $amount_paid); 

    $payment_status = ($amount_due > 0) ? 'partial' : 'paid';
    if ($payment_method === 'credit') { 
        $payment_status = 'pending';
    }
    $status = 1;

    try {
        $db->beginTransaction();

        $sale_number = 'VTA-' . date('YmdHis') . rand(100, 999); 
        $sale_date = date('Y-m-d H:i:s');

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

        foreach ($input['items'] as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $unit_price = $item['price'];
            $item_subtotal_base = $item['subtotal'];

            $product_details = $db->fetch("SELECT sku, cost_price, tax_rate, track_stock FROM products WHERE id = ?", [$product_id]);
            $product_sku = $product_details['sku'] ?? null;
            $cost_price = $product_details['cost_price'] ?? 0.00;
            $product_tax_rate = $product_details['tax_rate'] ?? 0.00; 
            $track_stock = $product_details['track_stock'] ?? 1;

            $item_tax_amount = 0;
            $line_total = $item_subtotal_base;

            if ($include_igv_frontend) {
                $item_tax_amount = ($item_subtotal_base / (1 + (0.18))) * 0.18;
                $line_total = $item_subtotal_base;
            }

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

            if ($track_stock == 1) { 
                $stmt_stock = $db->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ? AND business_id = ?
                ");
                $stmt_stock->execute([$quantity, $product_id, $business_id]);
            }
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