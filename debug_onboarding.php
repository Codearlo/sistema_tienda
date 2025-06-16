<?php
session_start();
require_once 'backend/config/config.php';

echo "<h1>Debug Onboarding</h1>";

// Verificar sesión
echo "<h2>Información de Sesión:</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'No definido') . "<br>";
echo "Email: " . ($_SESSION['email'] ?? 'No definido') . "<br>";
echo "First Name: " . ($_SESSION['first_name'] ?? 'No definido') . "<br>";
echo "Last Name: " . ($_SESSION['last_name'] ?? 'No definido') . "<br>";
echo "Business ID: " . ($_SESSION['business_id'] ?? 'No definido') . "<br>";

// Verificar estructura de tablas
echo "<h2>Estructura de Tabla Users:</h2>";
try {
    $db = getDB();
    $columns = $db->fetchAll("DESCRIBE users");
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")" . ($column['Null'] == 'YES' ? ' NULL' : ' NOT NULL') . "<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<h2>Estructura de Tabla Businesses:</h2>";
try {
    $columns = $db->fetchAll("DESCRIBE businesses");
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")" . ($column['Null'] == 'YES' ? ' NULL' : ' NOT NULL') . "<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Verificar usuario actual
echo "<h2>Datos del Usuario Actual:</h2>";
try {
    if (isset($_SESSION['user_id'])) {
        $user = $db->single("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if ($user) {
            foreach ($user as $key => $value) {
                echo "- $key: " . ($value ?? 'NULL') . "<br>";
            }
        } else {
            echo "Usuario no encontrado<br>";
        }
    } else {
        echo "No hay user_id en sesión<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test específico de inserción
echo "<h2>Test de Inserción:</h2>";
try {
    $test_data = [
        'owner_id' => $_SESSION['user_id'] ?? 1,
        'business_name' => 'Test Store',
        'business_type' => 'retail',
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo "Intentando insertar: " . json_encode($test_data) . "<br>";
    
    $business_id = $db->insert("businesses", $test_data);
    
    if ($business_id) {
        echo "✅ Inserción exitosa. Business ID: $business_id<br>";
        
        // Test de actualización
        echo "<h3>Test de Actualización:</h3>";
        $update_result = $db->update("users", ['business_id' => $business_id], "id = ?", [$_SESSION['user_id']]);
        
        if ($update_result) {
            echo "✅ Actualización exitosa<br>";
        } else {
            echo "❌ Error en actualización<br>";
        }
        
        // Limpiar test
        $db->delete("businesses", "id = ?", [$business_id]);
        $db->update("users", ['business_id' => null], "id = ?", [$_SESSION['user_id']]);
        echo "Test limpiado<br>";
        
    } else {
        echo "❌ Error en inserción<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en test: " . $e->getMessage() . "<br>";
}

// Test del método update específico
echo "<h2>Test del Método Update:</h2>";
try {
    $result = $db->update("users", ['last_login' => date('Y-m-d H:i:s')], "id = ?", [$_SESSION['user_id']]);
    echo $result ? "✅ Update funciona correctamente" : "❌ Update falló";
} catch (Exception $e) {
    echo "❌ Error en update: " . $e->getMessage();
}
?>