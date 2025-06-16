<?php
session_start();

require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no es válido');
    }
    
    if (strlen($password) < 8) {
        throw new Exception('La contraseña debe tener al menos 8 caracteres');
    }
    
    if ($password !== $confirm_password) {
        throw new Exception('Las contraseñas no coinciden');
    }
    
    $db = getDB();
    
    // Verificar si el email ya existe
    $existing_user = $db->single(
        "SELECT id FROM users WHERE email = ?",
        [$email]
    );
    
    if ($existing_user) {
        throw new Exception('Ya existe una cuenta con este email');
    }
    
    // Crear el usuario
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // CORRECCIÓN: Usar la sintaxis correcta del método insert()
    $user_id = $db->insert('users', [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password' => $hashed_password,
        'user_type' => 'admin',
        'status' => 1,
        'email_verified' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$user_id) {
        throw new Exception('Error al crear la cuenta');
    }
    
    // Crear sesión
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['user_type'] = 'admin';
    // NO establecemos business_id ni onboarding_completed para forzar el onboarding
    
    echo json_encode([
        'success' => true,
        'message' => 'Cuenta creada exitosamente',
        'redirect' => '/onboarding.php' // Redirigir al onboarding
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>