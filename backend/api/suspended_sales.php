<?php
// Versión de diagnóstico para suspended_sales.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Función para registrar información de depuración
function debugLog($message, $data = null) {
    $logEntry = "[" . date('Y-m-d H:i:s') . "] SUSPENDED_SALES_DEBUG: " . $message;
    if ($data !== null) {
        $logEntry .= " | DATA: " . json_encode($data);
    }
    error_log($logEntry);
}

// Función para enviar respuestas JSON con más información
function sendJsonResponse($success, $message, $data = null, $statusCode = 200, $debug = null) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    
    $response = [
        'success' => $success, 
        'message' => $message, 
        'data' => $data
    ];
    
    // En modo desarrollo, incluir información de depuración
    if ($debug && (isset($_GET['debug']) || $_SERVER['HTTP_HOST'] === 'localhost')) {
        $response['debug'] = $debug;
    }
    
    echo json_encode($response);
    exit();
}

debugLog("Iniciando suspended_sales.php");

// Verificar que los archivos necesarios existan
$databasePath = '../config/database.php';
$authPath = '../includes/auth.php';

if (!file_exists($databasePath)) {
    debugLog("ERROR: No se encontró database.php en: " . realpath(dirname(__FILE__)) . '/' . $databasePath);
    sendJsonResponse(false, 'Error de configuración: archivo de base de datos no encontrado', null, 500);
}

if (!file_exists($authPath)) {
    debugLog("ERROR: No se encontró auth.php en: " . realpath(dirname(__FILE__)) . '/' . $authPath);
    sendJsonResponse(false, 'Error de configuración: archivo de autenticación no encontrado', null, 500);
}

require_once $databasePath;
require_once $authPath;

debugLog("Archivos cargados correctamente");

// Verificar función isAuthenticated
if (!function_exists('isAuthenticated')) {
    debugLog("ERROR: La función isAuthenticated() no está definida");
    sendJsonResponse(false, 'Error de configuración: función de autenticación no disponible', null, 500);
}

// Verificar autenticación
if (!isAuthenticated()) {
    debugLog("ERROR: Usuario no autenticado", [
        'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
        'session_business_id' => $_SESSION['business_id'] ?? 'not_set'
    ]);
    sendJsonResponse(false, 'Acceso denegado', null, 401);
}

debugLog("Usuario autenticado correctamente", [
    'user_id' => $_SESSION['user_id'],
    'business_id' => $_SESSION['business_id']
]);

// Verificar conexión a base de datos
try {
    $db = getDB();
    debugLog("Conexión a base de datos establecida");
} catch (Exception $e) {
    debugLog("ERROR: Error al conectar con la base de datos: " . $e->getMessage());
    sendJsonResponse(false, 'Error de conexión a base de datos: ' . $e->getMessage(), null, 500);
}

$business_id = $_SESSION['business_id'];
$method = $_SERVER['REQUEST_METHOD'];

debugLog("Procesando request", [
    'method' => $method,
    'business_id' => $business_id
]);

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
        debugLog("ERROR: Método no permitido: " . $method);
        sendJsonResponse(false, 'Método no permitido', null, 405);
        break;
}

function handlePostSuspendedSale($db, $business_id) {
    debugLog("Iniciando handlePostSuspendedSale");
    
    // Leer entrada
    $rawInput = file_get_contents('php://input');
    debugLog("Raw input recibido", ['length' => strlen($rawInput), 'content' => substr($rawInput, 0, 500)]);
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("ERROR: Error al decodificar JSON: " . json_last_error_msg());
        sendJsonResponse(false, 'Error al procesar datos JSON', null, 400);
    }
    
    debugLog("JSON decodificado correctamente", $input);

    if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        debugLog("ERROR: No se encontraron items válidos");
        sendJsonResponse(false, 'No se encontraron artículos en la venta suspendida.', null, 400);
    }

    $customer_id = $input['customer_id'] ?? null;
    $subtotal = $input['subtotal'] ?? 0;
    $tax = $input['tax'] ?? 0;
    $total = $input['total'] ?? 0;
    $include_igv = $input['includeIgv'] ?? 1;

    debugLog("Datos extraídos", [
        'customer_id' => $customer_id,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'include_igv' => $include_igv,
        'items_count' => count($input['items'])
    ]);

    try {
        // Verificar que las tablas existan antes de continuar
        $tablesCheck = $db->query("SHOW TABLES LIKE 'suspended_sales'");
        if (empty($tablesCheck)) {
            debugLog("ERROR: La tabla suspended_sales no existe");
            sendJsonResponse(false, 'Error: tabla suspended_sales no encontrada', null, 500);
        }
        
        $tablesCheck = $db->query("SHOW TABLES LIKE 'suspended_sale_items'");
        if (empty($tablesCheck)) {
            debugLog("ERROR: La tabla suspended_sale_items no existe");
            sendJsonResponse(false, 'Error: tabla suspended_sale_items no encontrada', null, 500);
        }

        debugLog("Tablas verificadas, iniciando transacción");
        $db->beginTransaction();

        $sale_number = 'SUSP-' . uniqid();
        debugLog("Número de venta generado: " . $sale_number);

        $stmt = $db->prepare("
            INSERT INTO suspended_sales (
                business_id, sale_number, customer_id, subtotal, tax, total, include_igv, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        
        $executeResult = $stmt->execute([
            $business_id, $sale_number, $customer_id, $subtotal, $tax, $total, $include_igv
        ]);
        
        if (!$executeResult) {
            debugLog("ERROR: Error al insertar en suspended_sales", $stmt->errorInfo());
            throw new Exception('Error al insertar venta suspendida');
        }

        $suspended_sale_id = $db->lastInsertId();
        debugLog("Venta suspendida creada con ID: " . $suspended_sale_id);

        foreach ($input['items'] as $index => $item) {
            debugLog("Procesando item " . ($index + 1), $item);
            
            $stmt = $db->prepare("
                INSERT INTO suspended_sale_items (
                    suspended_sale_id, product_id, product_name, quantity, price, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $executeResult = $stmt->execute([
                $suspended_sale_id, 
                $item['product_id'], 
                $item['name'], 
                $item['quantity'], 
                $item['price'], 
                $item['subtotal']
            ]);
            
            if (!$executeResult) {
                debugLog("ERROR: Error al insertar item " . ($index + 1), $stmt->errorInfo());
                throw new Exception('Error al insertar item de venta suspendida');
            }
        }

        $db->commit();
        debugLog("Transacción completada exitosamente");
        
        sendJsonResponse(true, 'Venta suspendida creada con éxito.', [
            'id' => $suspended_sale_id, 
            'sale_number' => $sale_number, 
            'total' => $total, 
            'created_at' => date('Y-m-d H:i:s'),
            'customer_id' => $customer_id,
            'include_igv' => $include_igv
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        debugLog("ERROR: Excepción capturada: " . $e->getMessage());
        sendJsonResponse(false, 'Error al suspender la venta: ' . $e->getMessage(), null, 500, [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

function handleGetSuspendedSale($db, $business_id) {
    // ... mantener código existente pero agregar debugLog donde sea necesario
}

function handleDeleteSuspendedSale($db, $business_id) {
    // ... mantener código existente pero agregar debugLog donde sea necesario
}
?>