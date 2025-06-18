<?php
// Archivo: backend/api/test.php
// Archivo de prueba simple para verificar que la carpeta API funciona

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'La carpeta API funciona correctamente',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);
?>