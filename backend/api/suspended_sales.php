<?php
/**
 * API para manejo de ventas suspendidas
 * Versión con rutas corregidas
 */

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para logging de depuración
function debugLog($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] SUSPENDED_SALES: " . $message;
    if ($data !== null) {
        $logMessage .= " | " . json_encode($data);
    }
    error_log($logMessage);
}

// Función mejorada para respuestas JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    
    header('Content-Type: application/json');
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    exit();
}

debugLog("Iniciando suspended_sales.php", ['method' => $_SERVER['REQUEST_METHOD']]);

try {
    // RUTAS CORREGIDAS - includes está en la raíz, no en backend
    $configFiles = [
        '../config/database.php' => realpath(__DIR__ . '/../config/database.php'),
        '../../includes/auth.php' => realpath(__DIR__ . '/../../includes/auth.php')  // Subir dos niveles
    ];
    
    foreach ($configFiles as $file => $realPath) {
        if (!$realPath || !file_exists($realPath)) {
            debugLog("ERROR: Archivo de configuración no encontrado", ['file' => $file, 'realPath' => $realPath]);
            sendJsonResponse(false, "Error de configuración: archivo {$file} no encontrado", null, 500);
        }
    }
    
    // Incluir archivos necesarios con rutas corregidas
    require_once '../config/database.php';
    require_once '../../includes/auth.php';  // Ruta corregida
    
    debugLog("Archivos de configuración cargados correctamente");
    
    // Verificar que la función isAuthenticated esté disponible
    if (!function_exists('isAuthenticated')) {
        debugLog("ERROR: Función isAuthenticated no definida");
        sendJsonResponse(false, 'Error de configuración: función de autenticación no disponible', null, 500);
    }
    
    // Verificar autenticación
    if (!isAuthenticated()) {
        debugLog("ERROR: Usuario no autenticado", [
            'user_id' => $_SESSION['user_id'] ?? 'no_definido',
            'business_id' => $_SESSION['business_id'] ?? 'no_definido'
        ]);
        sendJsonResponse(false, 'Acceso denegado. Por favor, inicia sesión nuevamente.', null, 401);
    }
    
    debugLog("Usuario autenticado", [
        'user_id' => $_SESSION['user_id'],
        'business_id' => $_SESSION['business_id']
    ]);
    
    // Verificar conexión a base de datos
    if (!function_exists('getDB')) {
        debugLog("ERROR: Función getDB no disponible");
        sendJsonResponse(false, 'Error de configuración de base de datos', null, 500);
    }
    
    $db = getDB();
    if (!$db) {
        debugLog("ERROR: No se pudo establecer conexión con la base de datos");
        sendJsonResponse(false, 'Error de conexión a base de datos', null, 500);
    }
    
    debugLog("Conexión a base de datos establecida");
    
    $business_id = $_SESSION['business_id'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    debugLog("Procesando solicitud", ['method' => $method, 'business_id' => $business_id]);
    
    // Enrutar según método HTTP
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
            debugLog("ERROR: Método HTTP no permitido", ['method' => $method]);
            sendJsonResponse(false, 'Método no permitido', null, 405);
            break;
    }

} catch (Exception $e) {
    debugLog("ERROR: Excepción no capturada", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    sendJsonResponse(false, 'Error interno del servidor: ' . $e->getMessage(), null, 500);
}

function handlePostSuspendedSale($db, $business_id) {
    debugLog("Iniciando handlePostSuspendedSale");
    
    try {
        // Obtener y validar datos de entrada
        $rawInput = file_get_contents('php://input');
        debugLog("Input recibido", ['length' => strlen($rawInput)]);
        
        if (empty($rawInput)) {
            sendJsonResponse(false, 'No se recibieron datos', null, 400);
        }
        
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugLog("ERROR: Error al decodificar JSON", ['error' => json_last_error_msg()]);
            sendJsonResponse(false, 'Error al procesar datos JSON: ' . json_last_error_msg(), null, 400);
        }
        
        debugLog("Datos decodificados correctamente", $input);
        
        // Validar estructura de datos
        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            debugLog("ERROR: Items no válidos o vacíos");
            sendJsonResponse(false, 'No se encontraron artículos válidos en la venta suspendida', null, 400);
        }
        
        // Extraer datos
        $customer_id = $input['customer_id'] ?? null;
        $subtotal = floatval($input['subtotal'] ?? 0);
        $tax = floatval($input['tax'] ?? 0);
        $total = floatval($input['total'] ?? 0);
        $include_igv = isset($input['includeIgv']) ? ($input['includeIgv'] ? 1 : 0) : 1;
        
        debugLog("Datos procesados", [
            'customer_id' => $customer_id,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'include_igv' => $include_igv,
            'items_count' => count($input['items'])
        ]);
        
        // Verificar que las tablas existan
        $tables = ['suspended_sales', 'suspended_sale_items'];
        foreach ($tables as $table) {
            $result = $db->query("SHOW TABLES LIKE '{$table}'");
            if (empty($result)) {
                debugLog("ERROR: Tabla no existe", ['table' => $table]);
                sendJsonResponse(false, "Error: la tabla {$table} no existe en la base de datos", null, 500);
            }
        }
        
        debugLog("Tablas verificadas, iniciando transacción");
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Generar número de venta único
        $sale_number = 'SUSP-' . date('YmdHis') . '-' . uniqid();
        
        // Insertar venta suspendida
        $stmt = $db->prepare("
            INSERT INTO suspended_sales (
                business_id, sale_number, customer_id, subtotal, tax, total, include_igv, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        
        $result = $stmt->execute([
            $business_id, $sale_number, $customer_id, $subtotal, $tax, $total, $include_igv
        ]);
        
        if (!$result) {
            throw new Exception('Error al insertar venta suspendida: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $suspended_sale_id = $db->lastInsertId();
        debugLog("Venta suspendida creada", ['id' => $suspended_sale_id, 'sale_number' => $sale_number]);
        
        // Insertar items
        foreach ($input['items'] as $index => $item) {
            debugLog("Procesando item", ['index' => $index, 'item' => $item]);
            
            $stmt = $db->prepare("
                INSERT INTO suspended_sale_items (
                    suspended_sale_id, product_id, product_name, quantity, price, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $suspended_sale_id, 
                $item['product_id'], 
                $item['name'], 
                $item['quantity'], 
                $item['price'], 
                $item['subtotal']
            ]);
            
            if (!$result) {
                throw new Exception("Error al insertar item {$index}: " . implode(', ', $stmt->errorInfo()));
            }
        }
        
        // Confirmar transacción
        $db->commit();
        debugLog("Transacción completada exitosamente");
        
        // Enviar respuesta exitosa
        sendJsonResponse(true, 'Venta suspendida creada con éxito', [
            'id' => $suspended_sale_id,
            'sale_number' => $sale_number,
            'total' => $total,
            'created_at' => date('Y-m-d H:i:s'),
            'customer_id' => $customer_id,
            'include_igv' => $include_igv
        ]);
        
    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        debugLog("ERROR en handlePostSuspendedSale", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendJsonResponse(false, 'Error al suspender la venta: ' . $e->getMessage(), null, 500);
    }
}

function handleGetSuspendedSale($db, $business_id) {
    debugLog("Iniciando handleGetSuspendedSale");

    $sale_id = $_GET['id'] ?? null;

    if (!$sale_id) {
        debugLog("ERROR: ID de venta suspendida no proporcionado para GET");
        sendJsonResponse(false, 'ID de venta suspendida no proporcionado', null, 400);
    }

    try {
        $suspended_sale = $db->single("
            SELECT * FROM suspended_sales 
            WHERE id = ? AND business_id = ? AND status = 'active'
        ", [$sale_id, $business_id]);

        if (!$suspended_sale) {
            debugLog("Venta suspendida no encontrada o inactiva", ['sale_id' => $sale_id]);
            sendJsonResponse(false, 'Venta suspendida no encontrada o ya completada', null, 404);
        }

        $items = $db->fetchAll("
            SELECT * FROM suspended_sale_items 
            WHERE suspended_sale_id = ?
        ", [$sale_id]);

        $suspended_sale['items'] = $items;
        debugLog("Venta suspendida obtenida con éxito", ['sale_id' => $sale_id, 'items_count' => count($items)]);
        sendJsonResponse(true, 'Venta suspendida obtenida con éxito', $suspended_sale);

    } catch (Exception $e) {
        debugLog("ERROR en handleGetSuspendedSale", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendJsonResponse(false, 'Error al obtener la venta suspendida: ' . $e->getMessage(), null, 500);
    }
}

function handleDeleteSuspendedSale($db, $business_id) {
    debugLog("Iniciando handleDeleteSuspendedSale");

    $sale_id = $_GET['id'] ?? null;

    if (!$sale_id) {
        debugLog("ERROR: ID de venta suspendida no proporcionado para DELETE");
        sendJsonResponse(false, 'ID de venta suspendida no proporcionado', null, 400);
    }

    try {
        $db->beginTransaction();

        // Eliminar los ítems de la venta suspendida
        $stmt_items = $db->prepare("DELETE FROM suspended_sale_items WHERE suspended_sale_id = ?");
        $stmt_items->execute([$sale_id]);
        debugLog("Ítems de venta suspendida eliminados", ['sale_id' => $sale_id, 'rows_affected' => $stmt_items->rowCount()]);

        // Eliminar la venta suspendida
        $stmt_sale = $db->prepare("DELETE FROM suspended_sales WHERE id = ? AND business_id = ?");
        $stmt_sale->execute([$sale_id, $business_id]);

        if ($stmt_sale->rowCount() === 0) {
            $db->rollBack();
            debugLog("Venta suspendida no encontrada para eliminar", ['sale_id' => $sale_id]);
            sendJsonResponse(false, 'Venta suspendida no encontrada o no tienes permisos para eliminarla', null, 404);
        }

        $db->commit();
        debugLog("Venta suspendida eliminada con éxito", ['sale_id' => $sale_id]);
        sendJsonResponse(true, 'Venta suspendida eliminada con éxito', ['id' => $sale_id]);

    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        debugLog("ERROR en handleDeleteSuspendedSale", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        sendJsonResponse(false, 'Error al eliminar la venta suspendida: ' . $e->getMessage(), null, 500);
    }
}
?>