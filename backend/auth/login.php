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
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        throw new Exception('Email y contraseña son requeridos');
    }
    
    $db = getDB();
    
    // Buscar usuario
    $user = $db->single(
        "SELECT u.*, b.id as business_id 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1",
        [$email]
    );
    
    if (!$user || !password_verify($password, $user['password'])) {
        // Registrar intento de login fallido
        if ($user) {
            $db->query(
                "UPDATE users SET login_attempts = login_attempts + 1, 
                 locked_until = CASE 
                     WHEN login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                     ELSE locked_until 
                 END 
                 WHERE id = ?",
                [$user['id']]
            );
        }
        
        throw new Exception('Email o contraseña incorrectos');
    }
    
    // Verificar si la cuenta está bloqueada
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        throw new Exception('Cuenta bloqueada temporalmente. Intenta nuevamente más tarde.');
    }
    
    // Resetear intentos de login
    $db->query(
        "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?",
        [$user['id']]
    );
    
    // Crear sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    
    // Verificar si tiene negocio configurado
    $redirect_url = '/dashboard.php';
    
    if ($user['business_id']) {
        $_SESSION['business_id'] = $user['business_id'];
        $_SESSION['onboarding_completed'] = true;
    } else {
        // Usuario sin negocio configurado, debe completar onboarding
        $redirect_url = '/onboarding.php';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'redirect' => $redirect_url,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'has_business' => !empty($user['business_id'])
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