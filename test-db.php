<?php
// Test simple de conexión a la base de datos
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Conexión a Base de Datos</h2>";

// Credenciales
$host = 'localhost';
$db_name = 'u347334547_inv_db';
$username = 'u347334547_inv_user';
$password = 'CH7322a#';

echo "1. Probando conexión con credenciales:<br>";
echo "Host: " . $host . "<br>";
echo "Database: " . $db_name . "<br>";
echo "Username: " . $username . "<br>";
echo "Password: " . str_repeat('*', strlen($password)) . "<br><br>";

try {
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    
    echo "2. Creando conexión PDO...<br>";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ <strong>Conexión exitosa!</strong><br><br>";
    
    // Test de consulta simple
    echo "3. Probando consulta simple...<br>";
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] == 1) {
        echo "✅ <strong>Consulta exitosa!</strong><br><br>";
    }
    
    // Verificar si existe la tabla users
    echo "4. Verificando tabla 'users'...<br>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✅ <strong>Tabla 'users' existe!</strong><br>";
        
        // Contar usuarios
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch();
        echo "📊 Total de usuarios: " . $count['count'] . "<br><br>";
        
        // Mostrar usuario admin
        echo "5. Verificando usuario admin...<br>";
        $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, status FROM users WHERE email = ?");
        $stmt->execute(['admin@treinta.local']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "✅ <strong>Usuario admin encontrado!</strong><br>";
            echo "ID: " . $admin['id'] . "<br>";
            echo "Email: " . $admin['email'] . "<br>";
            echo "Nombre: " . $admin['first_name'] . " " . $admin['last_name'] . "<br>";
            echo "Status: " . ($admin['status'] ? 'Activo' : 'Inactivo') . "<br>";
        } else {
            echo "❌ <strong>Usuario admin NO encontrado!</strong><br>";
            echo "Usuarios existentes:<br>";
            $stmt = $pdo->query("SELECT email FROM users LIMIT 5");
            while ($user = $stmt->fetch()) {
                echo "- " . $user['email'] . "<br>";
            }
        }
        
    } else {
        echo "❌ <strong>Tabla 'users' NO existe!</strong><br>";
        echo "Tablas disponibles:<br>";
        $stmt = $pdo->query("SHOW TABLES");
        while ($table = $stmt->fetch()) {
            echo "- " . $table[array_keys($table)[0]] . "<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>Error de conexión:</strong><br>";
    echo "Código: " . $e->getCode() . "<br>";
    echo "Mensaje: " . $e->getMessage() . "<br>";
    
    // Errores comunes
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<br>🔍 <strong>Posibles causas:</strong><br>";
        echo "- Usuario o contraseña incorrectos<br>";
        echo "- Usuario sin permisos para la base de datos<br>";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<br>🔍 <strong>Posibles causas:</strong><br>";
        echo "- La base de datos no existe<br>";
        echo "- Nombre de base de datos incorrecto<br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error general:</strong><br>";
    echo "Mensaje: " . $e->getMessage() . "<br>";
}
?>