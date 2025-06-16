<?php
// Archivo temporal para probar la conexión a la base de datos
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Conexión a Base de Datos</h1>";

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'u347334547_invapp';
$username = 'u347334547_invapp';
$password = 'CH7322a#';

echo "<h2>Configuración:</h2>";
echo "Host: $host<br>";
echo "Database: $dbname<br>";
echo "Username: $username<br>";
echo "Password: " . str_repeat('*', strlen($password)) . "<br><br>";

// Test 1: Conexión básica
echo "<h2>Test 1: Conexión PDO básica</h2>";
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conexión PDO exitosa<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Query test exitosa: " . $result['test'] . "<br>";
    
    // Test tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "✅ Tablas encontradas: " . count($tables) . "<br>";
    foreach ($tables as $table) {
        echo "- " . array_values($table)[0] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error PDO: " . $e->getMessage() . "<br>";
}

// Test 2: Usando la clase Database
echo "<h2>Test 2: Clase Database</h2>";
try {
    require_once 'backend/config/database.php';
    
    $db = getDB();
    echo "✅ Instancia Database creada<br>";
    
    if ($db->isConnected()) {
        echo "✅ Database conectada<br>";
        
        // Test query usuarios
        $users = $db->fetchAll("SELECT COUNT(*) as total FROM users");
        echo "✅ Query usuarios exitosa - Total: " . $users[0]['total'] . "<br>";
        
    } else {
        echo "❌ Database NO conectada<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error Database: " . $e->getMessage() . "<br>";
}

// Test 3: mysqli
echo "<h2>Test 3: MySQLi</h2>";
try {
    $mysqli = new mysqli($host, $username, $password, $dbname);
    
    if ($mysqli->connect_error) {
        echo "❌ Error MySQLi: " . $mysqli->connect_error . "<br>";
    } else {
        echo "✅ Conexión MySQLi exitosa<br>";
        
        $result = $mysqli->query("SELECT VERSION() as version");
        $row = $result->fetch_assoc();
        echo "✅ MySQL Version: " . $row['version'] . "<br>";
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "❌ Error MySQLi: " . $e->getMessage() . "<br>";
}

echo "<h2>Información del Sistema</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Extensions cargadas: " . (extension_loaded('pdo') ? 'PDO ✅' : 'PDO ❌') . " | " . (extension_loaded('pdo_mysql') ? 'PDO_MySQL ✅' : 'PDO_MySQL ❌') . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
?>