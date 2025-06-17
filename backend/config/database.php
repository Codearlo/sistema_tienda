<?php
/**
 * CLASE DE BASE DE DATOS OPTIMIZADA
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
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
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
            throw new Exception("Error al insertar datos");
        }
    }
    
    /**
     * Actualizar registro
     */
    public function update($table, $data, $where, $params = []) {
        try {
            $setParts = [];
            foreach (array_keys($data) as $key) {
                $setParts[] = "{$key} = :{$key}";
            }
            $setClause = implode(', ', $setParts);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind data values
            foreach ($data as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            // Bind where parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al actualizar datos");
        }
    }
    
    /**
     * Eliminar registro
     */
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al eliminar datos");
        }
    }
    
    /**
     * Contar registros
     */
    public function count($table, $where = '1', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return intval($result['total']);
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
     * Obtener con paginación
     */
    public function paginate($sql, $params = [], $page = 1, $perPage = 20) {
        try {
            // Calcular offset
            $offset = ($page - 1) * $perPage;
            
            // Contar total de registros
            $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_query";
            $totalStmt = $this->pdo->prepare($countSql);
            $totalStmt->execute($params);
            $total = intval($totalStmt->fetch()['total']);
            
            // Obtener datos paginados
            $paginatedSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
            $dataStmt = $this->pdo->prepare($paginatedSql);
            $dataStmt->execute($params);
            $data = $dataStmt->fetchAll();
            
            return [
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Paginate error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en la paginación");
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
    
    /**
     * Preparar statement
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * Obtener información de la base de datos
     */
    public function getInfo() {
        try {
            $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $status = $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            
            return [
                'version' => $version,
                'status' => $status,
                'driver' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ];
        } catch (PDOException $e) {
            error_log("Database info error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar conexión
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

/**
 * Función helper para ejecutar consultas rápidas
 */
function dbQuery($sql, $params = []) {
    return Database::getInstance()->fetchAll($sql, $params);
}

/**
 * Función helper para obtener un solo registro
 */
function dbSingle($sql, $params = []) {
    return Database::getInstance()->single($sql, $params);
}

/**
 * Función helper para insertar
 */
function dbInsert($table, $data) {
    return Database::getInstance()->insert($table, $data);
}

/**
 * Función helper para actualizar
 */
function dbUpdate($table, $data, $where, $params = []) {
    return Database::getInstance()->update($table, $data, $where, $params);
}

/**
 * Función helper para eliminar
 */
function dbDelete($table, $where, $params = []) {
    return Database::getInstance()->delete($table, $where, $params);
}
?>