/**
 * MODALS MANAGEMENT - JavaScript
 * Gestión de modales y ventanas emergentes
 */

// ===== CONFIGURACIÓN GLOBAL DE MODALES =====
document.addEventListener('DOMContentLoaded', function() {
    setupModalEvents();
});

function setupModalEvents() {
    // Cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Cerrar modales clickeando en el overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Prevenir que los clicks dentro del modal lo cierren
    document.querySelectorAll('.modal-content').forEach(content => {
        content.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
}

// ===== FUNCIONES GENERALES DE MODALES =====
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        // Forzar reflow para animación
        modal.offsetHeight;
        modal.classList.add('modal-open');
        
        // Enfocar primer input si existe
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('modal-open');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('modal-open');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    });
}

// ===== MODALES ESPECÍFICOS =====

// Modal de Producto
function openProductModal(productId = null) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');
    
    if (!modal || !modalTitle || !form) {
        console.error('Elementos del modal de producto no encontrados');
        return;
    }
    
    if (productId) {
        // Editar producto existente
        const product = ProductsState.products.find(p => p.id == productId);
        if (!product) {
            showMessage('Producto no encontrado', 'error');
            return;
        }
        
        modalTitle.textContent = 'Editar Producto';
        fillProductForm(product);
        ProductsState.editingProduct = productId;
    } else {
        // Nuevo producto
        modalTitle.textContent = 'Nuevo Producto';
        form.reset();
        ProductsState.editingProduct = null;
    }
    
    openModal('productModal');
}

function closeProductModal() {
    closeModal('productModal');
    ProductsState.editingProduct = null;
}

function fillProductForm(product) {
    const fields = {
        'productId': product.id,
        'productName': product.name,
        'productCategory': product.category_id || '',
        'productSku': product.sku || '',
        'productBarcode': product.barcode || '',
        'productCost': product.cost_price || '',
        'productPrice': product.selling_price,
        'productStock': product.stock_quantity || '',
        'productMinStock': product.min_stock || '',
        'productDescription': product.description || ''
    };
    
    Object.keys(fields).forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.value = fields[fieldId];
        }
    });
}

// Modal de Categoría
function openCategoryModal() {
    const modal = document.getElementById('categoryModal');
    if (modal) {
        openModal('categoryModal');
    }
}

function closeCategoryModal() {
    closeModal('categoryModal');
    const form = document.getElementById('categoryForm');
    if (form) {
        form.reset();
    }
}

// Modal de Ajuste de Stock
function openStockModal(productId) {
    const product = ProductsState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    const modal = document.getElementById('stockModal');
    if (!modal) return;
    
    // Llenar información del producto
    const elements = {
        'stockProductId': productId,
        'stockProductName': product.name,
        'currentStock': product.stock_quantity || 0
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            if (element.tagName === 'INPUT') {
                element.value = elements[id];
            } else {
                element.textContent = elements[id];
            }
        }
    });
    
    // Limpiar formulario
    document.getElementById('stockQuantity').value = '';
    document.getElementById('adjustmentReason').value = '';
    document.getElementById('adjustmentType').value = 'add';
    updateAdjustmentType();
    
    openModal('stockModal');
}

function closeStockModal() {
    closeModal('stockModal');
}

function updateAdjustmentType() {
    const type = document.getElementById('adjustmentType')?.value;
    const label = document.getElementById('quantityLabel');
    
    if (!label) return;
    
    const labels = {
        'add': 'Cantidad a agregar',
        'remove': 'Cantidad a reducir',
        'set': 'Nuevo stock total'
    };
    
    label.textContent = labels[type] || 'Cantidad';
}

// ===== CONFIRMACIONES =====
function showConfirmModal(title, message, onConfirm, onCancel = null) {
    // Crear modal de confirmación dinámicamente
    const confirmModal = document.createElement('div');
    confirmModal.className = 'modal';
    confirmModal.innerHTML = `
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>${title}</h3>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmAction()">Confirmar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(confirmModal);
    
    // Funciones temporales
    window.confirmAction = function() {
        if (onConfirm) onConfirm();
        closeConfirmModal();
    };
    
    window.closeConfirmModal = function() {
        if (onCancel) onCancel();
        document.body.removeChild(confirmModal);
        delete window.confirmAction;
        delete window.closeConfirmModal;
    };
    
    // Mostrar modal
    confirmModal.style.display = 'flex';
    
    // Cerrar con Escape
    function handleEscape(e) {
        if (e.key === 'Escape') {
            closeConfirmModal();
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);
    
    // Cerrar clickeando fuera
    confirmModal.addEventListener('click', function(e) {
        if (e.target === confirmModal) {
            closeConfirmModal();
        }
    });
}

// ===== ALERTAS Y NOTIFICACIONES =====
function showAlert(message, type = 'info', duration = 3000) {
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${type} alert-toast`;
    
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-triangle',
        'warning': 'fa-exclamation-circle',
        'info': 'fa-info-circle'
    };
    
    alertEl.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(alertEl);
    
    // Animar entrada
    setTimeout(() => {
        alertEl.classList.add('show');
    }, 100);
    
    // Auto-remover
    if (duration > 0) {
        setTimeout(() => {
            alertEl.classList.remove('show');
            setTimeout(() => {
                if (alertEl.parentElement) {
                    alertEl.parentElement.removeChild(alertEl);
                }
            }, 300);
        }, duration);
    }
    
    return alertEl;
}

// Alias para compatibilidad
function showMessage(message, type = 'info') {
    showAlert(message, type);
}

// ===== LOADING Y ESTADOS =====
function showLoading(message = 'Cargando...') {
    const loadingEl = document.createElement('div');
    loadingEl.id = 'globalLoading';
    loadingEl.className = 'modal';
    loadingEl.innerHTML = `
        <div class="modal-content" style="max-width: 300px; text-align: center;">
            <div class="modal-body">
                <div class="spinner"></div>
                <p style="margin: 1rem 0 0 0;">${message}</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(loadingEl);
    loadingEl.style.display = 'flex';
    
    return loadingEl;
}

function hideLoading() {
    const loadingEl = document.getElementById('globalLoading');
    if (loadingEl) {
        loadingEl.remove();
    }
}

// ===== VALIDACIÓN DE FORMULARIOS =====
function validateForm(formId, rules = {}) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const formData = new FormData(form);
    
    // Limpiar errores previos
    form.querySelectorAll('.error-message').forEach(el => el.remove());
    form.querySelectorAll('.form-input.error').forEach(el => el.classList.remove('error'));
    
    // Validar campos requeridos
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'Este campo es requerido');
            isValid = false;
        }
    });
    
    // Validaciones personalizadas
    Object.keys(rules).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        const value = formData.get(fieldName);
        const rule = rules[fieldName];
        
        if (field && rule && !rule.test(value)) {
            showFieldError(field, rule.message || 'Valor inválido');
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    const errorEl = document.createElement('div');
    errorEl.className = 'error-message';
    errorEl.textContent = message;
    
    field.parentNode.appendChild(errorEl);
}

// ===== ESTILOS ADICIONALES PARA MODALES =====
const modalStyles = `
    .modal {
        backdrop-filter: blur(4px);
        animation: modalFadeIn 0.3s ease;
    }
    
    .modal-open .modal-content {
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes modalSlideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .alert-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1100;
        min-width: 300px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .alert-toast.show {
        transform: translateX(0);
    }
    
    .alert-close {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 0.25rem;
        margin-left: auto;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .alert-close:hover {
        opacity: 1;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid var(--primary-500);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .form-input.error {
        border-color: var(--danger-500);
        box-shadow: 0 0 0 3px rgb(239 68 68 / 0.1);
    }
    
    .error-message {
        color: var(--danger-600);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
`;

// Agregar estilos al documento
if (!document.getElementById('modal-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'modal-styles';
    styleSheet.textContent = modalStyles;
    document.head.appendChild(styleSheet);
}