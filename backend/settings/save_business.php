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
    
    $business_name = trim($input['business_name'] ?? '');
    $business_phone = trim($input['business_phone'] ?? '');
    $business_email = trim($input['business_email'] ?? '');
    $business_address = trim($input['business_address'] ?? '');
    
    // Validaciones
    if (empty($business_name)) {
        throw new Exception('El nombre del negocio es requerido');
    }
    
    if (!empty($business_email) && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del email del negocio no es válido');
    }
    
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verificar que el usuario sea propietario del negocio
    $business = $db->single(
        "SELECT owner_id FROM businesses WHERE id = ?",
        [$business_id]
    );
    
    if (!$business || $business['owner_id'] != $user_id) {
        throw new Exception('No tienes permisos para editar este negocio');
    }
    
    // Actualizar el negocio
    $updated = $db->update(
        "businesses",
        [
            'business_name' => $business_name,
            'phone' => $business_phone ?: null,
            'email' => $business_email ?: null,
            'address' => $business_address ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        "id = ?",
        [$business_id]
    );
    
    if (!$updated) {
        throw new Exception('Error al actualizar la información del negocio');
    }
    
    // Actualizar datos de sesión
    $_SESSION['business_name'] = $business_name;
    
    echo json_encode([
        'success' => true,
        'message' => 'Información del negocio actualizada exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>