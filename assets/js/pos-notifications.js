/**
 * SISTEMA DE NOTIFICACIONES OPTIMIZADO PARA POS
 * Controla las notificaciones específicamente en el punto de venta
 */

class POSNotificationManager {
    constructor() {
        this.maxNotifications = 3; // Máximo 3 notificaciones visibles en POS
        this.autoHideDelay = 4000; // 4 segundos para auto-ocultar
        this.container = null;
        this.notifications = new Map();
        this.soundEnabled = true;
        this.isInitialized = false;
        
        this.init();
    }
    
    init() {
        if (this.isInitialized) return;
        
        // Crear contenedor si no existe
        this.createContainer();
        
        // Conectar con el sistema global de notificaciones si existe
        if (window.notificationSystem) {
            this.connectToGlobalSystem();
        } else {
            // Inicializar sistema básico
            this.initBasicSystem();
        }
        
        this.isInitialized = true;
        console.log('🔔 POS Notification Manager iniciado');
    }
    
    createContainer() {
        // Eliminar contenedor existente si hay
        const existing = document.getElementById('notification-container');
        if (existing) {
            existing.remove();
        }
        
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'pos-notification-container';
        document.body.appendChild(this.container);
    }
    
    connectToGlobalSystem() {
        // Interceptar las notificaciones del sistema global
        const originalShow = window.notificationSystem.showNotification;
        
        window.notificationSystem.showNotification = (data, shouldBroadcast = false) => {
            // Filtrar notificaciones para POS
            if (this.shouldShowInPOS(data)) {
                this.showPOSNotification(data);
            }
            
            // Llamar al método original pero sin mostrar UI (solo broadcast)
            if (shouldBroadcast && window.notificationSystem.channel) {
                window.notificationSystem.channel.postMessage({
                    type: 'notification',
                    notification: data
                });
            }
        };
    }
    
    initBasicSystem() {
        // Sistema básico sin SSE, solo para mostrar notificaciones locales
        console.log('📡 Iniciando sistema básico de notificaciones para POS');
    }
    
    shouldShowInPOS(notification) {
        // Filtrar qué notificaciones mostrar en POS
        const posRelevantTypes = ['sale', 'payment', 'error', 'warning', 'success'];
        return posRelevantTypes.includes(notification.type);
    }
    
    showPOSNotification(data) {
        // Limitar número de notificaciones
        this.cleanupOldNotifications();
        
        const notification = this.createNotificationElement(data);
        const notificationId = this.generateId();
        
        // Añadir a la cola
        this.notifications.set(notificationId, {
            element: notification,
            data: data,
            timestamp: Date.now()
        });
        
        // Mostrar notificación
        this.container.appendChild(notification);
        
        // Auto-ocultar
        setTimeout(() => {
            this.hideNotification(notificationId);
        }, this.autoHideDelay);
        
        // Reproducir sonido
        if (this.soundEnabled && data.type !== 'info') {
            this.playSound(data.type);
        }
    }
    
    createNotificationElement(data) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${data.type}`;
        
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">
                    ${this.getIcon(data.type)}
                </div>
                <div class="notification-text">
                    <div class="notification-title">${data.title}</div>
                    <div class="notification-message">${data.message}</div>
                    ${data.timestamp ? `<div class="notification-time">${this.formatTime(data.timestamp)}</div>` : ''}
                </div>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    ×
                </button>
            </div>
        `;
        
        // Agregar evento de click para cerrar
        notification.addEventListener('click', (e) => {
            if (e.target.classList.contains('notification-close')) {
                this.hideNotificationElement(notification);
            }
        });
        
        return notification;
    }
    
    cleanupOldNotifications() {
        while (this.notifications.size >= this.maxNotifications) {
            const oldestId = this.notifications.keys().next().value;
            this.hideNotification(oldestId);
        }
    }
    
    hideNotification(notificationId) {
        const notification = this.notifications.get(notificationId);
        if (notification && notification.element) {
            this.hideNotificationElement(notification.element);
            this.notifications.delete(notificationId);
        }
    }
    
    hideNotificationElement(element) {
        element.classList.add('notification-exit');
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
        }, 300);
    }
    
    getIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            sale: '💰',
            payment: '💳'
        };
        return icons[type] || icons.info;
    }
    
    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    generateId() {
        return 'notif_' + Math.random().toString(36).substr(2, 9);
    }
    
    playSound(type) {
        try {
            // Sonidos diferentes según el tipo
            const frequencies = {
                success: 800,
                sale: 1000,
                payment: 600,
                error: 400,
                warning: 700
            };
            
            const freq = frequencies[type] || 500;
            
            if (window.AudioContext || window.webkitAudioContext) {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(freq, audioContext.currentTime);
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            }
        } catch (error) {
            console.log('No se pudo reproducir sonido:', error);
        }
    }
    
    // Métodos públicos para usar desde el POS
    
    showSaleComplete(amount, saleNumber) {
        this.showPOSNotification({
            type: 'sale',
            title: 'Venta Completada',
            message: `Venta #${saleNumber} por ${amount} registrada exitosamente`,
            timestamp: new Date().toISOString()
        });
    }
    
    showPaymentReceived(amount, method) {
        this.showPOSNotification({
            type: 'payment',
            title: 'Pago Recibido',
            message: `Pago de ${amount} por ${method}`,
            timestamp: new Date().toISOString()
        });
    }
    
    showError(message) {
        this.showPOSNotification({
            type: 'error',
            title: 'Error',
            message: message,
            timestamp: new Date().toISOString()
        });
    }
    
    showSuccess(message) {
        this.showPOSNotification({
            type: 'success',
            title: 'Éxito',
            message: message,
            timestamp: new Date().toISOString()
        });
    }
    
    showWarning(message) {
        this.showPOSNotification({
            type: 'warning',
            title: 'Advertencia',
            message: message,
            timestamp: new Date().toISOString()
        });
    }
    
    // Control de configuración
    
    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
    }
    
    setMaxNotifications(max) {
        this.maxNotifications = max;
        this.cleanupOldNotifications();
    }
    
    clearAllNotifications() {
        this.notifications.forEach((notification, id) => {
            this.hideNotification(id);
        });
    }
}

// Funciones globales para integración con POS
window.POSNotifications = {
    manager: null,
    
    init() {
        if (!this.manager) {
            this.manager = new POSNotificationManager();
        }
        return this.manager;
    },
    
    // Métodos de conveniencia
    saleComplete(amount, saleNumber) {
        this.init().showSaleComplete(amount, saleNumber);
    },
    
    paymentReceived(amount, method) {
        this.init().showPaymentReceived(amount, method);
    },
    
    error(message) {
        this.init().showError(message);
    },
    
    success(message) {
        this.init().showSuccess(message);
    },
    
    warning(message) {
        this.init().showWarning(message);
    },
    
    clearAll() {
        if (this.manager) {
            this.manager.clearAllNotifications();
        }
    }
};

// Auto-inicializar si estamos en página POS
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('pos-page') || window.location.pathname.includes('pos.php')) {
        console.log('🎯 Detectada página POS, inicializando notificaciones...');
        window.POSNotifications.init();
    }
});

// Prevenir múltiples inicializaciones
if (window.posNotificationsLoaded) {
    console.warn('⚠️ POS Notifications ya estaba cargado');
} else {
    window.posNotificationsLoaded = true;
    console.log('✅ POS Notifications cargado correctamente');
}