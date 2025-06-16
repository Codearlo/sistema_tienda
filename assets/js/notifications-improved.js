/**
 * Sistema de Notificaciones Mejorado para Treinta
 * Evita duplicación de notificaciones en múltiples pestañas
 */

class ImprovedNotificationSystem {
    constructor() {
        this.isLeader = false;
        this.leaderId = this.generateId();
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.container = null;
        this.recentNotifications = new Map();
        this.throttleTime = 3000; // 3 segundos
        this.maxVisibleNotifications = 3;
        this.isVisible = !document.hidden;
        
        // Inicializar componentes
        this.initializeBroadcastChannel();
        this.initializeLeaderElection();
        this.initializeVisibilityHandling();
        this.createNotificationContainer();
        this.requestBrowserPermission();
        
        console.log('🔔 Sistema de notificaciones mejorado iniciado');
    }
    
    generateId() {
        return 'tab_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }
    
    // ===== BROADCAST CHANNEL (COMUNICACIÓN ENTRE PESTAÑAS) =====
    initializeBroadcastChannel() {
        if ('BroadcastChannel' in window) {
            this.channel = new BroadcastChannel('treinta_notifications');
            
            this.channel.addEventListener('message', (event) => {
                switch (event.data.type) {
                    case 'notification':
                        this.showNotification(event.data.notification, false); // false = no broadcast
                        break;
                    case 'leader_heartbeat':
                        this.handleLeaderHeartbeat(event.data);
                        break;
                    case 'leader_left':
                        this.handleLeaderLeft();
                        break;
                    case 'request_leadership':
                        this.handleLeadershipRequest(event.data);
                        break;
                }
            });
            
            console.log('📡 Broadcast Channel inicializado');
        } else {
            console.warn('⚠️ BroadcastChannel no soportado, cada pestaña manejará sus propias notificaciones');
        }
    }
    
    // ===== ELECCIÓN DE LÍDER (SOLO UNA PESTAÑA MANEJA SSE) =====
    initializeLeaderElection() {
        // Verificar si ya hay un líder activo
        const currentLeader = localStorage.getItem('notification_leader');
        const leaderTimestamp = localStorage.getItem('leader_timestamp');
        
        if (!currentLeader || this.isLeaderExpired(leaderTimestamp)) {
            this.becomeLeader();
        } else if (this.channel) {
            // Solicitar liderazgo si el líder actual no responde
            setTimeout(() => {
                this.requestLeadership();
            }, 2000);
        }
        
        // Heartbeat del líder cada 10 segundos
        setInterval(() => {
            if (this.isLeader) {
                this.broadcastLeaderHeartbeat();
            }
        }, 10000);
        
        // Cleanup al cerrar pestaña
        window.addEventListener('beforeunload', () => {
            if (this.isLeader) {
                this.relinquishLeadership();
            }
        });
    }
    
    isLeaderExpired(timestamp) {
        if (!timestamp) return true;
        return Date.now() - parseInt(timestamp) > 30000; // 30 segundos
    }
    
    becomeLeader() {
        this.isLeader = true;
        localStorage.setItem('notification_leader', this.leaderId);
        localStorage.setItem('leader_timestamp', Date.now().toString());
        
        console.log('👑 Esta pestaña es ahora el líder de notificaciones');
        
        if (this.channel) {
            this.channel.postMessage({
                type: 'leader_heartbeat',
                leaderId: this.leaderId,
                timestamp: Date.now()
            });
        }
        
        // Solo el líder conecta al SSE
        this.connect();
    }
    
    relinquishLeadership() {
        if (this.isLeader) {
            localStorage.removeItem('notification_leader');
            localStorage.removeItem('leader_timestamp');
            
            if (this.channel) {
                this.channel.postMessage({
                    type: 'leader_left',
                    leaderId: this.leaderId
                });
            }
            
            this.disconnect();
            this.isLeader = false;
            
            console.log('👋 Liderazgo relinquido');
        }
    }
    
    requestLeadership() {
        if (this.channel && !this.isLeader) {
            this.channel.postMessage({
                type: 'request_leadership',
                requesterId: this.leaderId,
                timestamp: Date.now()
            });
            
            // Si no hay respuesta en 3 segundos, asumir liderazgo
            setTimeout(() => {
                const currentLeader = localStorage.getItem('notification_leader');
                if (!currentLeader || this.isLeaderExpired(localStorage.getItem('leader_timestamp'))) {
                    this.becomeLeader();
                }
            }, 3000);
        }
    }
    
    broadcastLeaderHeartbeat() {
        localStorage.setItem('leader_timestamp', Date.now().toString());
        
        if (this.channel) {
            this.channel.postMessage({
                type: 'leader_heartbeat',
                leaderId: this.leaderId,
                timestamp: Date.now()
            });
        }
    }
    
    handleLeaderHeartbeat(data) {
        if (data.leaderId !== this.leaderId) {
            // Hay otro líder activo
            if (this.isLeader) {
                console.log('🔄 Otro líder detectado, relinquiendo liderazgo');
                this.isLeader = false;
                this.disconnect();
            }
        }
    }
    
    handleLeaderLeft() {
        setTimeout(() => {
            const currentLeader = localStorage.getItem('notification_leader');
            if (!currentLeader) {
                this.becomeLeader();
            }
        }, 1000);
    }
    
    handleLeadershipRequest(data) {
        if (this.isLeader) {
            // Responder que seguimos siendo líderes
            this.broadcastLeaderHeartbeat();
        }
    }
    
    // ===== MANEJO DE VISIBILIDAD DE PESTAÑAS =====
    initializeVisibilityHandling() {
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            
            if (this.isVisible && this.isLeader) {
                // Pestaña visible y líder, asegurar conexión
                if (!this.isConnected) {
                    this.connect();
                }
            }
        });
    }
    
    // ===== CONEXIÓN SSE (SOLO PARA EL LÍDER) =====
    connect() {
        if (!this.isLeader) {
            console.log('⚠️ Solo el líder puede conectar al SSE');
            return;
        }
        
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        console.log('🔌 Conectando al stream de notificaciones...');
        
        this.eventSource = new EventSource('backend/api/notifications_stream.php');
        
        this.eventSource.onopen = () => {
            console.log('✅ Conectado al sistema de notificaciones');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.updateConnectionStatus(true);
        };
        
        this.eventSource.onerror = (error) => {
            console.error('❌ Error en conexión SSE:', error);
            this.isConnected = false;
            this.updateConnectionStatus(false);
            
            if (this.eventSource.readyState === EventSource.CLOSED) {
                this.handleReconnection();
            }
        };
        
        // Manejar notificaciones
        this.eventSource.addEventListener('notification', (event) => {
            try {
                const data = JSON.parse(event.data);
                this.showNotification(data, true); // true = broadcast a otras pestañas
            } catch (error) {
                console.error('Error al procesar notificación:', error);
            }
        });
        
        // Heartbeat para mantener conexión
        this.eventSource.addEventListener('heartbeat', (event) => {
            this.updateConnectionStatus(true);
        });
    }
    
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        this.updateConnectionStatus(false);
    }
    
    handleReconnection() {
        if (!this.isLeader) return;
        
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
            
            console.log(`🔄 Reintentando conexión en ${delay/1000}s (intento ${this.reconnectAttempts})`);
            
            setTimeout(() => {
                if (this.isLeader) {
                    this.connect();
                }
            }, delay);
        } else {
            console.error('💥 Máximo de intentos de reconexión alcanzado');
            this.showError('No se pudo conectar al sistema de notificaciones');
            
            // Intentar transferir liderazgo
            this.relinquishLeadership();
        }
    }
    
    // ===== MANEJO DE NOTIFICACIONES =====
    createNotificationContainer() {
        if (document.getElementById('notification-container')) {
            this.container = document.getElementById('notification-container');
            return;
        }
        
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
        this.container = container;
    }
    
    shouldShowNotification(notification) {
        const key = `${notification.type}_${notification.title}_${notification.message}`;
        const lastShown = this.recentNotifications.get(key);
        
        if (lastShown && Date.now() - lastShown < this.throttleTime) {
            console.log('🚫 Notificación throttled:', notification.title);
            return false;
        }
        
        this.recentNotifications.set(key, Date.now());
        
        // Limpiar notificaciones antiguas del cache
        setTimeout(() => {
            this.recentNotifications.delete(key);
        }, this.throttleTime * 2);
        
        return true;
    }
    
    showNotification(data, shouldBroadcast = false) {
        if (!this.shouldShowNotification(data)) {
            return;
        }
        
        // Broadcast a otras pestañas (solo si somos líderes)
        if (shouldBroadcast && this.isLeader && this.channel) {
            this.channel.postMessage({
                type: 'notification',
                notification: data
            });
        }
        
        // Mostrar notificación visual solo en pestaña visible
        if (this.isVisible) {
            this.displayVisualNotification(data);
        }
        
        // Siempre mostrar notificación del navegador
        this.showBrowserNotification(data);
        
        // Reproducir sonido
        this.playNotificationSound();
    }
    
    displayVisualNotification(data) {
        // Limitar número de notificaciones visibles
        while (this.container.children.length >= this.maxVisibleNotifications) {
            this.container.removeChild(this.container.firstChild);
        }
        
        const notification = this.createNotificationElement(data);
        this.container.appendChild(notification);
        
        // Auto-remove después de 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    createNotificationElement(data) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${data.type}`;
        notification.style.cssText = `
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            pointer-events: auto;
            cursor: pointer;
            min-width: 300px;
            position: relative;
            border-left: 4px solid ${this.getNotificationColor(data.type)};
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="font-size: 20px;">${this.getNotificationIcon(data.type)}</div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px; color: #1f2937;">
                        ${data.title}
                    </div>
                    <div style="color: #6b7280; font-size: 14px; line-height: 1.4;">
                        ${data.message}
                    </div>
                    ${data.timestamp ? `
                        <div style="color: #9ca3af; font-size: 12px; margin-top: 4px;">
                            ${new Date(data.timestamp).toLocaleTimeString()}
                        </div>
                    ` : ''}
                </div>
                <button onclick="this.parentNode.parentNode.remove()" 
                        style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0; font-size: 18px;">
                    ×
                </button>
            </div>
        `;
        
        // Click para cerrar
        notification.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        });
        
        return notification;
    }
    
    getNotificationColor(type) {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6',
            sale: '#8b5cf6'
        };
        return colors[type] || colors.info;
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            sale: '💰'
        };
        return icons[type] || icons.info;
    }
    
    // ===== NOTIFICACIONES DEL NAVEGADOR =====
    async requestBrowserPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    }
    
    showBrowserNotification(data) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(data.title, {
                body: data.message,
                icon: '/favicon.ico',
                tag: `treinta-${data.type}`, // Evita duplicados
                requireInteraction: data.type === 'error'
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
            
            // Auto-close después de 5 segundos
            setTimeout(() => notification.close(), 5000);
        }
    }
    
    // ===== SONIDO Y ESTADO =====
    playNotificationSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACAlou');
            audio.volume = 0.1;
            audio.play().catch(() => {}); // Ignorar errores de audio
        } catch (error) {
            // Audio no disponible
        }
    }
    
    updateConnectionStatus(connected) {
        const indicator = document.getElementById('connection-status');
        if (indicator) {
            indicator.className = connected ? 'connected' : 'disconnected';
            indicator.title = connected ? 'Conectado a notificaciones' : 'Desconectado';
        }
    }
    
    showError(message) {
        this.showNotification({
            type: 'error',
            title: 'Error de Conexión',
            message: message,
            timestamp: new Date().toISOString()
        });
    }
    
    // ===== MÉTODOS PÚBLICOS =====
    isConnectedToNotifications() {
        return this.isConnected;
    }
    
    getConnectionInfo() {
        return {
            isConnected: this.isConnected,
            isLeader: this.isLeader,
            leaderId: this.leaderId,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

// Auto-inicializar cuando la página esté lista
document.addEventListener('DOMContentLoaded', function() {
    // Solo inicializar si el usuario está logueado
    if (document.body.classList.contains('dashboard-page') || document.querySelector('[data-page]')) {
        window.notificationSystem = new ImprovedNotificationSystem();
        
        // Agregar indicador de conexión si no existe
        if (!document.getElementById('connection-status')) {
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
        }
        
        // CSS para el indicador
        if (!document.getElementById('notification-status-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-status-styles';
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
                
                .notification {
                    animation: slideIn 0.3s ease-out;
                }
                
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
});

// Limpiar al cerrar página
window.addEventListener('beforeunload', function() {
    if (window.notificationSystem) {
        window.notificationSystem.relinquishLeadership();
        window.notificationSystem.disconnect();
    }
});