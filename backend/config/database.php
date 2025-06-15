<?php
/**
 * Configuración de Base de Datos - Treinta App
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'u347334547_inv_db';
    private $username = 'u347334547_inv_user';
    private $password = 'CH7322a#';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    public function single($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = [];
            foreach ($data as $key => $value) {
                $set[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $set);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $params = array_merge($data, $whereParams);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception("Error en actualización: " . $e->getMessage());
        }
    }
}

// Función global para obtener la instancia de la base de datos
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}
?>