<?php
/**
 * Configuración de Base de Datos - Treinta App
 * Archivo: config/database.php
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'u347334547_inv_db';
    private $username = 'u347334547_inv_user';
    private $password = 'CH7322a#';
    private $charset = 'utf8mb4';
    private $pdo;
    private $error;

    /**
     * Constructor - Establece la conexión automáticamente
     */
    public function __construct() {
        $this->connect();
    }

    /**
     * Establece la conexión a la base de datos
     */
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }

    /**
     * Obtiene la instancia PDO
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Ejecuta una consulta preparada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error en consulta SQL: " . $e->getMessage());
            throw new Exception("Error en la consulta a la base de datos");
        }
    }

    /**
     * Obtiene un solo registro
     */
    public function single($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Obtiene múltiples registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Inserta un registro y retorna el ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Actualiza registros
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }

    /**
     * Elimina registros
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    /**
     * Cuenta registros
     */
    public function count($table, $where = '1=1', $params = []) {
        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
        $result = $this->single($sql, $params);
        return $result['total'];
    }

    /**
     * Inicia una transacción
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Confirma una transacción
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Revierte una transacción
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * Obtiene el último error
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Cierra la conexión
     */
    public function close() {
        $this->pdo = null;
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

// Función para probar la conexión
function testDatabaseConnection() {
    try {
        $db = new Database();
        echo "✅ Conexión a la base de datos exitosa<br>";
        
        // Prueba básica
        $result = $db->single("SELECT 1 as test");
        if ($result['test'] == 1) {
            echo "✅ Consulta de prueba exitosa<br>";
        }
        
        return true;
    } catch (Exception $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
        return false;
    }
}
?>