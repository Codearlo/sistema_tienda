/**
 * ADD PRODUCT - JavaScript
 * Funcionalidades para agregar productos
 */

// Estado global
const AddProductState = {
    uploadedImages: [],
    maxImages: 5,
    isDraftSaved: false
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializeAddProduct();
});

function initializeAddProduct() {
    console.log('Inicializando formulario de agregar producto...');
    
    // Configurar eventos
    setupEventListeners();
    
    // Configurar validaciones
    setupValidations();
    
    // Cargar borrador si existe
    loadDraft();
    
    console.log('Formulario inicializado correctamente');
}

// ===== CONFIGURACIÓN DE EVENTOS =====
function setupEventListeners() {
    // Manejo de imágenes
    const imageUpload = document.getElementById('imageUpload');
    if (imageUpload) {
        imageUpload.addEventListener('change', handleImageUpload);
    }
    
    // Auto-guardar borrador
    const formInputs = document.querySelectorAll('#productForm input, #productForm select, #productForm textarea');
    formInputs.forEach(input => {
        input.addEventListener('input', debounce(autoSaveDraft, 2000));
    });
    
    // Prevenir pérdida de datos
    window.addEventListener('beforeunload', function(e) {
        if (isFormDirty() && !AddProductState.isDraftSaved) {
            e.preventDefault();
            e.returnValue = '';
            return 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
        }
    });
    
    // Validaciones en tiempo real
    const costPrice = document.getElementById('cost_price');
    const sellingPrice = document.getElementById('selling_price');
    
    if (costPrice && sellingPrice) {
        costPrice.addEventListener('input', calculateProfitMargin);
        sellingPrice.addEventListener('input', calculateProfitMargin);
    }
    
    // Generar SKU automático
    const nameInput = document.getElementById('name');
    const skuInput = document.getElementById('sku');
    
    if (nameInput && skuInput) {
        nameInput.addEventListener('input', function() {
            if (!skuInput.value) {
                generateSKU();
            }
        });
    }
}

// ===== MANEJO DE IMÁGENES =====
function triggerImageUpload() {
    if (AddProductState.uploadedImages.length >= AddProductState.maxImages) {
        showMessage(`Máximo ${AddProductState.maxImages} imágenes permitidas`, 'warning');
        return;
    }
    
    document.getElementById('imageUpload').click();
}

function handleImageUpload(event) {
    const files = Array.from(event.target.files);
    
    files.forEach(file => {
        if (AddProductState.uploadedImages.length >= AddProductState.maxImages) {
            showMessage(`Máximo ${AddProductState.maxImages} imágenes permitidas`, 'warning');
            return;
        }
        
        if (!file.type.startsWith('image/')) {
            showMessage('Solo se permiten archivos de imagen', 'error');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) { // 5MB
            showMessage('La imagen no debe superar los 5MB', 'error');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageData = {
                file: file,
                url: e.target.result,
                id: Date.now() + Math.random()
            };
            
            AddProductState.uploadedImages.push(imageData);
            renderImagePreviews();
        };
        reader.readAsDataURL(file);
    });
    
    // Limpiar input
    event.target.value = '';
}

function renderImagePreviews() {
    const container = document.getElementById('imagePreviews');
    if (!container) return;
    
    container.innerHTML = AddProductState.uploadedImages.map(image => `
        <div class="image-preview">
            <img src="${image.url}" alt="Preview">
            <button type="button" class="image-remove" onclick="removeImage('${image.id}')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

function removeImage(imageId) {
    AddProductState.uploadedImages = AddProductState.uploadedImages.filter(img => img.id !== imageId);
    renderImagePreviews();
}

// ===== CÁLCULOS Y VALIDACIONES =====
function calculateProfitMargin() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
    
    const marginContainer = document.getElementById('profitMargin');
    const marginValue = document.getElementById('marginValue');
    
    if (!marginContainer || !marginValue) return;
    
    if (costPrice > 0 && sellingPrice > 0) {
        const margin = ((sellingPrice - costPrice) / costPrice * 100).toFixed(1);
        marginValue.textContent = margin + '%';
        marginContainer.style.display = 'block';
        
        // Cambiar color según el margen
        marginContainer.className = 'profit-margin';
        if (margin < 10) {
            marginContainer.classList.add('low-margin');
        } else if (margin > 50) {
            marginContainer.classList.add('high-margin');
        }
    } else {
        marginContainer.style.display = 'none';
    }
}

function generateSKU() {
    const name = document.getElementById('name').value;
    if (!name) return;
    
    // Generar SKU basado en el nombre
    const sku = 'PROD' + name.substring(0, 3).toUpperCase() + Date.now().toString().slice(-4);
    document.getElementById('sku').value = sku;
}

function setupValidations() {
    const form = document.getElementById('productForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
}

function validateForm() {
    let isValid = true;
    const errors = [];
    
    // Validar nombre
    const name = document.getElementById('name').value.trim();
    if (!name) {
        errors.push('El nombre del producto es requerido');
        markFieldError('name');
        isValid = false;
    }
    
    // Validar precio de venta
    const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
    if (sellingPrice <= 0) {
        errors.push('El precio de venta debe ser mayor a 0');
        markFieldError('selling_price');
        isValid = false;
    }
    
    // Validar stock mínimo no mayor al inicial
    const stockQuantity = parseInt(document.getElementById('stock_quantity').value) || 0;
    const minStock = parseInt(document.getElementById('min_stock').value) || 0;
    
    if (minStock > stockQuantity && stockQuantity > 0) {
        errors.push('El stock mínimo no puede ser mayor al stock inicial');
        markFieldError('min_stock');
        isValid = false;
    }
    
    // Mostrar errores
    if (errors.length > 0) {
        showMessage(errors.join('<br>'), 'error');
    }
    
    return isValid;
}

function markFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.classList.add('error');
        setTimeout(() => {
            field.classList.remove('error');
        }, 3000);
    }
}

// ===== GESTIÓN DE CATEGORÍAS =====
function openCategoryModal() {
    const modal = document.getElementById('categoryModal');
    if (modal) {
        modal.style.display = 'flex';
        // Enfocar primer campo
        setTimeout(() => {
            const firstInput = modal.querySelector('input');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    if (modal) {
        modal.style.display = 'none';
        // Limpiar formulario
        const form = document.getElementById('categoryForm');
        if (form) form.reset();
    }
}

async function saveCategory(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const categoryData = Object.fromEntries(formData.entries());
    
    if (!categoryData.name.trim()) {
        showMessage('El nombre de la categoría es requerido', 'error');
        return;
    }
    
    try {
        showLoading('Creando categoría...');
        
        const response = await fetch('backend/api/index.php?endpoint=categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(categoryData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Agregar nueva categoría al select
            const categorySelect = document.getElementById('category_id');
            if (categorySelect) {
                const option = document.createElement('option');
                option.value = result.data.id;
                option.textContent = categoryData.name;
                option.selected = true;
                categorySelect.appendChild(option);
            }
            
            showMessage('Categoría creada exitosamente', 'success');
            closeCategoryModal();
        } else {
            showMessage(result.message || 'Error al crear la categoría', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    } finally {
        hideLoading();
    }
}

// ===== GESTIÓN DE BORRADORES =====
function autoSaveDraft() {
    if (!isFormDirty()) return;
    
    const formData = getFormData();
    localStorage.setItem('product_draft', JSON.stringify({
        data: formData,
        timestamp: Date.now()
    }));
    
    AddProductState.isDraftSaved = true;
    showMessage('Borrador guardado automáticamente', 'info', 2000);
}

function saveDraft() {
    const formData = getFormData();
    
    if (Object.keys(formData).length === 0) {
        showMessage('No hay datos para guardar', 'warning');
        return;
    }
    
    localStorage.setItem('product_draft', JSON.stringify({
        data: formData,
        timestamp: Date.now()
    }));
    
    AddProductState.isDraftSaved = true;
    showMessage('Borrador guardado', 'success');
}

function loadDraft() {
    const draft = localStorage.getItem('product_draft');
    if (!draft) return;
    
    try {
        const draftData = JSON.parse(draft);
        const data = draftData.data;
        
        // Verificar que el borrador no sea muy antiguo (24 horas)
        if (Date.now() - draftData.timestamp > 24 * 60 * 60 * 1000) {
            localStorage.removeItem('product_draft');
            return;
        }
        
        // Confirmar si cargar borrador
        if (confirm('Se encontró un borrador guardado. ¿Deseas cargarlo?')) {
            Object.keys(data).forEach(key => {
                const field = document.getElementById(key);
                if (field) {
                    field.value = data[key];
                    
                    // Disparar evento input para cálculos
                    field.dispatchEvent(new Event('input'));
                }
            });
            
            showMessage('Borrador cargado', 'info');
        }
    } catch (error) {
        console.error('Error cargando borrador:', error);
        localStorage.removeItem('product_draft');
    }
}

function clearDraft() {
    localStorage.removeItem('product_draft');
    AddProductState.isDraftSaved = false;
}

// ===== FUNCIONES AUXILIARES =====
function getFormData() {
    const form = document.getElementById('productForm');
    if (!form) return {};
    
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (value && value.trim && value.trim() !== '') {
            data[key] = value;
        }
    }
    
    return data;
}

function isFormDirty() {
    const formData = getFormData();
    return Object.keys(formData).length > 0;
}

function bulkUpload() {
    showMessage('Función de carga masiva próximamente', 'info');
}

// ===== UTILIDADES =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showMessage(message, type = 'info', duration = 3000) {
    // Crear elemento de mensaje
    const messageEl = document.createElement('div');
    messageEl.className = `alert alert-${type} message-toast`;
    messageEl.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Agregar al documento
    document.body.appendChild(messageEl);
    
    // Animar entrada
    setTimeout(() => {
        messageEl.classList.add('show');
    }, 100);
    
    // Remover después del tiempo especificado
    setTimeout(() => {
        messageEl.classList.remove('show');
        setTimeout(() => {
            if (messageEl.parentElement) {
                messageEl.parentElement.removeChild(messageEl);
            }
        }, 300);
    }, duration);
}

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

// ===== EVENTOS GLOBALES =====
// Cerrar modales con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal[style*="flex"]');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
});

// Cerrar modales clickeando fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Limpiar borrador al enviar formulario exitosamente
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('productForm');
    if (form) {
        form.addEventListener('submit', function() {
            // Si hay mensaje de éxito, limpiar borrador
            setTimeout(() => {
                const successAlert = document.querySelector('.alert-success');
                if (successAlert) {
                    clearDraft();
                }
            }, 1000);
        });
    }
});