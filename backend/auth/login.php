<?php
session_start();

// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Inicio del script de login<br>";

require_once '../config/database.php';

echo "Archivos incluidos correctamente<br>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Método no es POST<br>";
    header('Location: ../../login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

echo "Email: " . htmlspecialchars($email) . "<br>";
echo "Password length: " . strlen($password) . "<br>";

if (empty($email) || empty($password)) {
    echo "Email o password vacíos<br>";
    $_SESSION['error_message'] = 'Email y contraseña son requeridos.';
    header('Location: ../../login.php');
    exit();
}

try {
    echo "Intentando conectar a la base de datos<br>";
    $db = getDB();
    echo "Conexión exitosa<br>";
    
    $user = $db->single(
        "SELECT u.*, b.business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1",
        [$email]
    );
    
    echo "Consulta ejecutada<br>";
    
    if (!$user) {
        echo "Usuario no encontrado<br>";
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: ../../login.php');
        exit();
    }
    
    echo "Usuario encontrado: " . print_r($user, true) . "<br>";
    
    if (!password_verify($password, $user['password'])) {
        echo "Password incorrecto<br>";
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: ../../login.php');
        exit();
    }
    
    echo "Password correcto<br>";
    
    // Login exitoso
    $db->update('users', [
        'login_attempts' => 0,
        'locked_until' => null,
        'last_login' => date('Y-m-d H:i:s')
    ], 'id = ?', [$user['id']]);
    
    echo "Usuario actualizado<br>";
    
    // Establecer sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['business_id'] = $user['business_id'];
    $_SESSION['business_name'] = $user['business_name'];
    $_SESSION['logged_in_at'] = time();
    
    echo "Sesión establecida<br>";
    echo "Redirigiendo al dashboard<br>";
    
    // Redirigir al dashboard
    header('Location: ../../dashboard.php');
    exit();
    
} catch (Exception $e) {
    echo "Error capturado: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
    error_log('Error en login: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Error al procesar el login. Intente nuevamente.';
    header('Location: ../../login.php');
    exit();
}
?>