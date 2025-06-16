<?php
session_start();
require_once 'backend/config/config.php';

echo "<h1>Verificar Datos del Negocio</h1>";

if (!isset($_SESSION['business_id'])) {
    echo "Error: No hay business_id en sesión";
    exit();
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    echo "<h2>Datos en la tabla 'businesses':</h2>";
    $business = $db->single("SELECT * FROM businesses WHERE id = ?", [$business_id]);
    
    if ($business) {
        foreach ($business as $key => $value) {
            echo "<strong>$key:</strong> " . ($value ? htmlspecialchars($value) : '<em>NULL/vacío</em>') . "<br>";
        }
    } else {
        echo "❌ No se encontró el negocio con ID: $business_id";
    }
    
    echo "<h2>Configuraciones en la tabla 'settings':</h2>";
    $settings = $db->fetchAll("SELECT * FROM settings WHERE business_id = ?", [$business_id]);
    
    if ($settings) {
        foreach ($settings as $setting) {
            echo "<strong>" . $setting['setting_key'] . ":</strong> " . htmlspecialchars($setting['setting_value']) . "<br>";
        }
    } else {
        echo "❌ No se encontraron configuraciones para este negocio";
    }
    
    echo "<h2>Datos de sesión:</h2>";
    echo "<strong>user_id:</strong> " . $_SESSION['user_id'] . "<br>";
    echo "<strong>business_id:</strong> " . $_SESSION['business_id'] . "<br>";
    echo "<strong>business_name:</strong> " . ($_SESSION['business_name'] ?? 'No definido') . "<br>";
    echo "<strong>email:</strong> " . ($_SESSION['email'] ?? 'No definido') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>