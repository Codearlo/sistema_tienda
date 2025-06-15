<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Login</h2>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<form method="POST">
        Email: <input type="email" name="email" value="admin@treinta.local" required><br><br>
        Password: <input type="password" name="password" value="password" required><br><br>
        <button type="submit">Login</button>
    </form>';
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

echo "1. Email: " . htmlspecialchars($email) . "<br>";
echo "2. Password length: " . strlen($password) . "<br><br>";

if (empty($email) || empty($password)) {
    echo "❌ Email o password vacíos<br>";
    exit();
}

try {
    echo "3. Conectando a la base de datos...<br>";
    
    // Conexión directa sin includes
    $host = 'localhost';
    $db_name = 'u347334547_inv_db';
    $username = 'u347334547_inv_user';
    $db_password = 'CH7322a#';
    
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexión exitosa<br><br>";
    
    echo "4. Buscando usuario...<br>";
    
    $stmt = $pdo->prepare(
        "SELECT u.*, b.business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ Usuario no encontrado<br>";
        exit();
    }
    
    echo "✅ Usuario encontrado: " . $user['first_name'] . " " . $user['last_name'] . "<br>";
    echo "Hash en BD: " . substr($user['password'], 0, 20) . "...<br><br>";
    
    echo "5. Verificando password...<br>";
    echo "Password ingresado: '$password'<br>";
    
    // Verificar el hash actual
    $test_hash = password_hash('password', PASSWORD_DEFAULT);
    echo "Nuevo hash test: " . substr($test_hash, 0, 20) . "...<br>";
    echo "Verify test: " . (password_verify('password', $test_hash) ? 'SÍ' : 'NO') . "<br>";
    echo "Verify BD: " . (password_verify($password, $user['password']) ? 'SÍ' : 'NO') . "<br><br>";
    
    if (!password_verify($password, $user['password'])) {
        echo "❌ Password incorrecto<br>";
        echo "<strong>Posible solución:</strong> El password en la BD puede estar mal hasheado.<br>";
        
        // Intentar actualizar el password
        echo "<br>6. Actualizando password...<br>";
        $new_hash = password_hash('password', PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$new_hash, $user['id']]);
        echo "✅ Password actualizado. Intenta el login nuevamente.<br>";
        exit();
    }
    
    echo "✅ Password correcto<br><br>";
    
    echo "6. Actualizando último login...<br>";
    $update_stmt = $pdo->prepare(
        "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?"
    );
    $update_stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
    echo "✅ Usuario actualizado<br><br>";
    
    echo "7. Estableciendo sesión...<br>";
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['business_id'] = $user['business_id'];
    $_SESSION['business_name'] = $user['business_name'];
    $_SESSION['logged_in_at'] = time();
    
    echo "✅ Sesión establecida:<br>";
    echo "- user_id: " . $_SESSION['user_id'] . "<br>";
    echo "- user_name: " . $_SESSION['user_name'] . "<br>";
    echo "- business_id: " . $_SESSION['business_id'] . "<br><br>";
    
    echo "🎉 <strong>LOGIN EXITOSO!</strong><br>";
    echo '<a href="dashboard.php">Ir al Dashboard</a>';
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>