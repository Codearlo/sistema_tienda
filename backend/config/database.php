<?php
/**
 * Configuración de Base de Datos - Treinta App
 * Archivo: backend/config/database.php
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'u347334547_inv_db';
    private $username = 'u347334547_inv_user';
    private $password = 'CH7322a#';
    private $charset = 'utf8mb4';
    private $pdo;
    private static $instance = null;

    public function __construct() {
        $this->connect();
    }

    /**
     * Singleton pattern para una sola instancia de conexión
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establecer conexión a la base de datos
     */
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT         => true
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Configurar zona horaria
            $this->pdo->exec("SET time_zone = '-05:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
        }
    }

    /**
     * Obtener instancia PDO
     */
    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Preparar consulta
     */
    public function prepare($sql) {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            error_log("Prepare error: " . $e->getMessage());
            throw new Exception("Error en preparación de consulta");
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
            error_log("Single query error: " . $e->getMessage() . " SQL: " . $sql);
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
     * Insertar registro
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
            error_log("Insert error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al insertar registro");
        }
    }

    /**
     * Actualizar registro
     */
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
     * Ejecutar consulta personalizada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Custom query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en consulta personalizada");
        }
    }

    /**
     * Obtener registros con paginación
     */
    public function paginate($table, $where = '1=1', $params = [], $page = 1, $perPage = 20, $orderBy = 'id DESC') {
        try {
            $offset = ($page - 1) * $perPage;
            
            // Contar total de registros
            $totalSql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
            $totalStmt = $this->pdo->prepare($totalSql);
            $totalStmt->execute($params);
            $total = $totalStmt->fetch()['total'];
            
            // Obtener registros paginados
            $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderBy} LIMIT {$offset}, {$perPage}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ];
            
        } catch (PDOException $e) {
            error_log("Paginate error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error en paginación");
        }
    }

    /**
     * Buscar registros
     */
    public function search($table, $searchFields, $searchTerm, $where = '1=1', $params = [], $orderBy = 'id DESC') {
        try {
            $searchConditions = [];
            $searchParams = [];
            
            foreach ($searchFields as $field) {
                $searchConditions[] = "{$field} LIKE ?";
                $searchParams[] = "%{$searchTerm}%";
            }
            
            $searchClause = '(' . implode(' OR ', $searchConditions) . ')';
            $finalWhere = $where . ' AND ' . $searchClause;
            $finalParams = array_merge($params, $searchParams);
            
            $sql = "SELECT * FROM {$table} WHERE {$finalWhere} ORDER BY {$orderBy}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($finalParams);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error en búsqueda");
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
     * Limpiar datos (sanitizar)
     */
    public function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Ejecutar múltiples consultas en transacción
     */
    public function transaction($queries) {
        try {
            $this->beginTransaction();
            
            $results = [];
            foreach ($queries as $query) {
                $sql = $query['sql'];
                $params = $query['params'] ?? [];
                
                $stmt = $this->pdo->prepare($sql);
                $result = $stmt->execute($params);
                $results[] = $result;
            }
            
            $this->commit();
            return $results;
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("Transaction error: " . $e->getMessage());
            throw new Exception("Error en transacción múltiple");
        }
    }

    /**
     * Backup de tabla
     */
    public function backupTable($table, $filename = null) {
        try {
            if (!$filename) {
                $filename = $table . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
            }
            
            $sql = "SELECT * FROM {$table}";
            $stmt = $this->pdo->query($sql);
            $data = $stmt->fetchAll();
            
            $backup = "-- Backup de tabla {$table}\n";
            $backup .= "-- Creado el: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($data as $row) {
                $fields = implode(',', array_keys($row));
                $values = implode(',', array_map(function($v) {
                    return "'" . addslashes($v) . "'";
                }, array_values($row)));
                
                $backup .= "INSERT INTO {$table} ({$fields}) VALUES ({$values});\n";
            }
            
            return $backup;
            
        } catch (PDOException $e) {
            error_log("Backup error: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("Error al crear backup");
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
    return Database::getInstance();
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