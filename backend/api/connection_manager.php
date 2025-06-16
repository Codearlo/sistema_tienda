<?php
/**
 * Gestor de Conexiones SSE
 * Proporciona información sobre conexiones activas y estadísticas
 */

require_once '../config/database.php';

class ConnectionManager {
    private $connectionsFile;
    
    public function __construct() {
        $this->connectionsFile = __DIR__ . '/../../tmp/sse_connections.json';
    }
    
    /**
     * Obtiene todas las conexiones activas
     */
    public function getActiveConnections() {
        $connections = $this->loadConnections();
        $now = time();
        $active = [];
        
        foreach ($connections as $connId => $conn) {
            if (($now - $conn['last_heartbeat']) <= 60) { // 60 segundos
                $conn['duration'] = $now - $conn['started_at'];
                $conn['last_seen'] = $now - $conn['last_heartbeat'];
                $active[$connId] = $conn;
            }
        }
        
        return $active;
    }
    
    /**
     * Obtiene estadísticas de conexiones
     */
    public function getConnectionStats() {
        $connections = $this->getActiveConnections();
        $stats = [
            'total_active' => count($connections),
            'by_business' => [],
            'average_duration' => 0,
            'oldest_connection' => null,
            'newest_connection' => null
        ];
        
        if (empty($connections)) {
            return $stats;
        }
        
        $totalDuration = 0;
        $oldestTime = PHP_INT_MAX;
        $newestTime = 0;
        
        foreach ($connections as $conn) {
            // Por business
            $businessId = $conn['business_id'];
            if (!isset($stats['by_business'][$businessId])) {
                $stats['by_business'][$businessId] = 0;
            }
            $stats['by_business'][$businessId]++;
            
            // Duraciones
            $totalDuration += $conn['duration'];
            
            if ($conn['started_at'] < $oldestTime) {
                $oldestTime = $conn['started_at'];
                $stats['oldest_connection'] = $conn;
            }
            
            if ($conn['started_at'] > $newestTime) {
                $newestTime = $conn['started_at'];
                $stats['newest_connection'] = $conn;
            }
        }
        
        $stats['average_duration'] = round($totalDuration / count($connections));
        
        return $stats;
    }
    
    /**
     * Fuerza el cierre de una conexión específica
     */
    public function forceCloseConnection($connectionId) {
        $connections = $this->loadConnections();
        
        if (isset($connections[$connectionId])) {
            unset($connections[$connectionId]);
            $this->saveConnections($connections);
            return true;
        }
        
        return false;
    }
    
    /**
     * Fuerza el cierre de todas las conexiones de un business
     */
    public function forceCloseBusinessConnections($businessId) {
        $connections = $this->loadConnections();
        $closed = 0;
        
        foreach ($connections as $connId => $conn) {
            if ($conn['business_id'] == $businessId) {
                unset($connections[$connId]);
                $closed++;
            }
        }
        
        if ($closed > 0) {
            $this->saveConnections($connections);
        }
        
        return $closed;
    }
    
    /**
     * Limpia conexiones inactivas
     */
    public function cleanupInactiveConnections($timeoutSeconds = 60) {
        $connections = $this->loadConnections();
        $now = time();
        $cleaned = 0;
        
        foreach ($connections as $connId => $conn) {
            if (($now - $conn['last_heartbeat']) > $timeoutSeconds) {
                unset($connections[$connId]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->saveConnections($connections);
        }
        
        return $cleaned;
    }
    
    /**
     * Obtiene información de una conexión específica
     */
    public function getConnectionInfo($connectionId) {
        $connections = $this->loadConnections();
        
        if (isset($connections[$connectionId])) {
            $conn = $connections[$connectionId];
            $now = time();
            
            $conn['duration'] = $now - $conn['started_at'];
            $conn['last_seen'] = $now - $conn['last_heartbeat'];
            $conn['is_active'] = $conn['last_seen'] <= 60;
            
            return $conn;
        }
        
        return null;
    }
    
    private function loadConnections() {
        if (!file_exists($this->connectionsFile)) {
            return [];
        }
        
        $data = file_get_contents($this->connectionsFile);
        return json_decode($data, true) ?: [];
    }
    
    private function saveConnections($connections) {
        $tmpDir = dirname($this->connectionsFile);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        file_put_contents(
            $this->connectionsFile, 
            json_encode($connections, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}

// ===== API ENDPOINTS =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_start();
    
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    $manager = new ConnectionManager();
    $action = $_GET['action'] ?? 'stats';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'stats':
            echo json_encode($manager->getConnectionStats());
            break;
            
        case 'list':
            echo json_encode($manager->getActiveConnections());
            break;
            
        case 'info':
            $connId = $_GET['connection_id'] ?? '';
            $info = $manager->getConnectionInfo($connId);
            if ($info) {
                echo json_encode($info);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Conexión no encontrada']);
            }
            break;
            
        case 'cleanup':
            $cleaned = $manager->cleanupInactiveConnections();
            echo json_encode(['cleaned' => $cleaned]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    session_start();
    
    // Verificar autenticación y permisos de admin
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    $manager = new ConnectionManager();
    $input = json_decode(file_get_contents('php://input'), true);
    
    header('Content-Type: application/json');
    
    if (isset($input['connection_id'])) {
        $success = $manager->forceCloseConnection($input['connection_id']);
        echo json_encode(['success' => $success]);
    } elseif (isset($input['business_id'])) {
        $closed = $manager->forceCloseBusinessConnections($input['business_id']);
        echo json_encode(['closed' => $closed]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetros inválidos']);
    }
}
?>