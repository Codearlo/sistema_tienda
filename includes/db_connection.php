<?php
/**
 * Conexión Simple a Base de Datos
 * Para archivos que necesitan PDO directo
 */

function getSimpleConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = 'localhost';
        $db_name = 'u347334547_inv_db';
        $username = 'u347334547_inv_user';
        $password = 'CH7322a#';
        
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
?>