<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Simple</h1>";

// Test directo de conexión PDO
echo "<h2>Test Conexión Directa</h2>";

$host = 'localhost';
$dbname = 'u347334547_inv_db';
$username = 'u347334547_inv_user';
$password = 'CH7322a#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexión PDO exitosa<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Query funcionando: " . $result['test'] . "<br>";
    
    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "✅ Tablas encontradas: " . count($tables) . "<br>";
    
    foreach ($tables as $table) {
        $table_name = array_values($table)[0];
        echo "- $table_name<br>";
    }
    
    // Test usuarios
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $count = $stmt->fetch();
        echo "✅ Usuarios en BD: " . $count['total'] . "<br>";
        
        // Mostrar algunos usuarios
        $stmt = $pdo->query("SELECT email, first_name, status FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        echo "Usuarios:<br>";
        foreach ($users as $user) {
            echo "- " . $user['email'] . " (" . $user['first_name'] . ", status: " . $user['status'] . ")<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error con tabla users: " . $e->getMessage() . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}

echo "<h2>Información PHP</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Disponible' : 'No disponible') . "<br>";

echo "<h2>✅ Test Completado</h2>";
echo "<a href='login.php'>Probar Login</a>";
?>