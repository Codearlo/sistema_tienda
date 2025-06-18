<?php
/**
 * CLASE DE BASE DE DATOS OPTIMIZADA - CORREGIDA
 * Archivo: backend/config/database.php
 */

class Database {
    private $pdo;
    private static $instance = null;
    
    private function __construct() {
        $host = 'localhost';
        $db_name = 'u347334547_inv_db';
        $username = 'u347334547_inv_user';
        $password = 'CH7322a#';
        
        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$db_name};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Ejecutar consulta y obtener todos los resultados
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en la consulta");
        }
    }
    
    /**
     * Ejecutar consulta y obtener un solo resultado
     */
    public function single($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en la consulta");
        }
    }
    
    /**
     * Insertar registro
     */
    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            $stmt->execute();
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Insert error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al insertar registro");
        }
    }
    
    /**
     * Actualizar registro
     */
    public function update($table, $data, $whereCondition, $whereParams = []) {
        try {
            $setParams = [];
            $set = [];
            
            foreach ($data as $key => $value) {
                $set[] = "{$key} = ?";
                $setParams[] = $value;
            }
            $setClause = implode(', ', $set);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereCondition}";
            $finalParams = array_merge($setParams, $whereParams);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($finalParams);
            
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al actualizar registro");
        }
    }
    
    /**
     * Eliminar registro
     */
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al eliminar registro");
        }
    }
    
    /**
     * Contar registros
     */
    public function count($table, $where = '1=1', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Count error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al contar registros");
        }
    }
    
    /**
     * Verificar si existe un registro
     */
    public function exists($table, $where, $params = []) {
        try {
            $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Exists error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al verificar existencia");
        }
    }
    
    /**
     * Ejecutar consulta personalizada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en la consulta");
        }
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Obtener último ID insertado
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

/**
 * Función helper para obtener instancia de la base de datos
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Función helper para obtener conexión PDO directa
 */
function getPDO() {
    return Database::getInstance()->getPDO();
}
?>