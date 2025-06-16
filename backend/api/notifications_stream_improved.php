<?php
/**
 * Servidor SSE Mejorado para Notificaciones
 * Maneja conexiones de manera eficiente y evita sobrecarga
 */

session_start();
require_once '../config/database.php';

// ===== CONFIGURACIÓN =====
const MAX_CONNECTIONS = 10;
const HEARTBEAT_INTERVAL = 30; // segundos
const CONNECTION_TIMEOUT = 60; // segundos
const CLEANUP_INTERVAL = 300; // 5 minutos

class ImprovedNotificationStream {
    private $db;
    private $businessId;
    private $userId;
    private $connectionId;
    private $connectionsFile;
    
    public function __construct() {
        $this->db = getDB();
        $this->businessId = $_SESSION['business_id'] ?? null;
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->connectionId = uniqid('conn_', true);
        $this->connectionsFile = __DIR__ . '/../../tmp/sse_connections.json';
        
        // Crear directorio tmp si no existe
        $tmpDir = dirname($this->connectionsFile);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
    }
    
    public function handleConnection() {
        // Verificar autenticación
        if (!$this->businessId || !$this->userId) {
            $this->sendError('No autenticado');
            return;
        }
        
        // Limpiar conexiones antiguas
        $this->cleanupConnections();
        
        // Verificar límite de conexiones
        if ($this->getActiveConnectionCount() >= MAX_CONNECTIONS) {
            $this->sendError('Demasiadas conexiones activas. Intenta más tarde.');
            return;
        }
        
        // Configurar headers SSE
        $this->setupSSEHeaders();
        
        // Registrar conexión
        $this->registerConnection();
        
        // Iniciar stream
        $this->startStreaming();
    }
    
    private function setupSSEHeaders() {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Cache-Control');
        
        // Evitar buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_implicit_flush(true);
    }
    
    private function registerConnection() {
        $connections = $this->loadConnections();
        
        $connections[$this->connectionId] = [
            'business_id' => $this->businessId,
            'user_id' => $this->userId,
            'started_at' => time(),
            'last_heartbeat' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $this->saveConnections($connections);
        
        error_log("SSE: Nueva conexión registrada - {$this->connectionId} para business {$this->businessId}");
    }
    
    private function unregisterConnection() {
        $connections = $this->loadConnections();
        unset($connections[$this->connectionId]);
        $this->saveConnections($connections);
        
        error_log("SSE: Conexión cerrada - {$this->connectionId}");
    }
    
    private function loadConnections() {
        if (!file_exists($this->connectionsFile)) {
            return [];
        }
        
        $data = file_get_contents($this->connectionsFile);
        return json_decode($data, true) ?: [];
    }
    
    private function saveConnections($connections) {
        file_put_contents(
            $this->connectionsFile, 
            json_encode($connections, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
    
    private function cleanupConnections() {
        $connections = $this->loadConnections();
        $now = time();
        $cleaned = false;
        
        foreach ($connections as $connId => $conn) {
            // Remover conexiones que han estado inactivas por más del timeout
            if (($now - $conn['last_heartbeat']) > CONNECTION_TIMEOUT) {
                unset($connections[$connId]);
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            $this->saveConnections($connections);
            error_log("SSE: Conexiones inactivas limpiadas");
        }
    }
    
    private function getActiveConnectionCount() {
        $connections = $this->loadConnections();
        $now = time();
        $active = 0;
        
        foreach ($connections as $conn) {
            if (($now - $conn['last_heartbeat']) <= CONNECTION_TIMEOUT) {
                $active++;
            }
        }
        
        return $active;
    }
    
    private function updateHeartbeat() {
        $connections = $this->loadConnections();
        if (isset($connections[$this->connectionId])) {
            $connections[$this->connectionId]['last_heartbeat'] = time();
            $this->saveConnections($connections);
        }
    }
    
    private function startStreaming() {
        $lastNotificationCheck = 0;
        $lastHeartbeat = time();
        $lastCleanup = time();
        
        // Enviar confirmación de conexión
        $this->sendEvent('connected', [
            'message' => 'Conectado al sistema de notificaciones',
            'connection_id' => $this->connectionId,
            'timestamp' => time()
        ]);
        
        while (connection_status() == CONNECTION_NORMAL && !connection_aborted()) {
            $now = time();
            
            try {
                // Verificar nuevas notificaciones cada 2 segundos
                if ($now - $lastNotificationCheck >= 2) {
                    $this->checkAndSendNotifications($lastNotificationCheck);
                    $lastNotificationCheck = $now;
                }
                
                // Enviar heartbeat cada HEARTBEAT_INTERVAL segundos
                if ($now - $lastHeartbeat >= HEARTBEAT_INTERVAL) {
                    $this->sendHeartbeat();
                    $this->updateHeartbeat();
                    $lastHeartbeat = $now;
                }
                
                // Cleanup periódico
                if ($now - $lastCleanup >= CLEANUP_INTERVAL) {
                    $this->cleanupConnections();
                    $lastCleanup = $now;
                }
                
                // Dormir para reducir uso de CPU
                usleep(500000); // 0.5 segundos
                
            } catch (Exception $e) {
                error_log("SSE Error: " . $e->getMessage());
                $this->sendError('Error interno del servidor');
                break;
            }
        }
        
        // Cleanup al cerrar
        $this->unregisterConnection();
    }
    
    private function checkAndSendNotifications($since) {
        try {
            // Obtener notificaciones nuevas desde la última verificación
            $sql = "
                SELECT * FROM notifications 
                WHERE business_id = ? 
                AND created_at > FROM_UNIXTIME(?) 
                AND status = 'unread'
                ORDER BY created_at ASC
                LIMIT 10
            ";
            
            $notifications = $this->db->fetchAll($sql, [$this->businessId, $since]);
            
            foreach ($notifications as $notification) {
                $this->sendEvent('notification', [
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'data' => json_decode($notification['data'] ?? '{}', true),
                    'timestamp' => $notification['created_at'],
                    'priority' => $notification['priority'] ?? 'normal'
                ]);
                
                // Marcar como enviada (opcional)
                $this->db->execute(
                    "UPDATE notifications SET status = 'sent' WHERE id = ?",
                    [$notification['id']]
                );
            }
            
        } catch (Exception $e) {
            error_log("Error checking notifications: " . $e->getMessage());
        }
    }
    
    private function sendHeartbeat() {
        $this->sendEvent('heartbeat', [
            'timestamp' => time(),
            'connection_id' => $this->connectionId,
            'active_connections' => $this->getActiveConnectionCount()
        ]);
    }
    
    private function sendEvent($event, $data) {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    private function sendError($message) {
        http_response_code(400);
        $this->sendEvent('error', [
            'message' => $message,
            'timestamp' => time()
        ]);
    }
}

// ===== MANEJO DE ERRORES Y TIMEOUTS =====
set_time_limit(0); // Sin límite de tiempo
ignore_user_abort(false); // Cerrar cuando el cliente se desconecta

// Manejo de errores
set_error_handler(function($severity, $message, $file, $line) {
    error_log("SSE PHP Error: {$message} in {$file}:{$line}");
    return false;
});

// Manejo de señales para cleanup
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        error_log("SSE: Recibida señal SIGTERM");
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() {
        error_log("SSE: Recibida señal SIGINT");
        exit(0);
    });
}

// ===== INICIALIZACIÓN =====
try {
    $stream = new ImprovedNotificationStream();
    $stream->handleConnection();
} catch (Exception $e) {
    error_log("SSE Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Error interno del servidor']) . "\n\n";
    flush();
}
?>