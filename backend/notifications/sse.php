<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    http_response_code(401);
    exit();
}

// Configurar headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Evitar timeout
set_time_limit(0);
ini_set('auto_detect_line_endings', 1);

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];
$last_notification_id = intval($_GET['lastId'] ?? 0);

// Función para enviar datos SSE
function sendSSE($id, $event, $data) {
    echo "id: $id\n";
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    // Forzar envío inmediato
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
}

try {
    $db = getDB();
    
    // Buscar notificaciones nuevas
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE business_id = ? AND id > ? 
         ORDER BY id ASC 
         LIMIT 10",
        [$business_id, $last_notification_id]
    );
    
    if (!empty($notifications)) {
        foreach ($notifications as $notification) {
            sendSSE(
                $notification['id'], 
                'notification', 
                [
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'priority' => $notification['priority'],
                    'created_at' => $notification['created_at']
                ]
            );
            $last_notification_id = $notification['id'];
        }
    }
    
    // Verificar stock bajo
    $low_stock_products = $db->fetchAll(
        "SELECT p.name, p.stock_quantity, p.min_stock 
         FROM products p 
         WHERE p.business_id = ? AND p.stock_quantity <= p.min_stock AND p.status = 1",
        [$business_id]
    );
    
    if (!empty($low_stock_products)) {
        $count = count($low_stock_products);
        sendSSE(
            'stock_' . time(),
            'low_stock',
            [
                'count' => $count,
                'message' => "$count producto(s) con stock bajo",
                'products' => array_slice($low_stock_products, 0, 3) // Solo primeros 3
            ]
        );
    }
    
    // Heartbeat para mantener conexión
    sendSSE('heartbeat_' . time(), 'heartbeat', ['timestamp' => time()]);
    
} catch (Exception $e) {
    sendSSE('error_' . time(), 'error', ['message' => 'Error del servidor']);
    error_log('SSE Error: ' . $e->getMessage());
}
?>