<?php
session_start();

// Limpiar cualquier error anterior
unset($_SESSION['error_message']);

// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['error_message'] = 'Email y contraseña son requeridos.';
    header('Location: login.php');
    exit();
}

try {
    // Conexión directa como en el test que funciona
    $host = 'localhost';
    $db_name = 'u347334547_inv_db';
    $username = 'u347334547_inv_user';
    $db_password = 'CH7322a#';
    
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->prepare(
        "SELECT u.*, b.business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: login.php');
        exit();
    }
    
    // Verificar si está bloqueado
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $_SESSION['error_message'] = 'Cuenta bloqueada. Intente más tarde.';
        header('Location: login.php');
        exit();
    }
    
    // Actualizar último login
    $update_stmt = $pdo->prepare(
        "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?"
    );
    $update_stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
    
    // Establecer sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['business_id'] = $user['business_id'];
    $_SESSION['business_name'] = $user['business_name'] ?? 'Mi Negocio';
    $_SESSION['logged_in_at'] = time();
    
    // Redirigir al dashboard
    header('Location: dashboard.php');
    exit();
    
} catch (Exception $e) {
    error_log('Error en login: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Error de conexión: ' . $e->getMessage();
    header('Location: login.php');
    exit();
}
?>