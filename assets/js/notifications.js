/**
 * Sistema de Notificaciones en Tiempo Real
 * Usa Server-Sent Events (SSE) para recibir notificaciones
 */

class NotificationSystem {
    constructor() {
        this.eventSource = null;
        this.lastNotificationId = 0;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.isConnected = false;
        
        // Contenedor de notificaciones
        this.container = this.createNotificationContainer();
        
        // Inicializar
        this.init();
    }
    
    init() {
        this.connect();
        this.setupNotificationPermissions();
    }
    
    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        const url = `backend/notifications/sse.php?lastId=${this.lastNotificationId}`;
        this.eventSource = new EventSource(url);
        
        this.eventSource.onopen = () => {
            console.log('✅ Conectado al sistema de notificaciones');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.updateConnectionStatus(true);
        };
        
        this.eventSource.onerror = (error) => {
            console.error('❌ Error en notificaciones:', error);
            this.isConnected = false;
            this.updateConnectionStatus(false);
            this.handleReconnection();
        };
        
        // Escuchar notificaciones normales
        this.eventSource.addEventListener('notification', (event) => {
            const data = JSON.parse(event.data);
            this.showNotification(data);
            this.lastNotificationId = data.id;
        });
        
        // Escuchar alertas de stock bajo
        this.eventSource.addEventListener('low_stock', (event) => {
            const data = JSON.parse(event.data);
            this.showStockAlert(data);
        });
        
        // Heartbeat para mantener conexión
        this.eventSource.addEventListener('heartbeat', (event) => {
            // Solo para mantener la conexión viva
            this.updateConnectionStatus(true);
        });
        
        // Manejar errores
        this.eventSource.addEventListener('error', (event) => {
            const data = JSON.parse(event.data);
            this.showError(data.message);
        });
    }
    
    handleReconnection() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
            
            console.log(`🔄 Reintentando conexión en ${delay/1000}s (intento ${this.reconnectAttempts})`);
            
            setTimeout(() => {
                this.connect();
            }, delay);
        } else {
            console.error('💥 Máximo de intentos de reconexión alcanzado');
            this.showError('No se pudo conectar al sistema de notificaciones');
        }
    }
    
    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
        return container;
    }
    
    showNotification(data) {
        const notification = this.createNotificationElement(data);
        this.container.appendChild(notification);
        
        // Reproducir sonido si está habilitado
        this.playNotificationSound();
        
        // Browser notification si está disponible
        this.showBrowserNotification(data);
        
        // Auto-remove después de 8 segundos
        setTimeout(() => {
            this.removeNotification(notification);
        }, 8000);
    }
    
    showStockAlert(data) {
        const alert = {
            type: 'warning',
            title: 'Stock Bajo',
            message: data.message,
            priority: 'high'
        };
        
        const notification = this.createNotificationElement(alert);
        notification.classList.add('stock-alert');
        this.container.appendChild(notification);
        
        // Las alertas de stock duran más tiempo
        setTimeout(() => {
            this.removeNotification(notification);
        }, 15000);
    }
    
    showError(message) {
        const error = {
            type: 'error',
            title: 'Error del Sistema',
            message: message,
            priority: 'high'
        };
        
        const notification = this.createNotificationElement(error);
        this.container.appendChild(notification);
        
        setTimeout(() => {
            this.removeNotification(notification);
        }, 10000);
    }
    
    createNotificationElement(data) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${data.type || 'info'}`;
        notification.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border-left: 4px solid ${this.getNotificationColor(data.type)};
            min-width: 320px;
            pointer-events: auto;
            transform: translateX(100%);
            transition: all 0.3s ease;
            opacity: 0;
        `;
        
        const icon = this.getNotificationIcon(data.type);
        const priorityClass = data.priority === 'high' ? 'font-weight: 700;' : '';
        
        notification.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="color: ${this.getNotificationColor(data.type)}; font-size: 20px; flex-shrink: 0; margin-top: 2px;">
                    ${icon}
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #1a202c; margin-bottom: 4px; ${priorityClass}">
                        ${data.title}
                    </div>
                    <div style="color: #4a5568; font-size: 14px; line-height: 1.4;">
                        ${data.message}
                    </div>
                    ${data.created_at ? `<div style="color: #a0aec0; font-size: 12px; margin-top: 4px;">${this.formatTime(data.created_at)}</div>` : ''}
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 18px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                    ×
                </button>
            </div>
        `;
        
        // Animar entrada
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);
        
        return notification;
    }
    
    getNotificationColor(type) {
        switch(type) {
            case 'success': return '#22c55e';
            case 'warning': return '#f59e0b';
            case 'error': return '#ef4444';
            case 'info': 
            default: return '#3b82f6';
        }
    }
    
    getNotificationIcon(type) {
        switch(type) {
            case 'success': return '✅';
            case 'warning': return '⚠️';
            case 'error': return '❌';
            case 'info':
            default: return 'ℹ️';
        }
    }
    
    removeNotification(notification) {
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Ahora';
        if (diff < 3600000) return `${Math.floor(diff/60000)}m`;
        if (diff < 86400000) return `${Math.floor(diff/3600000)}h`;
        return date.toLocaleDateString();
    }
    
    // Notificaciones del navegador
    setupNotificationPermissions() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Permisos de notificación:', permission);
            });
        }
    }
    
    showBrowserNotification(data) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(data.title, {
                body: data.message,
                icon: '/assets/images/logo.png', // Ajusta la ruta
                tag: `notification-${data.id}`,
                requireInteraction: data.priority === 'high'
            });
        }
    }
    
    playNotificationSound() {
        // Opcional: reproducir sonido
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IAAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmcfCjiS2+7CayEFLYrP8t2LPgwTXrfz7alTFgxNo+T1r2AiCzG2+fT1rV0UEWm/9Oh5VRcNVKfu7ahTHAUSo+nxz2AjDy9VJV2nt3F8eEJ');
            audio.volume = 0.3;
            audio.play().catch(() => {
                // Ignorar errores de audio
            });
        } catch (e) {
            // Ignorar errores de audio
        }
    }
    
    updateConnectionStatus(connected) {
        // Actualizar indicador de conexión si existe
        const indicator = document.getElementById('connection-status');
        if (indicator) {
            indicator.className = connected ? 'connected' : 'disconnected';
            indicator.title = connected ? 'Conectado a notificaciones' : 'Desconectado';
        }
    }
    
    // Método público para cerrar conexión
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
    }
    
    // Método público para verificar conexión
    isConnectedToNotifications() {
        return this.isConnected;
    }
}

// Auto-inicializar cuando la página esté lista
document.addEventListener('DOMContentLoaded', function() {
    // Solo inicializar si el usuario está logueado
    if (document.body.classList.contains('dashboard-page')) {
        window.notificationSystem = new NotificationSystem();
        
        // Agregar indicador de conexión
        const indicator = document.createElement('div');
        indicator.id = 'connection-status';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ef4444;
            z-index: 999;
            transition: all 0.3s ease;
        `;
        indicator.title = 'Estado de notificaciones';
        document.body.appendChild(indicator);
        
        // CSS para el indicador
        const style = document.createElement('style');
        style.textContent = `
            #connection-status.connected {
                background: #22c55e !important;
                box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
            }
            #connection-status.disconnected {
                background: #ef4444 !important;
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
        `;
        document.head.appendChild(style);
    }
});

// Limpiar al cerrar página
window.addEventListener('beforeunload', function() {
    if (window.notificationSystem) {
        window.notificationSystem.disconnect();
    }
});