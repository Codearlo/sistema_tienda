<?php
session_start();

require_once 'includes/onboarding_middleware.php';
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/notification_config.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Configuración de Notificaciones';
$currentPage = 'settings';
$message = null;

// Manejar actualizaciones de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $preferences = [
            'enabled' => isset($_POST['enabled']),
            'browser_notifications' => isset($_POST['browser_notifications']),
            'sound' => isset($_POST['sound']),
            'types' => [
                'sales' => isset($_POST['type_sales']),
                'stock' => isset($_POST['type_stock']),
                'payments' => isset($_POST['type_payments']),
                'errors' => isset($_POST['type_errors']),
                'warnings' => isset($_POST['type_warnings'])
            ]
        ];
        
        if (updateUserNotificationPreferences($_SESSION['user_id'], $preferences)) {
            $message = ['type' => 'success', 'text' => 'Configuración actualizada correctamente'];
        } else {
            $message = ['type' => 'error', 'text' => 'Error al actualizar la configuración'];
        }
    } catch (Exception $e) {
        $message = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
    }
}

// Obtener configuración actual
$currentPreferences = getUserNotificationPreferences($_SESSION['user_id']);
$systemStats = getNotificationSystemStats();

include 'includes/header_improved.php';
?>

<div class="settings-container">
    <div class="settings-header">
        <h1>
            <i class="fas fa-bell"></i>
            Configuración de Notificaciones
        </h1>
        <p>Configura cómo y cuándo recibir notificaciones del sistema</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?>">
            <i class="fas fa-<?php echo $message['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <div class="settings-grid">
        <!-- Configuración Principal -->
        <div class="settings-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-cog"></i>
                    Configuración General
                </h2>
            </div>
            
            <form method="POST" class="settings-form">
                <div class="form-section">
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="enabled" <?php echo $currentPreferences['enabled'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Activar notificaciones</span>
                        </label>
                        <p class="form-help">Habilita o deshabilita completamente las notificaciones</p>
                    </div>

                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="browser_notifications" <?php echo $currentPreferences['browser_notifications'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Notificaciones del navegador</span>
                        </label>
                        <p class="form-help">Muestra notificaciones nativas del sistema operativo</p>
                    </div>

                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="sound" <?php echo $currentPreferences['sound'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Sonido de notificaciones</span>
                        </label>
                        <p class="form-help">Reproduce un sonido cuando llegan nuevas notificaciones</p>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Tipos de Notificaciones</h3>
                    
                    <div class="notification-types">
                        <div class="notification-type">
                            <label class="toggle-switch">
                                <input type="checkbox" name="type_sales" <?php echo $currentPreferences['types']['sales'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <i class="fas fa-shopping-cart"></i>
                                    Ventas
                                </span>
                            </label>
                            <p>Notificaciones de nuevas ventas y transacciones</p>
                        </div>

                        <div class="notification-type">
                            <label class="toggle-switch">
                                <input type="checkbox" name="type_stock" <?php echo $currentPreferences['types']['stock'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <i class="fas fa-boxes"></i>
                                    Inventario
                                </span>
                            </label>
                            <p>Alertas de stock bajo y cambios de inventario</p>
                        </div>

                        <div class="notification-type">
                            <label class="toggle-switch">
                                <input type="checkbox" name="type_payments" <?php echo $currentPreferences['types']['payments'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <i class="fas fa-credit-card"></i>
                                    Pagos
                                </span>
                            </label>
                            <p>Confirmaciones de pagos y transacciones financieras</p>
                        </div>

                        <div class="notification-type">
                            <label class="toggle-switch">
                                <input type="checkbox" name="type_errors" <?php echo $currentPreferences['types']['errors'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Errores
                                </span>
                            </label>
                            <p>Alertas de errores críticos del sistema</p>
                        </div>

                        <div class="notification-type">
                            <label class="toggle-switch">
                                <input type="checkbox" name="type_warnings" <?php echo $currentPreferences['types']['warnings'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">
                                    <i class="fas fa-info-circle"></i>
                                    Advertencias
                                </span>
                            </label>
                            <p>Avisos importantes y recordatorios</p>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Configuración
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="testNotification()">
                        <i class="fas fa-test-tube"></i>
                        Probar Notificación
                    </button>
                </div>
            </form>
        </div>

        <!-- Estado del Sistema -->
        <div class="settings-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    Estado del Sistema
                </h2>
            </div>
            
            <div class="system-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $systemStats['active_connections']; ?></div>
                    <div class="stat-label">Conexiones Activas</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo $systemStats['total_sent_today']; ?></div>
                    <div class="stat-label">Notificaciones Hoy</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value" id="connectionStatus">
                        <i class="fas fa-circle text-gray"></i>
                        Verificando...
                    </div>
                    <div class="stat-label">Estado de Conexión</div>
                </div>
            </div>

            <div class="connection-info" id="connectionInfo" style="display: none;">
                <h3>Información de Conexión</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Es Líder:</span>
                        <span class="info-value" id="isLeader">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID de Conexión:</span>
                        <span class="info-value" id="connectionId">-</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Intentos de Reconexión:</span>
                        <span class="info-value" id="reconnectAttempts">-</span>
                    </div>
                </div>
            </div>

            <div class="system-actions">
                <button type="button" class="btn btn-outline" onclick="refreshConnectionStatus()">
                    <i class="fas fa-refresh"></i>
                    Actualizar Estado
                </button>
                
                <button type="button" class="btn btn-outline" onclick="forceReconnect()">
                    <i class="fas fa-plug"></i>
                    Reconectar
                </button>
            </div>
        </div>

        <!-- Configuración Avanzada -->
        <div class="settings-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-wrench"></i>
                    Configuración Avanzada
                </h2>
            </div>
            
            <div class="advanced-settings">
                <div class="setting-item">
                    <label>Tiempo de throttling (ms):</label>
                    <span><?php echo NOTIFICATION_THROTTLE_TIME; ?></span>
                </div>
                
                <div class="setting-item">
                    <label>Máximo notificaciones visibles:</label>
                    <span><?php echo NOTIFICATION_MAX_VISIBLE; ?></span>
                </div>
                
                <div class="setting-item">
                    <label>Auto-ocultar después de (ms):</label>
                    <span><?php echo NOTIFICATION_AUTO_HIDE_DELAY; ?></span>
                </div>
                
                <div class="setting-item">
                    <label>Intervalo de heartbeat (s):</label>
                    <span><?php echo SSE_HEARTBEAT_INTERVAL; ?></span>
                </div>
            </div>

            <div class="browser-compatibility">
                <h3>Compatibilidad del Navegador</h3>
                <div class="compatibility-list" id="browserCompatibility">
                    <!-- Se llena con JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.settings-header {
    margin-bottom: 30px;
}

.settings-header h1 {
    margin: 0 0 10px 0;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-header p {
    color: #6b7280;
    margin: 0;
}

.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

.settings-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    background: #f8fafc;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.card-header h2 {
    margin: 0;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
}

.settings-form {
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin: 0 0 15px 0;
    color: #374151;
    font-size: 16px;
}

.form-group {
    margin-bottom: 20px;
}

.toggle-switch {
    display: flex;
    align-items: center;
    cursor: pointer;
    gap: 12px;
}

.toggle-switch input {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 24px;
    background: #d1d5db;
    border-radius: 12px;
    position: relative;
    transition: background 0.3s;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: transform 0.3s;
}

.toggle-switch input:checked + .toggle-slider {
    background: #3b82f6;
}

.toggle-switch input:checked + .toggle-slider::before {
    transform: translateX(20px);
}

.toggle-label {
    font-weight: 500;
    color: #374151;
}

.form-help {
    margin: 5px 0 0 56px;
    font-size: 14px;
    color: #6b7280;
}

.notification-types {
    display: grid;
    gap: 15px;
}

.notification-type {
    padding: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

.notification-type .toggle-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-type p {
    margin: 5px 0 0 56px;
    font-size: 14px;
    color: #6b7280;
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.system-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    padding: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
}

.connection-info {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
}

.info-grid {
    display: grid;
    gap: 10px;
    margin-top: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.info-label {
    font-weight: 500;
    color: #374151;
}

.info-value {
    color: #6b7280;
    font-family: monospace;
}

.system-actions {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 10px;
}

.advanced-settings {
    padding: 20px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.setting-item label {
    font-weight: 500;
    color: #374151;
}

.setting-item span {
    color: #6b7280;
    font-family: monospace;
}

.browser-compatibility {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
}

.compatibility-list {
    margin-top: 15px;
}

.compatibility-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.text-green { color: #10b981; }
.text-red { color: #ef4444; }
.text-gray { color: #6b7280; }

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-outline {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
}

.btn-outline:hover {
    background: #f9fafb;
}
</style>

<script>
// Actualizar estado de conexión
function refreshConnectionStatus() {
    const statusEl = document.getElementById('connectionStatus');
    const infoEl = document.getElementById('connectionInfo');
    
    if (window.notificationSystem) {
        const info = window.notificationSystem.getConnectionInfo();
        
        if (info.isConnected) {
            statusEl.innerHTML = '<i class="fas fa-circle text-green"></i> Conectado';
        } else {
            statusEl.innerHTML = '<i class="fas fa-circle text-red"></i> Desconectado';
        }
        
        // Mostrar información detallada
        document.getElementById('isLeader').textContent = info.isLeader ? 'Sí' : 'No';
        document.getElementById('connectionId').textContent = info.leaderId || '-';
        document.getElementById('reconnectAttempts').textContent = info.reconnectAttempts || '0';
        
        infoEl.style.display = 'block';
    } else {
        statusEl.innerHTML = '<i class="fas fa-circle text-gray"></i> No disponible';
        infoEl.style.display = 'none';
    }
}

// Forzar reconexión
function forceReconnect() {
    if (window.notificationSystem) {
        window.notificationSystem.disconnect();
        setTimeout(() => {
            if (window.notificationSystem.isLeader) {
                window.notificationSystem.connect();
            }
        }, 1000);
    }
}

// Probar notificación
function testNotification() {
    if (window.notificationSystem) {
        window.notificationSystem.showNotification({
            type: 'info',
            title: 'Notificación de Prueba',
            message: 'Esta es una notificación de prueba del sistema.',
            timestamp: new Date().toISOString()
        });
    } else {
        alert('Sistema de notificaciones no disponible');
    }
}

// Mostrar compatibilidad del navegador
function showBrowserCompatibility() {
    const container = document.getElementById('browserCompatibility');
    
    if (window.BROWSER_COMPATIBILITY) {
        const compat = window.BROWSER_COMPATIBILITY;
        const items = [
            { label: 'Server-Sent Events', supported: compat.event_source },
            { label: 'Broadcast Channel API', supported: compat.broadcast_channel },
            { label: 'Notifications API', supported: compat.notifications },
            { label: 'Page Visibility API', supported: compat.visibility_api }
        ];
        
        container.innerHTML = items.map(item => `
            <div class="compatibility-item">
                <span>${item.label}</span>
                <span class="${item.supported ? 'text-green' : 'text-red'}">
                    <i class="fas fa-${item.supported ? 'check' : 'times'}"></i>
                    ${item.supported ? 'Soportado' : 'No soportado'}
                </span>
            </div>
        `).join('');
    }
}

// Inicializar página
document.addEventListener('DOMContentLoaded', function() {
    refreshConnectionStatus();
    showBrowserCompatibility();
    
    // Actualizar estado cada 10 segundos
    setInterval(refreshConnectionStatus, 10000);
});
</script>

<?php include 'includes/footer.php'; ?>