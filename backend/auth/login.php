<?php
session_start();

// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG LOGIN ===<br>";
echo "POST data: " . print_r($_POST, true) . "<br>";

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Error: No es POST<br>";
    header('Location: ../../login.php');
    exit();
}

echo "1. Método POST OK<br>";

// Incluir database
echo "2. Incluyendo database...<br>";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✅ Database incluido<br>";
} catch (Exception $e) {
    echo "❌ Error incluyendo database: " . $e->getMessage() . "<br>";
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

echo "3. Email: '$email'<br>";
echo "4. Password length: " . strlen($password) . "<br>";

if (empty($email) || empty($password)) {
    echo "❌ Email o password vacíos<br>";
    $_SESSION['error_message'] = 'Email y contraseña son requeridos.';
    header('Location: ../../login.php');
    exit();
}

echo "5. Datos OK<br>";

try {
    echo "6. Obteniendo DB...<br>";
    $db = getDB();
    echo "✅ DB obtenida<br>";
    
    echo "7. Consultando usuario...<br>";
    $user = $db->single(
        "SELECT u.*, b.business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1",
        [$email]
    );
    
    if (!$user) {
        echo "❌ Usuario no encontrado<br>";
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: ../../login.php');
        exit();
    }
    
    echo "✅ Usuario encontrado: " . $user['first_name'] . "<br>";
    
    echo "8. Verificando password...<br>";
    if (!password_verify($password, $user['password'])) {
        echo "❌ Password incorrecto<br>";
        $_SESSION['error_message'] = 'Credenciales incorrectas.';
        header('Location: ../../login.php');
        exit();
    }
    
    echo "✅ Password correcto<br>";
    
    // Verificar si está bloqueado
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        echo "❌ Usuario bloqueado<br>";
        $_SESSION['error_message'] = 'Cuenta bloqueada. Intente más tarde.';
        header('Location: ../../login.php');
        exit();
    }
    
    echo "9. Actualizando usuario...<br>";
    $db->update('users', [
        'login_attempts' => 0,
        'locked_until' => null,
        'last_login' => date('Y-m-d H:i:s')
    ], 'id = ?', [$user['id']]);
    
    echo "✅ Usuario actualizado<br>";
    
    echo "10. Estableciendo sesión...<br>";
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['business_id'] = $user['business_id'];
    $_SESSION['business_name'] = $user['business_name'] ?? 'Mi Negocio';
    $_SESSION['logged_in_at'] = time();
    
    echo "✅ Sesión establecida<br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "User Name: " . $_SESSION['user_name'] . "<br>";
    echo "Business ID: " . $_SESSION['business_id'] . "<br>";
    
    echo "11. Redirigiendo...<br>";
    echo '<a href="../../dashboard.php">Ir manualmente al Dashboard</a><br>';
    
    // Intentar redirección
    header('Location: ../../dashboard.php');
    exit();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Trace: " . $e->getTraceAsString() . "<br>";
    
    error_log('Error en login: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Error al procesar el login. Intente nuevamente.';
    header('Location: ../../login.php');
    exit();
}
?>