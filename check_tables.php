<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificar Estructura de Tablas</h1>";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=u347334547_inv_db;charset=utf8mb4", 
                  "u347334547_inv_user", "CH7322a#", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexión exitosa<br><br>";
    
    // Verificar estructura de tabla users
    echo "<h2>Estructura tabla USERS:</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
    
    echo "<br>";
    
    // Verificar si existe tabla businesses
    echo "<h2>Verificar tabla BUSINESSES:</h2>";
    try {
        $stmt = $pdo->query("DESCRIBE businesses");
        $columns = $stmt->fetchAll();
        echo "✅ Tabla businesses existe:<br>";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "❌ Tabla businesses NO existe<br>";
        echo "Error: " . $e->getMessage() . "<br>";
        
        // Crear tabla businesses
        echo "<br><h3>Creando tabla businesses...</h3>";
        $sql = "CREATE TABLE businesses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_name VARCHAR(255) NOT NULL,
            owner_name VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        echo "✅ Tabla businesses creada<br>";
    }
    
    echo "<br>";
    
    // Verificar datos de usuarios
    echo "<h2>Usuarios existentes:</h2>";
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, business_id, user_type, status FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        echo "- ID: " . $user['id'] . " | " . $user['first_name'] . " " . $user['last_name'] . " | " . $user['email'] . " | Business: " . ($user['business_id'] ?? 'NULL') . " | Tipo: " . $user['user_type'] . " | Status: " . $user['status'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><a href='login.php'>Volver al Login</a>";
?>