<?php
/**
 * CONFIGURACIÓN Y CLASE DE BASE DE DATOS
 * Archivo: backend/config/database.php
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'u347334547_invapp');
define('DB_USER', 'u347334547_invapp');
define('DB_PASS', 'CH7322a#');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $pdo;
    private static $instance = null;

    private function __construct() {
        $this->connect();
    }

    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Conectar a la base de datos
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false
            ];
            
            // Intentar conexión
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Verificar que la conexión funciona
            $this->pdo->query('SELECT 1');
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            
            // Intentar sin charset específico como fallback
            try {
                $dsn_fallback = "mysql:host={$this->host};dbname={$this->db_name}";
                $options_fallback = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 30
                ];
                
                $this->pdo = new PDO($dsn_fallback, $this->username, $this->password, $options_fallback);
                $this->pdo->exec("SET NAMES utf8mb4");
                
            } catch (PDOException $e2) {
                error_log("Fallback connection also failed: " . $e2->getMessage());
                throw new Exception("No se pudo conectar a la base de datos. Verifique las credenciales.");
            }
        }
    }

    /**
     * Obtener un solo registro
     */
    public function single($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Single query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en consulta a la base de datos");
        }
    }

    /**
     * Ejecutar consulta SQL simple
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en consulta a la base de datos");
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
            error_log("FetchAll query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en consulta a la base de datos");
        }
    }

    /**
     * Insertar registro - MÉTODO CORREGIDO
     */
    public function insert($table, $data) {
        try {
            $keys = array_keys($data);
            $fields = implode(',', $keys);
            $placeholders = ':' . implode(', :', $keys);
            
            $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute($data);
            
            if ($result) {
                return $this->pdo->lastInsertId();
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Insert error: " . $e->getMessage() . " Table: " . $table . " Data: " . json_encode($data));
            throw new Exception("Error al insertar registro: " . $e->getMessage());
        }
    }

    /**
     * Actualizar registro
     */
    public function update($table, $data, $whereCondition, $whereParams = []) {
        try {
            $set = [];
            foreach ($data as $key => $value) {
                $set[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $set);
            
            // Si whereCondition es un array asociativo (para compatibilidad)
            if (is_array($whereCondition)) {
                $whereKeys = array_keys($whereCondition);
                $whereClause = implode(' = ? AND ', $whereKeys) . ' = ?';
                $whereParams = array_values($whereCondition);
            } else {
                $whereClause = $whereCondition;
            }
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
            $params = array_merge($data, $whereParams);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
            
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
     * Obtener el último ID insertado
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Begin transaction error: " . $e->getMessage());
            throw new Exception("Error al iniciar transacción");
        }
    }

    /**
     * Confirmar transacción
     */
    public function commit() {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            error_log("Commit error: " . $e->getMessage());
            throw new Exception("Error al confirmar transacción");
        }
    }

    /**
     * Revertir transacción
     */
    public function rollback() {
        try {
            return $this->pdo->rollBack();
        } catch (PDOException $e) {
            error_log("Rollback error: " . $e->getMessage());
            throw new Exception("Error al revertir transacción");
        }
    }

    /**
     * Verificar conexión
     */
    public function isConnected() {
        try {
            if (!$this->pdo) {
                return false;
            }
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener información de la base de datos
     */
    public function getDatabaseInfo() {
        try {
            $version = $this->pdo->query('SELECT VERSION()')->fetchColumn();
            $charset = $this->pdo->query('SELECT @@character_set_database')->fetchColumn();
            $collation = $this->pdo->query('SELECT @@collation_database')->fetchColumn();
            
            return [
                'version' => $version,
                'charset' => $charset,
                'collation' => $collation,
                'database' => $this->db_name
            ];
        } catch (PDOException $e) {
            error_log("Database info error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cerrar conexión
     */
    public function close() {
        $this->pdo = null;
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}

// Función global para obtener la instancia de la base de datos
function getDB() {
    try {
        return Database::getInstance();
    } catch (Exception $e) {
        error_log("Error getting database instance: " . $e->getMessage());
        throw $e;
    }
}

// Función para probar la conexión
function testDatabaseConnection() {
    try {
        $db = getDB();
        if ($db->isConnected()) {
            return [
                'status' => 'success',
                'message' => 'Conexión exitosa',
                'info' => $db->getDatabaseInfo()
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'No se pudo establecer conexión'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
?>