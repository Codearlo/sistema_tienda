/**
 * API CLIENT COMPLETO
 * Archivo: assets/js/api.js
 * Cliente para comunicación con el backend
 */

class APIClient {
    constructor() {
        this.baseURL = window.location.origin + '/backend/api';
        this.csrfToken = null;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        this.setupCSRF();
    }
    
    setupCSRF() {
        // Buscar token CSRF en meta tags
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            this.csrfToken = csrfMeta.getAttribute('content');
            this.defaultHeaders['X-CSRF-Token'] = this.csrfToken;
        }
    }
    
    async request(endpoint, options = {}) {
        // Construir URL completa
        const url = endpoint.startsWith('http') ? endpoint : this.baseURL + endpoint;
        
        // Configuración por defecto
        const config = {
            method: 'GET',
            headers: { ...this.defaultHeaders },
            credentials: 'same-origin',
            ...options
        };
        
        // Agregar headers personalizados
        if (options.headers) {
            config.headers = { ...config.headers, ...options.headers };
        }
        
        try {
            console.log(`🌐 API ${config.method}: ${url}`);
            
            const response = await fetch(url, config);
            
            // Verificar si la respuesta es OK
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Determinar tipo de contenido
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                console.log(`✅ API Response:`, data);
                return data;
            } else {
                const text = await response.text();
                console.log(`📄 API Text Response:`, text.substring(0, 200));
                return text;
            }
            
        } catch (error) {
            console.error(`❌ API Error (${config.method} ${url}):`, error);
            
            // No mostrar alert automáticamente, dejar que el código que llama maneje el error
            throw error;
        }
    }
    
    // ===== MÉTODOS HTTP =====
    
    async get(endpoint, params = {}) {
        // Agregar parámetros de query si existen
        const url = new URL(endpoint.startsWith('http') ? endpoint : this.baseURL + endpoint);
        
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        return await this.request(url.toString());
    }
    
    async post(endpoint, data = {}) {
        return await this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async put(endpoint, data = {}) {
        return await this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    async delete(endpoint) {
        return await this.request(endpoint, {
            method: 'DELETE'
        });
    }
    
    // ===== MÉTODOS ESPECÍFICOS DE LA APLICACIÓN =====
    
    // Dashboard
    async getDashboardData() {
        return await this.get('?endpoint=dashboard');
    }
    
    // Productos
    async getProducts(filters = {}) {
        return await this.get('?endpoint=products', filters);
    }
    
    async getProduct(id) {
        return await this.get(`?endpoint=products&id=${id}`);
    }
    
    async createProduct(data) {
        return await this.post('?endpoint=products', data);
    }
    
    async updateProduct(id, data) {
        return await this.put(`?endpoint=products&id=${id}`, data);
    }
    
    async deleteProduct(id) {
        return await this.delete(`?endpoint=products&id=${id}`);
    }
    
    // Categorías
    async getCategories() {
        return await this.get('?endpoint=categories');
    }
    
    async getCategory(id) {
        return await this.get(`?endpoint=categories&id=${id}`);
    }
    
    async createCategory(data) {
        return await this.post('?endpoint=categories', data);
    }
    
    async updateCategory(id, data) {
        return await this.put(`?endpoint=categories&id=${id}`, data);
    }
    
    async deleteCategory(id) {
        return await this.delete(`?endpoint=categories&id=${id}`);
    }
    
    // Ventas
    async getSales(filters = {}) {
        return await this.get('?endpoint=sales', filters);
    }
    
    async getSale(id) {
        return await this.get(`?endpoint=sales&id=${id}`);
    }
    
    async createSale(data) {
        return await this.post('?endpoint=sales', data);
    }
    
    // Clientes
    async getCustomers(filters = {}) {
        return await this.get('?endpoint=customers', filters);
    }
    
    async getCustomer(id) {
        return await this.get(`?endpoint=customers&id=${id}`);
    }
    
    async createCustomer(data) {
        return await this.post('?endpoint=customers', data);
    }
    
    async updateCustomer(id, data) {
        return await this.put(`?endpoint=customers&id=${id}`, data);
    }
    
    async deleteCustomer(id) {
        return await this.delete(`?endpoint=customers&id=${id}`);
    }
    
    // Stock
    async getStockMovements(filters = {}) {
        return await this.get('?endpoint=stock', filters);
    }
    
    async addStockMovement(data) {
        return await this.post('?endpoint=stock', data);
    }
    
    // ===== UTILIDADES =====
    
    setCSRFToken(token) {
        this.csrfToken = token;
        this.defaultHeaders['X-CSRF-Token'] = token;
    }
    
    setAuthToken(token) {
        this.defaultHeaders['Authorization'] = `Bearer ${token}`;
    }
    
    removeAuthToken() {
        delete this.defaultHeaders['Authorization'];
    }
    
    // Método para hacer requests sin mostrar errores automáticamente
    async silentRequest(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : this.baseURL + endpoint;
        
        const config = {
            method: 'GET',
            headers: { ...this.defaultHeaders },
            credentials: 'same-origin',
            ...options
        };
        
        if (options.headers) {
            config.headers = { ...config.headers, ...options.headers };
        }
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
            
        } catch (error) {
            console.error(`API Silent Error (${config.method} ${url}):`, error);
            throw error;
        }
    }
    
    // ===== COMPATIBILIDAD CON CÓDIGO LEGACY =====
    
    // Para mantener compatibilidad con el código existente
    async getDashboard() {
        return await this.getDashboardData();
    }
}

// ===== INSTANCIA GLOBAL =====
const API = new APIClient();

// ===== EXPORTAR PARA USO GLOBAL =====
window.API = API;

// ===== COMPATIBILIDAD =====
// Para compatibilidad con código existente que use fetch directamente
window.apiRequest = (endpoint, options) => API.request(endpoint, options);
window.apiGet = (endpoint, params) => API.get(endpoint, params);
window.apiPost = (endpoint, data) => API.post(endpoint, data);
window.apiPut = (endpoint, data) => API.put(endpoint, data);
window.apiDelete = (endpoint) => API.delete(endpoint);

// ===== SISTEMA DE NOTIFICACIONES =====
const Notifications = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'notifications-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 5000) {
        this.init();
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            background: ${this.getBackgroundColor(type)};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            pointer-events: auto;
            cursor: pointer;
            max-width: 100%;
            word-wrap: break-word;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="flex: 1; margin-right: 10px;">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; padding: 0; margin: 0;">×</button>
            </div>
        `;
        
        this.container.appendChild(notification);
        
        // Animación de entrada
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-remove después del tiempo especificado
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => notification.remove(), 300);
                }
            }, duration);
        }
        
        // Remove al hacer click
        notification.addEventListener('click', () => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        });
    },
    
    success(message, duration = 5000) {
        this.show(message, 'success', duration);
    },
    
    error(message, duration = 8000) {
        this.show(message, 'error', duration);
    },
    
    warning(message, duration = 6000) {
        this.show(message, 'warning', duration);
    },
    
    info(message, duration = 5000) {
        this.show(message, 'info', duration);
    },
    
    getBackgroundColor(type) {
        const colors = {
            success: '#10B981',
            error: '#EF4444',
            warning: '#F59E0B',
            info: '#3B82F6'
        };
        return colors[type] || colors.info;
    }
};

// Hacer Notifications global
window.Notifications = Notifications;

// Inicializar notificaciones
Notifications.init();

console.log('🔌 API Client inicializado correctamente');

// ===== MANEJO GLOBAL DE ERRORES DE API =====
window.addEventListener('unhandledrejection', event => {
    console.error('Unhandled promise rejection:', event.reason);
    if (event.reason && event.reason.message && event.reason.message.includes('API')) {
        Notifications.error('Error de conexión con el servidor');
    }
});