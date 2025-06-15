<?php
session_start();

// Rutas corregidas
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['error_message'] = 'Email y contraseña son requeridos.';
    header('Location: ../../login.php');
    exit();
}

try {
    $db = getDB();
    
    $user = $db->single(
        "SELECT u.*, b.business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1",
        [$email]
    );
    
    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: ../../login.php');
        exit();
    }
    
    // Verificar si está bloqueado
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $_SESSION['error_message'] = 'Cuenta bloqueada. Intente más tarde.';
        header('Location: ../../login.php');
        exit();
    }
    
    // Login exitoso
    $db->update('users', [
        'login_attempts' => 0,
        'locked_until' => null,
        'last_login' => date('Y-m-d H:i:s')
    ], 'id = ?', [$user['id']]);
    
    // Establecer sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['business_id'] = $user['business_id'];
    $_SESSION['business_name'] = $user['business_name'];
    $_SESSION['logged_in_at'] = time();
    
    // Redirigir al dashboard
    header('Location: ../../dashboard.php');
    exit();
    
} catch (Exception $e) {
    error_log('Error en login: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Error al procesar el login. Intente nuevamente.';
    header('Location: ../../login.php');
    exit();
}
?>