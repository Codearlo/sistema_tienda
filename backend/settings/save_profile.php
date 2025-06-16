<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    
    // Validaciones
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception('Nombre, apellido y email son requeridos');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del email no es válido');
    }
    
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Verificar si el email ya existe en otro usuario
    $existing_user = $db->single(
        "SELECT id FROM users WHERE email = ? AND id != ?",
        [$email, $user_id]
    );
    
    if ($existing_user) {
        throw new Exception('Este email ya está siendo usado por otro usuario');
    }
    
    // Actualizar el usuario
    $updated = $db->update(
        "users",
        [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        "id = ?",
        [$user_id]
    );
    
    if (!$updated) {
        throw new Exception('Error al actualizar el perfil');
    }
    
    // Actualizar datos de sesión
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;
    $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil actualizado exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>