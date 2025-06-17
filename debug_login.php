<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Login</h1>";

// Test 1: Configuración básica
echo "<h2>1. Configuración Básica</h2>";
try {
    require_once 'backend/config/config.php';
    echo "✅ config.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error cargando config.php: " . $e->getMessage() . "<br>";
}

// Test 2: Base de datos
echo "<h2>2. Test Base de Datos</h2>";
try {
    require_once 'backend/config/database.php';
    echo "✅ database.php cargado<br>";
    
    $db = getDB();
    echo "✅ getDB() funciona<br>";
    
    if ($db) {
        echo "✅ Conexión establecida<br>";
        
        // Test query simple
        $test = $db->single("SELECT 1 as test");
        echo "✅ Query test: " . ($test ? $test['test'] : 'NULL') . "<br>";
        
        // Test tabla users
        $userCount = $db->single("SELECT COUNT(*) as total FROM users");
        echo "✅ Usuarios en BD: " . $userCount['total'] . "<br>";
        
    } else {
        echo "❌ No se pudo obtener conexión<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error con BD: " . $e->getMessage() . "<br>";
}

// Test 3: Funciones auxiliares
echo "<h2>3. Test Funciones</h2>";

// Solo definir si no existe
if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    echo "✅ cleanInput() definida<br>";
} else {
    echo "✅ cleanInput() ya existe<br>";
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    echo "✅ isLoggedIn() definida<br>";
} else {
    echo "✅ isLoggedIn() ya existe<br>";
}

// Test 4: Test login directo
echo "<h2>4. Test Login Manual</h2>";
$test_email = 'admin@treinta.com';
$test_password = 'admin123';

try {
    $db = getDB();
    
    $user = $db->single(
        "SELECT u.*, b.id as business_id, b.name as business_name 
         FROM users u 
         LEFT JOIN businesses b ON u.business_id = b.id 
         WHERE u.email = ? AND u.status = 1", 
        [$test_email]
    );
    
    if ($user) {
        echo "✅ Usuario encontrado: " . $user['first_name'] . " " . $user['last_name'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Business ID: " . ($user['business_id'] ?? 'NULL') . "<br>";
        
        // Test verificación de contraseña
        if (password_verify($test_password, $user['password'])) {
            echo "✅ Contraseña verificada correctamente<br>";
        } else {
            echo "❌ Contraseña incorrecta<br>";
            echo "Hash almacenado: " . substr($user['password'], 0, 20) . "...<br>";
            echo "Test con hash nuevo: " . password_hash($test_password, PASSWORD_DEFAULT) . "<br>";
        }
        
    } else {
        echo "❌ Usuario no encontrado<br>";
        
        // Mostrar usuarios existentes
        $users = $db->fetchAll("SELECT email, first_name, last_name, status FROM users LIMIT 5");
        echo "Usuarios existentes:<br>";
        foreach ($users as $u) {
            echo "- " . $u['email'] . " (" . $u['first_name'] . ", status: " . $u['status'] . ")<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error en test login: " . $e->getMessage() . "<br>";
}

// Test 5: Información del sistema
echo "<h2>5. Información del Sistema</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO disponible: " . (extension_loaded('pdo') ? 'Sí' : 'No') . "<br>";
echo "PDO MySQL disponible: " . (extension_loaded('pdo_mysql') ? 'Sí' : 'No') . "<br>";
echo "Session status: " . session_status() . "<br>";

// Test 6: Crear usuario de prueba si no existe
echo "<h2>6. Crear Usuario de Prueba</h2>";
try {
    $db = getDB();
    
    $existing = $db->single("SELECT id FROM users WHERE email = ?", ['admin@treinta.com']);
    
    if (!$existing) {
        echo "Creando usuario de prueba...<br>";
        
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $user_id = $db->insert('users', [
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@treinta.com',
            'password' => $password_hash,
            'user_type' => 'admin',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($user_id) {
            echo "✅ Usuario admin creado con ID: $user_id<br>";
        } else {
            echo "❌ Error creando usuario<br>";
        }
    } else {
        echo "✅ Usuario admin ya existe<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error creando usuario: " . $e->getMessage() . "<br>";
}

echo "<h2>✅ Debug Completado</h2>";
echo "<p><a href='login.php'>Ir al Login</a></p>";
?>