/**
 * APLICACIÓN PRINCIPAL JAVASCRIPT
 * Archivo: assets/js/app.js
 */

// ===== CONFIGURACIÓN GLOBAL =====
const App = {
    baseUrl: window.location.origin,
    apiUrl: window.location.origin + '/backend/api',
    csrfToken: null,
    user: null,
    
    init() {
        this.setupCSRF();
        this.setupEventListeners();
        this.initComponents();
    },
    
    setupCSRF() {
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            this.csrfToken = token.getAttribute('content');
        }
    },
    
    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initModals();
            this.initForms();
            this.initTables();
        });
    },
    
    initComponents() {
        if (typeof Messages !== 'undefined') Messages.init();
        if (typeof Tables !== 'undefined') Tables.init();
    }
};

// ===== SISTEMA DE API =====
const API = {
    baseURL: App.apiUrl,
    
    async request(endpoint, options = {}) {
        const url = this.baseURL + endpoint;
        const config = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        };
        
        if (App.csrfToken) {
            config.headers['X-CSRF-Token'] = App.csrfToken;
        }
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            return contentType && contentType.includes('application/json')
                ? await response.json()
                : await response.text();
                
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },
    
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// ===== SISTEMA DE MENSAJES =====
const Messages = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'messages-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 400px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 5000) {
        this.init();
        
        const messageEl = document.createElement('div');
        messageEl.className = `alert alert-${type}`;
        messageEl.style.cssText = `
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
        `;
        messageEl.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 1.2em; cursor: pointer;">×</button>
            </div>
        `;
        
        this.container.appendChild(messageEl);
        
        // Animación de entrada
        setTimeout(() => {
            messageEl.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-remover
        if (duration > 0) {
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.style.transform = 'translateX(100%)';
                    setTimeout(() => messageEl.remove(), 300);
                }
            }, duration);
        }
    },
    
    getBackgroundColor(type) {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        return colors[type] || colors.info;
    },
    
    success(message, duration = 5000) {
        this.show(message, 'success', duration);
    },
    
    error(message, duration = 0) {
        this.show(message, 'error', duration);
    },
    
    warning(message, duration = 7000) {
        this.show(message, 'warning', duration);
    },
    
    info(message, duration = 5000) {
        this.show(message, 'info', duration);
    }
};

// ===== GESTIÓN DE FORMULARIOS =====
const Forms = {
    serialize(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                data[key] = Array.isArray(data[key]) ? data[key] : [data[key]];
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    clear(form) {
        form.reset();
        form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    },
    
    populate(form, data) {
        Object.keys(data).forEach(key => {
            const element = form.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = Boolean(data[key]);
                } else if (element.type === 'radio') {
                    const radio = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                    if (radio) radio.checked = true;
                } else {
                    element.value = data[key];
                }
            }
        });
    },
    
    validate(form) {
        let isValid = true;
        const errors = [];
        
        // Remover errores previos
        form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        
        // Validar campos requeridos
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                errors.push(`${field.getAttribute('data-label') || field.name} es requerido`);
                isValid = false;
            }
        });
        
        // Validar emails
        form.querySelectorAll('[type="email"]').forEach(field => {
            if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                field.classList.add('error');
                errors.push('Email no válido');
                isValid = false;
            }
        });
        
        return { isValid, errors };
    }
};

// ===== GESTIÓN DE TABLAS =====
const Tables = {
    init() {
        document.querySelectorAll('table[data-sortable]').forEach(table => {
            this.makeSortable(table);
        });
    },
    
    makeSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header.dataset.sort, header);
            });
        });
    },
    
    sortTable(table, column, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = header.classList.contains('sort-asc');
        
        // Limpiar clases de ordenamiento
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Agregar clase apropiada
        header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
        
        const columnIndex = Array.from(header.parentNode.children).indexOf(header);
        
        rows.sort((a, b) => {
            const aText = a.children[columnIndex].textContent.trim();
            const bText = b.children[columnIndex].textContent.trim();
            
            // Comparar como números si es posible
            const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? bNum - aNum : aNum - bNum;
            }
            
            // Comparar como texto
            return isAsc 
                ? bText.localeCompare(aText)
                : aText.localeCompare(bText);
        });
        
        // Reorganizar filas
        rows.forEach(row => tbody.appendChild(row));
    }
};

// ===== GESTIÓN DE MODALES =====
const Modal = {
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },
    
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },
    
    closeAll() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
};

// ===== UTILIDADES =====
const Utils = {
    formatCurrency(amount) {
        return new Intl.NumberFormat('es-PE', {
            style: 'currency',
            currency: 'PEN'
        }).format(amount);
    },
    
    formatDate(date) {
        return new Intl.DateTimeFormat('es-PE').format(new Date(date));
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// ===== INICIALIZACIÓN =====
App.init();

// ===== FUNCIONES GLOBALES =====
window.showMessage = (message, type = 'info') => Messages.show(message, type);
window.openModal = (modalId) => Modal.open(modalId);
window.closeModal = (modalId) => Modal.close(modalId);
window.formatCurrency = (amount) => Utils.formatCurrency(amount);

// Cerrar modales al hacer clic fuera
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        Modal.close(e.target.id);
    }
});

// Cerrar modales con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        Modal.closeAll();
    }
});