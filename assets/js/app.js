/**
 * TREINTA APP - JavaScript Principal
 * Versión: 1.0.0
 * Archivo: assets/js/app.js
 */

// ===== CONFIGURACIÓN GLOBAL =====
const CONFIG = {
    API_BASE_URL: 'api/',
    APP_NAME: 'Treinta',
    VERSION: '1.0.0',
    CURRENCY_SYMBOL: 'S/',
    DECIMAL_PLACES: 2,
    DATE_FORMAT: 'DD/MM/YYYY',
    DATETIME_FORMAT: 'DD/MM/YYYY HH:mm'
};

// ===== UTILIDADES GLOBALES =====
const Utils = {
    // Formatear moneda
    formatCurrency(amount, includeSymbol = true) {
        const formatted = parseFloat(amount || 0).toFixed(CONFIG.DECIMAL_PLACES);
        const withCommas = formatted.replace(/\d(?=(\d{3})+\.)/g, '$&,');
        return includeSymbol ? `${CONFIG.CURRENCY_SYMBOL} ${withCommas}` : withCommas;
    },

    // Formatear fecha
    formatDate(date, format = CONFIG.DATE_FORMAT) {
        if (!date) return '';
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';
        
        const day = d.getDate().toString().padStart(2, '0');
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const year = d.getFullYear();
        const hours = d.getHours().toString().padStart(2, '0');
        const minutes = d.getMinutes().toString().padStart(2, '0');
        
        return format
            .replace('DD', day)
            .replace('MM', month)
            .replace('YYYY', year)
            .replace('HH', hours)
            .replace('mm', minutes);
    },

    // Limpiar y validar input
    cleanInput(value) {
        if (typeof value !== 'string') return value;
        return value.trim().replace(/[<>]/g, '');
    },

    // Debounce para optimizar búsquedas
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

    // Generar ID único
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    // Validar email
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // Capitalizar primera letra
    capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }
};

// ===== MANEJO DE APIS =====
const API = {
    // Realizar petición HTTP
    async request(endpoint, options = {}) {
        const url = `${CONFIG.API_BASE_URL}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
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

// ===== SISTEMA DE NOTIFICACIONES =====
const Notifications = {
    container: null,

    init() {
        // Crear contenedor de notificaciones si no existe
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'notifications-container';
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

        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.style.cssText = `
            margin-bottom: 10px;
            pointer-events: auto;
            animation: slideInRight 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        `;

        const icon = this.getIcon(type);
        notification.innerHTML = `
            ${icon}
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.remove()" 
                    style="margin-left: 10px; background: none; border: none; 
                           font-size: 18px; cursor: pointer; opacity: 0.7;">&times;</button>
        `;

        this.container.appendChild(notification);

        // Auto-remove
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => notification.remove(), 300);
                }
            }, duration);
        }

        return notification;
    },

    getIcon(type) {
        const icons = {
            success: `<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"></path>
                        <circle cx="12" cy="12" r="10"></circle>
                      </svg>`,
            error: `<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="15" y1="9" x2="9" y2="15"></line>
                      <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>`,
            warning: `<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                      </svg>`,
            info: `<svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                     <circle cx="12" cy="12" r="10"></circle>
                     <line x1="12" y1="16" x2="12" y2="12"></line>
                     <line x1="12" y1="8" x2="12.01" y2="8"></line>
                   </svg>`
        };
        return icons[type] || icons.info;
    },

    success(message, duration) {
        return this.show(message, 'success', duration);
    },

    error(message, duration) {
        return this.show(message, 'error', duration);
    },

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    },

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
};

// ===== SISTEMA DE MODALES =====
const Modal = {
    create(options = {}) {
        const {
            title = 'Modal',
            content = '',
            size = 'medium',
            closable = true,
            onShow = null,
            onHide = null
        } = options;

        // Crear overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        
        // Crear modal
        const modal = document.createElement('div');
        modal.className = `modal modal-${size}`;
        
        modal.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
                ${closable ? '<button class="modal-close" data-modal-close>&times;</button>' : ''}
            </div>
            <div class="modal-body">
                ${content}
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Event listeners
        if (closable) {
            const closeBtn = modal.querySelector('[data-modal-close]');
            closeBtn.addEventListener('click', () => this.hide(overlay));
            
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.hide(overlay);
                }
            });
        }

        // Keyboard events
        const handleKeydown = (e) => {
            if (e.key === 'Escape' && closable) {
                this.hide(overlay);
            }
        };
        document.addEventListener('keydown', handleKeydown);

        const modalInstance = {
            element: overlay,
            modal: modal,
            show: () => this.show(overlay, onShow),
            hide: () => this.hide(overlay, onHide),
            setContent: (newContent) => {
                modal.querySelector('.modal-body').innerHTML = newContent;
            },
            setTitle: (newTitle) => {
                modal.querySelector('.modal-title').textContent = newTitle;
            },
            destroy: () => {
                document.removeEventListener('keydown', handleKeydown);
                if (overlay.parentElement) {
                    overlay.remove();
                }
            }
        };

        return modalInstance;
    },

    show(overlay, callback) {
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        if (callback) callback();
    },

    hide(overlay, callback) {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        if (callback) callback();
        
        // Delay para permitir la animación
        setTimeout(() => {
            if (overlay.parentElement) {
                overlay.remove();
            }
        }, 300);
    },

    confirm(message, title = 'Confirmar') {
        return new Promise((resolve) => {
            const modal = this.create({
                title: title,
                content: `
                    <p style="margin-bottom: 1.5rem; color: var(--gray-700);">${message}</p>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button class="btn" data-modal-cancel style="background-color: var(--gray-500); color: white;">Cancelar</button>
                        <button class="btn btn-primary" data-modal-confirm>Confirmar</button>
                    </div>
                `,
                closable: false
            });

            modal.modal.querySelector('[data-modal-confirm]').addEventListener('click', () => {
                modal.hide();
                resolve(true);
            });

            modal.modal.querySelector('[data-modal-cancel]').addEventListener('click', () => {
                modal.hide();
                resolve(false);
            });

            modal.show();
        });
    }
};

// ===== GESTIÓN DE FORMULARIOS =====
const Forms = {
    // Validar formulario
    validate(form) {
        const errors = [];
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                errors.push(`${this.getFieldLabel(input)} es requerido`);
                this.markFieldError(input);
            } else {
                this.clearFieldError(input);
                
                // Validaciones específicas
                if (input.type === 'email' && !Utils.validateEmail(input.value)) {
                    errors.push(`${this.getFieldLabel(input)} debe ser un email válido`);
                    this.markFieldError(input);
                }
                
                if (input.type === 'number') {
                    const num = parseFloat(input.value);
                    if (isNaN(num) || num < 0) {
                        errors.push(`${this.getFieldLabel(input)} debe ser un número válido`);
                        this.markFieldError(input);
                    }
                }
            }
        });

        return {
            isValid: errors.length === 0,
            errors: errors
        };
    },

    getFieldLabel(input) {
        const label = input.closest('.form-group')?.querySelector('label');
        return label ? label.textContent.replace('*', '').trim() : input.name;
    },

    markFieldError(input) {
        input.style.borderColor = 'var(--error-500)';
        input.classList.add('error');
    },

    clearFieldError(input) {
        input.style.borderColor = '';
        input.classList.remove('error');
    },

    // Serializar formulario a objeto
    serialize(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            // Manejar campos múltiples (checkboxes, etc.)
            if (data[key]) {
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

    // Llenar formulario con datos
    populate(form, data) {
        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = data[key];
                } else {
                    field.value = data[key];
                }
            }
        });
    },

    // Limpiar formulario
    clear(form) {
        form.reset();
        const errors = form.querySelectorAll('.error');
        errors.forEach(field => this.clearFieldError(field));
    }
};

// ===== GESTIÓN DE TABLAS =====
const Tables = {
    // Hacer tabla responsive
    makeResponsive(table) {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        wrapper.style.overflowX = 'auto';
        
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    },

    // Agregar funcionalidad de ordenamiento
    makeSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.innerHTML += ' <span class="sort-icon">↕️</span>';
            
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                const isAscending = !header.classList.contains('sort-asc');
                
                // Limpiar otras columnas
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                
                // Marcar columna actual
                header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
                
                // Ordenar filas
                rows.sort((a, b) => {
                    const aVal = a.children[header.cellIndex].textContent.trim();
                    const bVal = b.children[header.cellIndex].textContent.trim();
                    
                    // Detectar si es número
                    const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
                    const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAscending ? aNum - bNum : bNum - aNum;
                    }
                    
                    // Ordenamiento alfabético
                    return isAscending ? 
                        aVal.localeCompare(bVal) : 
                        bVal.localeCompare(aVal);
                });
                
                // Reordenar en el DOM
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    },

    // Agregar paginación
    addPagination(table, rowsPerPage = 10) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const totalPages = Math.ceil(rows.length / rowsPerPage);
        let currentPage = 1;

        const showPage = (page) => {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });
        };

        const createPagination = () => {
            const existing = table.parentNode.querySelector('.pagination');
            if (existing) existing.remove();

            const pagination = document.createElement('div');
            pagination.className = 'pagination';
            pagination.style.cssText = `
                display: flex;
                justify-content: center;
                gap: 0.5rem;
                margin-top: 1rem;
            `;

            // Botón anterior
            const prevBtn = document.createElement('button');
            prevBtn.textContent = '‹';
            prevBtn.className = 'btn';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    showPage(currentPage);
                    createPagination();
                }
            });
            pagination.appendChild(prevBtn);

            // Números de página
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = 'btn';
                if (i === currentPage) {
                    pageBtn.style.backgroundColor = 'var(--primary-600)';
                    pageBtn.style.color = 'white';
                }
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    showPage(currentPage);
                    createPagination();
                });
                pagination.appendChild(pageBtn);
            }

            // Botón siguiente
            const nextBtn = document.createElement('button');
            nextBtn.textContent = '›';
            nextBtn.className = 'btn';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    showPage(currentPage);
                    createPagination();
                }
            });
            pagination.appendChild(nextBtn);

            table.parentNode.insertBefore(pagination, table.nextSibling);
        };

        showPage(currentPage);
        createPagination();
    }
};

// ===== GESTIÓN DE LOCAL STORAGE =====
const Storage = {
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Error saving to localStorage:', error);
            return false;
        }
    },

    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Error reading from localStorage:', error);
            return defaultValue;
        }
    },

    remove(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Error removing from localStorage:', error);
            return false;
        }
    },

    clear() {
        try {
            localStorage.clear();
            return true;
        } catch (error) {
            console.error('Error clearing localStorage:', error);
            return false;
        }
    }
};

// ===== INICIALIZACIÓN GLOBAL =====
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar sistemas
    Notifications.init();
    
    // Manejar formularios globalmente
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('ajax-form')) {
            e.preventDefault();
            handleAjaxForm(form);
        }
    });

    // Manejar enlaces AJAX
    document.addEventListener('click', function(e) {
        const link = e.target.closest('[data-ajax]');
        if (link) {
            e.preventDefault();
            handleAjaxLink(link);
        }
    });

    // Auto-inicializar componentes
    initializeComponents();
    
    // Configurar manejo de errores global
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        Notifications.error('Ha ocurrido un error inesperado');
    });

    // Configurar tooltips
    initializeTooltips();
    
    console.log(`${CONFIG.APP_NAME} v${CONFIG.VERSION} inicializado correctamente`);
});

// ===== FUNCIONES DE MANEJO AJAX =====
async function handleAjaxForm(form) {
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
        // Validar formulario
        const validation = Forms.validate(form);
        if (!validation.isValid) {
            validation.errors.forEach(error => Notifications.error(error));
            return;
        }

        // Mostrar loading
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        // Enviar datos
        const formData = Forms.serialize(form);
        const response = await API.post(form.action || '', formData);

        // Manejar respuesta
        if (response.success) {
            Notifications.success(response.message || 'Operación exitosa');
            if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1000);
            }
            if (form.classList.contains('clear-on-success')) {
                Forms.clear(form);
            }
        } else {
            Notifications.error(response.message || 'Error en la operación');
        }

    } catch (error) {
        console.error('Form submission error:', error);
        Notifications.error('Error de conexión. Intente nuevamente.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function handleAjaxLink(link) {
    const url = link.getAttribute('href') || link.dataset.url;
    const method = link.dataset.method || 'GET';
    const confirm = link.dataset.confirm;

    try {
        // Confirmar acción si es necesario
        if (confirm) {
            const confirmed = await Modal.confirm(confirm);
            if (!confirmed) return;
        }

        // Realizar petición
        const response = await API.request(url, { method });

        // Manejar respuesta
        if (response.success) {
            Notifications.success(response.message || 'Operación exitosa');
            if (response.redirect) {
                window.location.href = response.redirect;
            } else if (response.reload) {
                window.location.reload();
            }
        } else {
            Notifications.error(response.message || 'Error en la operación');
        }

    } catch (error) {
        console.error('Ajax link error:', error);
        Notifications.error('Error de conexión. Intente nuevamente.');
    }
}

// ===== INICIALIZACIÓN DE COMPONENTES =====
function initializeComponents() {
    // Tablas responsive
    document.querySelectorAll('.table-responsive').forEach(Tables.makeResponsive);
    
    // Tablas ordenables
    document.querySelectorAll('.table-sortable').forEach(Tables.makeSortable);
    
    // Máscaras de input
    initializeInputMasks();
    
    // Auto-resize de textareas
    document.querySelectorAll('textarea[data-auto-resize]').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
}

function initializeInputMasks() {
    // Máscara para moneda
    document.querySelectorAll('input[data-mask="currency"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = (parseInt(value) / 100).toFixed(2);
                e.target.value = Utils.formatCurrency(value, false);
            }
        });
    });

    // Máscara para números
    document.querySelectorAll('input[data-mask="number"]').forEach(input => {
        input.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d]/g, '');
        });
    });
}

function initializeTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: var(--gray-900);
                color: white;
                padding: 0.5rem;
                border-radius: 4px;
                font-size: 0.875rem;
                z-index: 1000;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.2s;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            setTimeout(() => tooltip.style.opacity = '1', 10);
            
            this.addEventListener('mouseleave', function() {
                if (tooltip.parentElement) {
                    tooltip.remove();
                }
            }, { once: true });
        });
    });
}

// ===== ANIMACIONES CSS =====
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .error {
        border-color: var(--error-500) !important;
    }
`;
document.head.appendChild(style);

// ===== EXPORTAR PARA USO GLOBAL =====
window.Utils = Utils;
window.API = API;
window.Notifications = Notifications;
window.Modal = Modal;
window.Forms = Forms;
window.Tables = Tables;
window.Storage = Storage;