/**
 * APLICACIÓN PRINCIPAL - TREINTA POS
 * Archivo: assets/js/app.js
 * Funcionalidades core del sistema
 */

// ===== CONFIGURACIÓN GLOBAL =====
const App = {
    version: '1.0.0',
    debug: true,
    apiUrl: 'backend/api/',
    baseUrl: window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, ''),
    
    // Configuración de UI
    ui: {
        loadingDelay: 300,
        fadeSpeed: 200,
        tooltipDelay: 500
    },
    
    // Estado de la aplicación
    state: {
        isLoading: false,
        currentUser: null,
        currentBusiness: null
    }
};

// ===== UTILIDADES GENERALES =====
const Utils = {
    // Formatear moneda
    formatCurrency(amount, currency = 'S/') {
        return currency + ' ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    },
    
    // Formatear fecha
    formatDate(date, format = 'dd/mm/yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year)
            .replace('hh', hours)
            .replace('ii', minutes);
    },
    
    // Generar ID único
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },
    
    // Validar email
    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },
    
    // Escapar HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    // Debounce function
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
    
    // Formatear número con separadores de miles
    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },
    
    // Calcular porcentaje
    calculatePercentage(value, total) {
        return total > 0 ? ((value / total) * 100).toFixed(1) : 0;
    }
};

// ===== GESTIÓN DE LOADING =====
const Loading = {
    show(message = 'Cargando...') {
        App.state.isLoading = true;
        
        // Remover loader existente
        this.hide();
        
        const loader = document.createElement('div');
        loader.id = 'app-loader';
        loader.innerHTML = `
            <div class="loader-backdrop">
                <div class="loader-content">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            </div>
        `;
        
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        document.body.appendChild(loader);
        
        // CSS para el loader
        if (!document.getElementById('loader-styles')) {
            const styles = document.createElement('style');
            styles.id = 'loader-styles';
            styles.textContent = `
                .loader-backdrop {
                    background: rgba(255, 255, 255, 0.95);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    height: 100%;
                }
                .loader-content {
                    text-align: center;
                    padding: 2rem;
                }
                .spinner {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 1rem;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .loader-content p {
                    margin: 0;
                    color: #666;
                    font-size: 14px;
                }
            `;
            document.head.appendChild(styles);
        }
    },
    
    hide() {
        App.state.isLoading = false;
        const loader = document.getElementById('app-loader');
        if (loader) {
            loader.remove();
        }
    }
};

// ===== SISTEMA DE API =====
const API = {
    async request(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        // Agregar CSRF token si está disponible
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            defaultOptions.headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        const config = { ...defaultOptions, ...options };
        const url = App.apiUrl + endpoint.replace(/^\//, '');
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            
            return await response.text();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // Métodos HTTP específicos
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
        // Crear contenedor de mensajes si no existe
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

        const messageElement = document.createElement('div');
        messageElement.className = `alert alert-${type}`;
        messageElement.style.cssText = `
            background: ${this.getBackgroundColor(type)};
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            pointer-events: auto;
            cursor: pointer;
            position: relative;
            padding-right: 40px;
        `;
        
        messageElement.innerHTML = `
            ${message}
            <button onclick="this.parentElement.remove()" style="
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: white;
                font-size: 18px;
                cursor: pointer;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            ">&times;</button>
        `;

        this.container.appendChild(messageElement);

        // Animación de entrada
        setTimeout(() => {
            messageElement.style.transform = 'translateX(0)';
        }, 10);

        // Auto-remove
        if (duration > 0) {
            setTimeout(() => {
                this.remove(messageElement);
            }, duration);
        }

        // Click para cerrar
        messageElement.addEventListener('click', () => {
            this.remove(messageElement);
        });
    },

    remove(element) {
        if (element && element.parentNode) {
            element.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
            }, 300);
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

    error(message, duration = 7000) {
        this.show(message, 'error', duration);
    },

    warning(message, duration = 6000) {
        this.show(message, 'warning', duration);
    },

    info(message, duration = 5000) {
        this.show(message, 'info', duration);
    }
};

// ===== GESTIÓN DE FORMULARIOS =====
const Forms = {
    // Validar formulario
    validate(form) {
        const errors = [];
        const elements = form.querySelectorAll('[required]');
        
        elements.forEach(element => {
            if (!element.value.trim()) {
                errors.push(`El campo ${element.name || element.id} es requerido`);
                element.classList.add('error');
            } else {
                element.classList.remove('error');
            }
            
            // Validaciones específicas
            if (element.type === 'email' && element.value && !Utils.isValidEmail(element.value)) {
                errors.push('El formato del email no es válido');
                element.classList.add('error');
            }
        });
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    },
    
    // Serializar formulario a objeto
    serialize(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Si ya existe, convertir a array
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    // Limpiar formulario
    clear(form) {
        form.reset();
        form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    },
    
    // Llenar formulario con datos
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
    }
};

// ===== GESTIÓN DE TABLAS =====
const Tables = {
    // Hacer tabla sorteable
    makeSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header.dataset.sort, header);
            });
        });
    },
    
    // Ordenar tabla
    sortTable(table, column, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAsc = header.classList.contains('sort-asc');
        
        // Remover clases de ordenamiento de otros headers
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Agregar clase apropiada
        header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
        
        const columnIndex = Array.from(header.parentNode.children).indexOf(header);
        
        rows.sort((a, b) => {
            const aText = a.children[columnIndex].textContent.trim();
            const bText = b.children[columnIndex].textContent.trim();
            
            // Intentar comparar como números
            const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? bNum - aNum : aNum - bNum;
            }
            
            // Comparar como texto
            return isAsc ? bText.localeCompare(aText) : aText.localeCompare(bText);
        });
        
        // Reordenar filas
        rows.forEach(row => tbody.appendChild(row));
    },
    
    // Filtrar tabla
    filter(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }
};

// ===== GESTIÓN DE MODALES =====
const Modal = {
    // Abrir modal
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Focus en primer input
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    },
    
    // Cerrar modal
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    },
    
    // Cerrar todos los modales
    closeAll() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            this.close(modal.id);
        });
    }
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Treinta POS iniciado correctamente');
    
    // Configurar manejo global de errores
    window.addEventListener('error', function(e) {
        console.error('Error global:', e.error);
        if (App.debug) {
            Messages.error('Ha ocurrido un error inesperado. Revisa la consola para más detalles.');
        }
    });
    
    // Configurar manejo de promesas rechazadas
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Promesa rechazada:', e.reason);
        if (App.debug) {
            Messages.error('Error de conexión o procesamiento.');
        }
    });
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            Modal.closeAll();
        }
    });
    
    // Cerrar modales clickeando el backdrop
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            Modal.close(e.target.id);
        }
    });
    
    // Hacer todas las tablas con clase 'sortable' ordenables
    document.querySelectorAll('table.sortable').forEach(table => {
        Tables.makeSortable(table);
    });
    
    // Configurar campos de búsqueda para filtrado de tablas
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const tableId = input.dataset.tableSearch;
        const table = document.getElementById(tableId);
        
        if (table) {
            input.addEventListener('input', Utils.debounce(function() {
                Tables.filter(table, this.value);
            }, 300));
        }
    });
    
    // Auto-focus en campos con atributo data-autofocus
    const autofocusElement = document.querySelector('[data-autofocus]');
    if (autofocusElement) {
        setTimeout(() => autofocusElement.focus(), 100);
    }
    
    // Configurar tooltips simples
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-original-title', title);
                this.removeAttribute('title');
                
                const tooltip = document.createElement('div');
                tooltip.className = 'simple-tooltip';
                tooltip.textContent = title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: #333;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    white-space: nowrap;
                    pointer-events: none;
                `;
                
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                
                this._tooltip = tooltip;
            }
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
            
            const originalTitle = this.getAttribute('data-original-title');
            if (originalTitle) {
                this.setAttribute('title', originalTitle);
                this.removeAttribute('data-original-title');
            }
        });
    });
});

// ===== EXPORTAR AL SCOPE GLOBAL =====
window.App = App;
window.Utils = Utils;
window.Loading = Loading;
window.API = API;
window.Messages = Messages;
window.Forms = Forms;
window.Tables = Tables;
window.Modal = Modal;