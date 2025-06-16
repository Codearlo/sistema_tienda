<?php
session_start();

require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $business_name = trim($input['business_name'] ?? '');
    $business_type = trim($input['business_type'] ?? '');
    $ruc = trim($input['ruc'] ?? '');
    $address = trim($input['address'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    
    // Validaciones
    if (empty($business_name)) {
        throw new Exception('El nombre del negocio es requerido');
    }
    
    if (empty($business_type)) {
        throw new Exception('El tipo de negocio es requerido');
    }
    
    // Validar RUC si se proporciona
    if (!empty($ruc) && !preg_match('/^\d{11}$/', $ruc)) {
        throw new Exception('El RUC debe tener exactamente 11 dígitos');
    }
    
    // Validar email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del email no es válido');
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
    
    // Actualizar la información del negocio
    $updated = $db->query(
        "UPDATE businesses 
         SET business_name = ?, business_type = ?, ruc = ?, address = ?, phone = ?, email = ?, updated_at = NOW()
         WHERE id = ?",
        [$business_name, $business_type, $ruc ?: null, $address ?: null, $phone ?: null, $email ?: null, $business_id]
    );
    
    if (!$updated) {
        throw new Exception('Error al actualizar la información del negocio');
    }
    
    // Registrar en audit log
    $db->query(
        "INSERT INTO audit_logs (business_id, user_id, action, description, ip_address) 
         VALUES (?, ?, 'business_update', 'Información del negocio actualizada', ?)",
        [$business_id, $user_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Información del negocio actualizada exitosamente',
        'data' => [
            'business_name' => $business_name,
            'business_type' => $business_type,
            'ruc' => $ruc,
            'address' => $address,
            'phone' => $phone,
            'email' => $email
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>