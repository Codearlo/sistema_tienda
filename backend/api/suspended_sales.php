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
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handlePostSuspendedSale($db, $business_id);
        break;
    case 'GET':
        handleGetSuspendedSale($db, $business_id);
        break;
    case 'DELETE':
        handleDeleteSuspendedSale($db, $business_id);
        break;
    default:
        sendJsonResponse(false, 'Método no permitido', null, 405);
        break;
}

function handlePostSuspendedSale($db, $business_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        sendJsonResponse(false, 'No se encontraron artículos en la venta suspendida.', null, 400);
    }

    $customer_id = $input['customer_id'] ?? null;
    $subtotal = $input['subtotal'] ?? 0;
    $tax = $input['tax'] ?? 0;
    $total = $input['total'] ?? 0;
    $include_igv = $input['includeIgv'] ?? 1; // Capturar el estado de includeIgv del frontend

    try {
        $db->beginTransaction();

        // Generar un número de venta suspendida (puedes usar un contador o UUID)
        $sale_number = 'SUSP-' . uniqid(); 

        $stmt = $db->prepare("
            INSERT INTO suspended_sales (
                business_id, sale_number, customer_id, subtotal, tax, total, include_igv, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        $stmt->execute([
            $business_id, $sale_number, $customer_id, $subtotal, $tax, $total, $include_igv
        ]);

        $suspended_sale_id = $db->lastInsertId();

        foreach ($input['items'] as $item) {
            $stmt = $db->prepare("
                INSERT INTO suspended_sale_items (
                    suspended_sale_id, product_id, product_name, quantity, price, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $suspended_sale_id, $item['product_id'], $item['name'], $item['quantity'], $item['price'], $item['subtotal']
            ]);
        }

        $db->commit();
        sendJsonResponse(true, 'Venta suspendida creada con éxito.', [
            'id' => $suspended_sale_id, 
            'sale_number' => $sale_number, 
            'total' => $total, 
            'created_at' => date('Y-m-d H:i:s'),
            'customer_id' => $customer_id, // Incluir customer_id para renderizar en frontend
            'include_igv' => $include_igv // Incluir include_igv para reanudar
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(false, 'Error al suspender la venta: ' . $e->getMessage(), null, 500);
    }
}

function handleGetSuspendedSale($db, $business_id) {
    $sale_id = $_GET['id'] ?? null;

    if (!$sale_id) {
        sendJsonResponse(false, 'ID de venta suspendida no proporcionado.', null, 400);
    }

    try {
        $sale = $db->fetch("
            SELECT ss.*, c.first_name, c.last_name 
            FROM suspended_sales ss
            LEFT JOIN customers c ON ss.customer_id = c.id
            WHERE ss.id = ? AND ss.business_id = ? AND ss.status = 'active'
        ", [$sale_id, $business_id]);

        if (!$sale) {
            sendJsonResponse(false, 'Venta suspendida no encontrada.', null, 404);
        }

        $items = $db->fetchAll("
            SELECT * FROM suspended_sale_items 
            WHERE suspended_sale_id = ?
        ", [$sale_id]);

        $sale['items'] = $items;
        // Concatenar nombre de cliente si existe
        $sale['customer_name'] = ($sale['first_name'] || $sale['last_name']) ? trim($sale['first_name'] . ' ' . $sale['last_name']) : 'Cliente General'; 

        sendJsonResponse(true, 'Venta suspendida obtenida.', $sale);

    } catch (Exception $e) {
        sendJsonResponse(false, 'Error al obtener la venta suspendida: ' . $e->getMessage(), null, 500);
    }
}

function handleDeleteSuspendedSale($db, $business_id) {
    $sale_id = $_GET['id'] ?? null;

    if (!$sale_id) {
        sendJsonResponse(false, 'ID de venta suspendida no proporcionado.', null, 400);
    }

    try {
        $db->beginTransaction();

        // Eliminar los ítems de la venta suspendida
        $stmt_items = $db->prepare("
            DELETE FROM suspended_sale_items 
            WHERE suspended_sale_id = ?
        ");
        $stmt_items->execute([$sale_id]);

        // Eliminar la venta suspendida en sí
        $stmt_sale = $db->prepare("
            DELETE FROM suspended_sales 
            WHERE id = ? AND business_id = ?
        ");
        $stmt_sale->execute([$sale_id, $business_id]);

        $db->commit();
        sendJsonResponse(true, 'Venta suspendida eliminada exitosamente.');

    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(false, 'Error al eliminar la venta suspendida: ' . $e->getMessage(), null, 500);
    }
}