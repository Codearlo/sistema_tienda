<?php
/**
 * API DE VENTAS - Con métodos correctos de Database class
 * Archivo: backend/api/sales.php
 */

session_start();
require_once '../config/database.php';
require_once '../../includes/auth.php';

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para enviar respuestas JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Verificar autenticación
if (!isAuthenticated()) {
    sendJsonResponse(false, 'Acceso denegado', null, 401);
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $user_id = $_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'POST':
            handlePostSale($db, $business_id, $user_id);
            break;
        case 'GET':
            handleGetSales($db, $business_id);
            break;
        default:
            sendJsonResponse(false, 'Método no permitido', null, 405);
            break;
    }

} catch (Exception $e) {
    error_log("Error en sales.php: " . $e->getMessage());
    sendJsonResponse(false, 'Error interno del servidor: ' . $e->getMessage(), null, 500);
}

function handlePostSale($db, $business_id, $user_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendJsonResponse(false, 'Datos JSON inválidos', null, 400);
        }

        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            sendJsonResponse(false, 'No se encontraron artículos en la venta', null, 400);
        }

        // Extraer datos del input
        $customer_id = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
        $payment_method = $input['payment_method'] ?? 'cash';
        $subtotal = floatval($input['subtotal'] ?? 0);
        $tax_amount = floatval($input['tax'] ?? 0);
        $total_amount = floatval($input['total'] ?? 0);
        $cash_received = floatval($input['cash_received'] ?? 0);
        $change_amount = floatval($input['change_amount'] ?? 0);

        // Validaciones básicas
        if ($total_amount <= 0) {
            sendJsonResponse(false, 'El total de la venta debe ser mayor a 0', null, 400);
        }

        if ($payment_method === 'cash' && $cash_received < $total_amount) {
            sendJsonResponse(false, 'El monto recibido es insuficiente', null, 400);
        }

        // Comenzar transacción
        $db->beginTransaction();

        // Generar número de venta
        $sale_number = 'VTA-' . date('YmdHis') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $sale_date = date('Y-m-d H:i:s');

        // Datos para la tabla sales (usando nombres correctos de campos)
        $saleData = [
            'business_id' => $business_id,
            'customer_id' => $customer_id,
            'user_id' => $user_id,
            'sale_number' => $sale_number,
            'sale_date' => $sale_date,
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'discount_amount' => 0.00,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'payment_status' => 'paid',
            'amount_paid' => $payment_method === 'cash' ? $cash_received : $total_amount,
            'amount_due' => 0.00,
            'notes' => null,
            'receipt_printed' => 0,
            'status' => 1,
            'created_at' => $sale_date,
            'updated_at' => $sale_date
        ];

        // ✅ USAR método insert() de tu clase Database
        $sale_id = $db->insert('sales', $saleData);

        if (!$sale_id) {
            throw new Exception('Error al crear la venta principal');
        }

        // Procesar items de la venta
        foreach ($input['items'] as $item) {
            $product_id = intval($item['product_id']);
            $quantity = floatval($item['quantity']);
            $unit_price = floatval($item['price']);
            $item_subtotal = floatval($item['subtotal']);

            // Validar el item
            if ($product_id <= 0 || $quantity <= 0 || $unit_price <= 0) {
                throw new Exception('Datos de producto inválidos');
            }

            // ✅ USAR método single() de tu clase Database
            $product = $db->single(
                "SELECT name, sku, cost_price FROM products WHERE id = ? AND business_id = ?",
                [$product_id, $business_id]
            );

            if (!$product) {
                throw new Exception("Producto con ID {$product_id} no encontrado");
            }

            // Calcular impuesto y total del item
            $item_tax_rate = 18.00;
            $item_tax_amount = ($item_subtotal * $item_tax_rate) / 100;
            $line_total = $item_subtotal + $item_tax_amount;

            // Datos del item de venta
            $itemData = [
                'sale_id' => $sale_id,
                'product_id' => $product_id,
                'product_name' => $product['name'],
                'product_sku' => $product['sku'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'cost_price' => floatval($product['cost_price'] ?? 0),
                'discount_amount' => 0.00,
                'tax_rate' => $item_tax_rate,
                'tax_amount' => $item_tax_amount,
                'line_total' => $line_total,
                'created_at' => $sale_date
            ];

            // ✅ USAR método insert() de tu clase Database
            $item_id = $db->insert('sale_items', $itemData);

            if (!$item_id) {
                throw new Exception('Error al insertar item de venta');
            }

            // ✅ USAR método query() en lugar de execute() que no existe
            $updateResult = $db->query(
                "UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = ? WHERE id = ? AND business_id = ?",
                [$quantity, $sale_date, $product_id, $business_id]
            );

            if (!$updateResult) {
                throw new Exception("Error al actualizar stock del producto {$product_id}");
            }
        }

        // ✅ USAR método commit() de tu clase Database
        $db->commit();

        // Respuesta exitosa
        sendJsonResponse(true, 'Venta registrada exitosamente', [
            'sale_id' => $sale_id,
            'sale_number' => $sale_number,
            'total' => $total_amount,
            'payment_method' => $payment_method,
            'change_amount' => $change_amount
        ], 201);

    } catch (Exception $e) {
        // ✅ USAR método rollBack() (con B mayúscula) de tu clase Database
        try {
            $db->rollBack();
        } catch (Exception $rollbackError) {
            error_log("Error en rollback: " . $rollbackError->getMessage());
        }
        
        error_log("Error procesando venta: " . $e->getMessage());
        sendJsonResponse(false, 'Error al procesar la venta: ' . $e->getMessage(), null, 500);
    }
}

function handleGetSales($db, $business_id) {
    try {
        // Parámetros de paginación
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Filtros opcionales
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        $whereConditions = ["s.business_id = ?"];
        $whereParams = [$business_id];

        if ($dateFrom) {
            $whereConditions[] = "DATE(s.created_at) >= ?";
            $whereParams[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "DATE(s.created_at) <= ?";
            $whereParams[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // ✅ USAR método fetchAll() de tu clase Database
        $sales = $db->fetchAll(
            "SELECT s.*, 
                    CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
                    COUNT(si.id) as item_count
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN sale_items si ON s.id = si.sale_id
             WHERE {$whereClause}
             GROUP BY s.id
             ORDER BY s.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $whereParams
        );

        // ✅ USAR método single() de tu clase Database
        $total = $db->single(
            "SELECT COUNT(*) as total FROM sales s WHERE {$whereClause}",
            $whereParams
        )['total'];

        sendJsonResponse(true, 'Ventas obtenidas exitosamente', [
            'sales' => $sales,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);

    } catch (Exception $e) {
        error_log("Error obteniendo ventas: " . $e->getMessage());
        sendJsonResponse(false, 'Error al obtener ventas', null, 500);
    }
}
?>