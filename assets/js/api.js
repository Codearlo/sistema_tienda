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
            
            // Mostrar error básico al usuario
            alert(`Error de conexión: ${error.message}`);
            
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
        
        return await this.request(url.toString(), {
            method: 'GET'
        });
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
    
    async patch(endpoint, data = {}) {
        return await this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }
    
    async delete(endpoint) {
        return await this.request(endpoint, {
            method: 'DELETE'
        });
    }
    
    // ===== MÉTODOS DE ARCHIVO =====
    
    async uploadFile(endpoint, file, additionalData = {}) {
        const formData = new FormData();
        formData.append('file', file);
        
        // Agregar datos adicionales
        Object.keys(additionalData).forEach(key => {
            formData.append(key, additionalData[key]);
        });
        
        // Headers especiales para archivos (sin Content-Type)
        const headers = { ...this.defaultHeaders };
        delete headers['Content-Type']; // Dejar que el navegador establezca el boundary
        
        return await this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers
        });
    }
    
    // ===== MÉTODOS ESPECÍFICOS DE LA APLICACIÓN =====
    
    // Productos
    async getProducts(filters = {}) {
        return await this.get('/productos.php', filters);
    }
    
    async getProduct(id) {
        return await this.get(`/productos.php?id=${id}`);
    }
    
    async createProduct(data) {
        return await this.post('/productos.php', data);
    }
    
    async updateProduct(id, data) {
        return await this.put(`/productos.php?id=${id}`, data);
    }
    
    async deleteProduct(id) {
        return await this.delete(`/productos.php?id=${id}`);
    }
    
    // Ventas
    async getSales(filters = {}) {
        return await this.get('/index.php?endpoint=sales', filters);
    }
    
    async createSale(data) {
        return await this.post('/index.php?endpoint=sales', data);
    }
    
    // Categorías
    async getCategories() {
        return await this.get('/categorias.php');
    }
    
    async createCategory(data) {
        return await this.post('/categorias.php', data);
    }
    
    // Clientes
    async getCustomers(filters = {}) {
        return await this.get('/clientes.php', filters);
    }
    
    async createCustomer(data) {
        return await this.post('/clientes.php', data);
    }
    
    // Dashboard
    async getDashboardData() {
        return await this.get('/dashboard.php');
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

console.log('🔌 API Client inicializado correctamente');