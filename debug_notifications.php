<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Sistema de Notificaciones</h1>";

// Test 1: Verificar sesión
echo "<h2>1. Verificar Sesión</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ user_id: " . $_SESSION['user_id'] . "<br>";
    echo "✅ business_id: " . ($_SESSION['business_id'] ?? 'NO DEFINIDO') . "<br>";
    echo "✅ user_name: " . ($_SESSION['user_name'] ?? 'NO DEFINIDO') . "<br>";
} else {
    echo "❌ No hay sesión activa. <a href='login.php'>Ir al login</a><br>";
    exit();
}

// Test 2: Cargar archivos
echo "<h2>2. Cargar Archivos</h2>";
try {
    require_once 'backend/config/config.php';
    echo "✅ config.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error cargando config.php: " . $e->getMessage() . "<br>";
}

try {
    require_once 'backend/config/database.php';
    echo "✅ database.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error cargando database.php: " . $e->getMessage() . "<br>";
}

// Test 3: Conexión a BD
echo "<h2>3. Conexión a Base de Datos</h2>";
try {
    $db = getDB();
    echo "✅ Conexión a BD exitosa<br>";
    
    // Verificar si existe la tabla notifications
    $tables = $db->fetchAll("SHOW TABLES LIKE 'notifications'");
    if (count($tables) > 0) {
        echo "✅ Tabla 'notifications' existe<br>";
        
        // Verificar estructura
        $columns = $db->fetchAll("DESCRIBE notifications");
        echo "📋 Columnas de la tabla:<br>";
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
        }
    } else {
        echo "❌ Tabla 'notifications' NO existe<br>";
        echo "<h3>Crear tabla notifications:</h3>";
        echo "<pre>";
        echo "CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}

// Test 4: Cargar NotificationHelper
echo "<h2>4. NotificationHelper</h2>";
try {
    if (file_exists('backend/notifications/NotificationHelper.php')) {
        require_once 'backend/notifications/NotificationHelper.php';
        echo "✅ NotificationHelper.php existe<br>";
        
        $helper = new NotificationHelper();
        echo "✅ NotificationHelper instanciado<br>";
        
        // Test crear notificación simple
        if (isset($_SESSION['business_id'])) {
            $result = $helper->create(
                $_SESSION['business_id'],
                'test',
                'Test Debug',
                'Notificación de prueba desde debug',
                'medium'
            );
            
            if ($result) {
                echo "✅ Notificación de test creada con ID: $result<br>";
            } else {
                echo "❌ Error creando notificación de test<br>";
            }
        }
        
    } else {
        echo "❌ Archivo NotificationHelper.php NO existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error con NotificationHelper: " . $e->getMessage() . "<br>";
}

// Test 5: Verificar directorio backend/notifications
echo "<h2>5. Verificar Archivos</h2>";
$files_to_check = [
    'backend/notifications/' => 'Directorio notifications',
    'backend/notifications/NotificationHelper.php' => 'NotificationHelper.php',
    'backend/notifications/sse.php' => 'sse.php',
    'assets/js/notifications.js' => 'notifications.js'
];

foreach ($files_to_check as $path => $name) {
    if (file_exists($path)) {
        echo "✅ $name existe<br>";
    } else {
        echo "❌ $name NO existe en: $path<br>";
    }
}

echo "<h2>✅ Debug Completado</h2>";
echo "<p><a href='test_notifications.php'>Probar test_notifications.php</a></p>";
?>