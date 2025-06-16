<?php
/**
 * Configuración del Sistema de Notificaciones Mejorado
 */

// ===== CONFIGURACIÓN SSE =====
define('SSE_MAX_CONNECTIONS', 10);
define('SSE_HEARTBEAT_INTERVAL', 30); // segundos
define('SSE_CONNECTION_TIMEOUT', 60); // segundos
define('SSE_CLEANUP_INTERVAL', 300); // 5 minutos
define('SSE_RECONNECT_DELAY_BASE', 1000); // milisegundos
define('SSE_MAX_RECONNECT_ATTEMPTS', 5);

// ===== CONFIGURACIÓN DE NOTIFICACIONES =====
define('NOTIFICATION_THROTTLE_TIME', 3000); // milisegundos
define('NOTIFICATION_MAX_VISIBLE', 3);
define('NOTIFICATION_AUTO_HIDE_DELAY', 5000); // milisegundos
define('NOTIFICATION_SOUND_ENABLED', true);

// ===== CONFIGURACIÓN DE BROADCAST CHANNEL =====
define('BROADCAST_CHANNEL_NAME', 'treinta_notifications');
define('LEADER_HEARTBEAT_INTERVAL', 10000); // milisegundos
define('LEADER_TIMEOUT', 30000); // milisegundos

// ===== TIPOS DE NOTIFICACIÓN =====
define('NOTIFICATION_TYPES', [
    'success' => [
        'color' => '#10b981',
        'icon' => '✅',
        'priority' => 'normal',
        'sound' => true
    ],
    'error' => [
        'color' => '#ef4444', 
        'icon' => '❌',
        'priority' => 'high',
        'sound' => true
    ],
    'warning' => [
        'color' => '#f59e0b',
        'icon' => '⚠️', 
        'priority' => 'normal',
        'sound' => true
    ],
    'info' => [
        'color' => '#3b82f6',
        'icon' => 'ℹ️',
        'priority' => 'low',
        'sound' => false
    ],
    'sale' => [
        'color' => '#8b5cf6',
        'icon' => '💰',
        'priority' => 'normal', 
        'sound' => true
    ],
    'stock' => [
        'color' => '#f97316',
        'icon' => '📦',
        'priority' => 'normal',
        'sound' => true
    ],
    'payment' => [
        'color' => '#059669',
        'icon' => '💳',
        'priority' => 'normal',
        'sound' => true
    ]
]);

// ===== FUNCIONES DE CONFIGURACIÓN =====

/**
 * Obtiene la configuración completa de notificaciones para JavaScript
 */
function getNotificationConfig() {
    return [
        'sse' => [
            'max_connections' => SSE_MAX_CONNECTIONS,
            'heartbeat_interval' => SSE_HEARTBEAT_INTERVAL * 1000, // convertir a ms
            'connection_timeout' => SSE_CONNECTION_TIMEOUT * 1000,
            'reconnect_delay_base' => SSE_RECONNECT_DELAY_BASE,
            'max_reconnect_attempts' => SSE_MAX_RECONNECT_ATTEMPTS
        ],
        'notifications' => [
            'throttle_time' => NOTIFICATION_THROTTLE_TIME,
            'max_visible' => NOTIFICATION_MAX_VISIBLE,
            'auto_hide_delay' => NOTIFICATION_AUTO_HIDE_DELAY,
            'sound_enabled' => NOTIFICATION_SOUND_ENABLED
        ],
        'broadcast' => [
            'channel_name' => BROADCAST_CHANNEL_NAME,
            'leader_heartbeat_interval' => LEADER_HEARTBEAT_INTERVAL,
            'leader_timeout' => LEADER_TIMEOUT
        ],
        'types' => NOTIFICATION_TYPES
    ];
}

/**
 * Genera el script de configuración para incluir en HTML
 */
function getNotificationConfigScript() {
    $config = getNotificationConfig();
    return '<script>window.NOTIFICATION_CONFIG = ' . json_encode($config) . ';</script>';
}

/**
 * Verifica si las notificaciones están habilitadas para un usuario
 */
function areNotificationsEnabledForUser($userId) {
    try {
        $db = getDB();
        $settings = $db->fetchOne(
            "SELECT notification_settings FROM users WHERE id = ?",
            [$userId]
        );
        
        if ($settings && $settings['notification_settings']) {
            $notificationSettings = json_decode($settings['notification_settings'], true);
            return $notificationSettings['enabled'] ?? true;
        }
        
        return true; // Por defecto habilitadas
    } catch (Exception $e) {
        error_log("Error checking notification settings: " . $e->getMessage());
        return true;
    }
}

/**
 * Obtiene las preferencias de notificación de un usuario
 */
function getUserNotificationPreferences($userId) {
    try {
        $db = getDB();
        $settings = $db->fetchOne(
            "SELECT notification_settings FROM users WHERE id = ?", 
            [$userId]
        );
        
        $defaults = [
            'enabled' => true,
            'browser_notifications' => true,
            'sound' => true,
            'types' => [
                'sales' => true,
                'stock' => true,
                'payments' => true,
                'errors' => true,
                'warnings' => true
            ]
        ];
        
        if ($settings && $settings['notification_settings']) {
            $userSettings = json_decode($settings['notification_settings'], true);
            return array_merge($defaults, $userSettings);
        }
        
        return $defaults;
    } catch (Exception $e) {
        error_log("Error getting notification preferences: " . $e->getMessage());
        return $defaults ?? [
            'enabled' => true,
            'browser_notifications' => true,
            'sound' => true,
            'types' => [
                'sales' => true,
                'stock' => true, 
                'payments' => true,
                'errors' => true,
                'warnings' => true
            ]
        ];
    }
}

/**
 * Actualiza las preferencias de notificación de un usuario
 */
function updateUserNotificationPreferences($userId, $preferences) {
    try {
        $db = getDB();
        return $db->execute(
            "UPDATE users SET notification_settings = ? WHERE id = ?",
            [json_encode($preferences), $userId]
        );
    } catch (Exception $e) {
        error_log("Error updating notification preferences: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si el navegador del usuario soporta las características necesarias
 */
function getBrowserCompatibility() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Detectar características basado en User-Agent (básico)
    $hasEventSource = true; // Disponible en todos los navegadores modernos
    $hasBroadcastChannel = true; // Disponible en navegadores modernos
    $hasNotifications = true; // API de notificaciones disponible
    $hasVisibilityAPI = true; // Page Visibility API
    
    // Detectar navegadores muy antiguos
    if (preg_match('/MSIE [6-9]\./', $userAgent)) {
        $hasEventSource = false;
        $hasBroadcastChannel = false;
        $hasNotifications = false;
        $hasVisibilityAPI = false;
    }
    
    return [
        'event_source' => $hasEventSource,
        'broadcast_channel' => $hasBroadcastChannel,
        'notifications' => $hasNotifications,
        'visibility_api' => $hasVisibilityAPI,
        'is_compatible' => $hasEventSource && $hasBroadcastChannel
    ];
}

/**
 * Genera el HTML necesario para incluir el sistema de notificaciones
 */
function includeNotificationSystem() {
    $compatibility = getBrowserCompatibility();
    
    if (!$compatibility['is_compatible']) {
        return '<!-- Sistema de notificaciones no compatible con este navegador -->';
    }
    
    $configScript = getNotificationConfigScript();
    
    return $configScript . '
    <script src="assets/js/notifications-improved.js"></script>
    <style>
        /* Estilos para notificaciones mejoradas ya incluidos en el JS */
    </style>';
}

/**
 * Obtiene estadísticas del sistema de notificaciones
 */
function getNotificationSystemStats() {
    try {
        $connectionsFile = __DIR__ . '/../tmp/sse_connections.json';
        
        if (!file_exists($connectionsFile)) {
            return [
                'active_connections' => 0,
                'total_sent_today' => 0,
                'error_rate' => 0,
                'avg_response_time' => 0
            ];
        }
        
        $connections = json_decode(file_get_contents($connectionsFile), true) ?: [];
        $now = time();
        $activeConnections = 0;
        
        foreach ($connections as $conn) {
            if (($now - $conn['last_heartbeat']) <= SSE_CONNECTION_TIMEOUT) {
                $activeConnections++;
            }
        }
        
        // Obtener estadísticas de la base de datos
        $db = getDB();
        $today = date('Y-m-d');
        
        $sentToday = $db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE DATE(created_at) = ? AND status = 'sent'",
            [$today]
        )['count'] ?? 0;
        
        return [
            'active_connections' => $activeConnections,
            'total_sent_today' => $sentToday,
            'error_rate' => 0, // Implementar si es necesario
            'avg_response_time' => 0 // Implementar si es necesario
        ];
        
    } catch (Exception $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        return [
            'active_connections' => 0,
            'total_sent_today' => 0,
            'error_rate' => 0,
            'avg_response_time' => 0
        ];
    }
}
?>