<?php
session_start();
require_once '../config/database.php';
require_once '../../includes/auth.php'; // ✅ CORRECCIÓN: Ruta correcta desde backend/api/ hacia includes/

// Función para enviar respuestas JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Usar la función del includes/auth.php existente
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
        // Puedes añadir lógica para obtener ventas si es necesario
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

    // Calcular amount_paid y amount_due
    $amount_paid = ($payment_method === 'cash') ? $cash_received : $total_amount;
    $amount_due = max(0, $total_amount - $amount_paid);

    // Determinar payment_status
    $payment_status = ($amount_due > 0) ? 'partial' : 'paid';
    if ($payment_method === 'credit') {
        $payment_status = 'pending';
    }

    try {
        $db->beginTransaction();

        // Generar número de venta
        $sale_number = 'VTA-' . date('YmdHis') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        // Insertar venta principal
        $saleData = [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'customer_id' => $customer_id,
            'sale_number' => $sale_number,
            'sale_date' => date('Y-m-d'),
            'subtotal_amount' => $subtotal,
            'tax_amount' => $tax_amount_total,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'amount_paid' => $amount_paid,
            'amount_due' => $amount_due,
            'cash_received' => ($payment_method === 'cash') ? $cash_received : 0,
            'change_amount' => ($payment_method === 'cash') ? $change_amount : 0,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $sale_id = $db->insert('sales', $saleData);

        // Insertar items de la venta
        foreach ($input['items'] as $item) {
            $itemData = [
                'sale_id' => $sale_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'tax_amount' => $item['subtotal'] * 0.18,
                'total_amount' => $item['subtotal'] * 1.18,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $db->insert('sale_items', $itemData);

            // Actualizar stock del producto
            $db->execute(
                "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND business_id = ?",
                [$item['quantity'], $item['product_id'], $business_id]
            );
        }

        $db->commit();

        // Respuesta exitosa
        sendJsonResponse(true, 'Venta creada exitosamente', [
            'sale_id' => $sale_id,
            'sale_number' => $sale_number,
            'total' => $total_amount,
            'payment_method' => $payment_method,
            'change_amount' => $change_amount
        ], 201);

    } catch (Exception $e) {
        $db->rollback();
        error_log("Error creando venta: " . $e->getMessage());
        sendJsonResponse(false, 'Error al procesar la venta: ' . $e->getMessage(), null, 500);
    }
}
?>