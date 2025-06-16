<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email_sales_report = $input['email_sales_report'] ? 1 : 0;
    $email_low_stock = $input['email_low_stock'] ? 1 : 0;
    $low_stock_threshold = intval($input['low_stock_threshold'] ?? 10);
    
    // Validaciones
    if ($low_stock_threshold < 1) {
        throw new Exception('El umbral de stock bajo debe ser mayor a 0');
    }
    
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Configuraciones a actualizar
    $settings_to_update = [
        'email_sales_report' => $email_sales_report,
        'email_low_stock' => $email_low_stock,
        'low_stock_threshold' => $low_stock_threshold
    ];
    
    foreach ($settings_to_update as $key => $value) {
        // Verificar si la configuración ya existe
        $existing = $db->single(
            "SELECT id FROM settings WHERE business_id = ? AND setting_key = ?",
            [$business_id, $key]
        );
        
        if ($existing) {
            // Actualizar configuración existente
            $db->update(
                "settings",
                [
                    'setting_value' => $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "id = ?",
                [$existing['id']]
            );
        } else {
            // Crear nueva configuración
            $db->insert("settings", [
                'business_id' => $business_id,
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => is_int($value) ? 'number' : 'boolean',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuraciones de notificaciones actualizadas exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>