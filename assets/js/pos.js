/**
 * POINT OF SALE (POS) - JavaScript Completo Corregido
 * Sistema funcional de punto de venta con modal de pago arreglado
 */

// Estado global del POS
const POSState = {
    cart: [],
    products: [],
    categories: [],
    customers: [],
    selectedCategory: null,
    paymentMethod: 'cash',
    cashReceived: 0,
    includeIgv: true,
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializePOS();
});

function initializePOS() {
    console.log('🚀 Inicializando POS...');
    
    // Usar datos del PHP
    if (typeof products_data !== 'undefined') {
        POSState.products = products_data;
    } else {
        console.warn('products_data no está definido en el ámbito global.');
    }
    if (typeof categories_data !== 'undefined') {
        POSState.categories = categories_data;
    } else {
        console.warn('categories_data no está definido en el ámbito global.');
    }
    if (typeof customers_data !== 'undefined') {
        POSState.customers = customers_data;
    } else {
        console.warn('customers_data no está definido en el ámbito global.');
    }

    console.log('📦 POSState.products después de inicializar:', POSState.products);
    console.log('📂 POSState.categories después de inicializar:', POSState.categories);
    console.log('👥 POSState.customers después de inicializar:', POSState.customers);
    
    updateClock();
    setInterval(updateClock, 1000);
    
    setupEventListeners();
    
    loadCategories();
    loadProducts();
    updateCartDisplay();
    updateTotals();
    setupPaymentMethods(); 
    updateIgvButtonState();
    
    console.log('✅ POS inicializado correctamente');
}

// ===== CONFIGURACIÓN DE EVENTOS =====
function setupEventListeners() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', handleProductSearch);
    }
    
    const clearBtn = document.querySelector('.search-clear-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearSearch);
    }
    
    const cashInput = document.getElementById('cashReceivedInput'); 
    if (cashInput) {
        cashInput.addEventListener('input', calculateChange);
    }
    
    const paymentMethodButtons = document.querySelectorAll('.payment-section .payment-method-btn'); 
    paymentMethodButtons.forEach(btn => {
        btn.addEventListener('click', () => selectPaymentMethod(btn.dataset.method));
    });
}

// ===== SISTEMA DE MODALES UNIFICADO ===== 
const ModalSystem = {
    
    // Abrir modal con manejo robusto
    open(modalId) {
        console.log('🔓 Abriendo modal:', modalId);
        
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('❌ Modal no encontrado:', modalId);
            return false;
        }
        
        // Remover cualquier clase conflictiva
        modal.classList.remove('modal-open');
        
        // Aplicar estilos y clases necesarias
        modal.style.display = 'flex';
        modal.classList.add('show');
        
        // Prevenir scroll del body
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        
        // Forzar reflow para asegurar la animación
        modal.offsetHeight;
        
        // Enfocar primer elemento interactivo
        setTimeout(() => {
            const focusable = modal.querySelector('input, select, textarea, button:not(.modal-close)');
            if (focusable) {
                focusable.focus();
            }
        }, 100);
        
        console.log('✅ Modal abierto exitosamente:', modalId);
        return true;
    },
    
    // Cerrar modal con cleanup completo
    close(modalId) {
        console.log('🔒 Cerrando modal:', modalId);
        
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.warn('⚠️ Modal no encontrado al cerrar:', modalId);
            return false;
        }
        
        // Remover clases de visualización
        modal.classList.remove('show');
        
        // Restaurar scroll del body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        
        // Ocultar después de la animación
        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('modal-open'); // Limpiar cualquier clase residual
        }, 300);
        
        console.log('✅ Modal cerrado exitosamente:', modalId);
        return true;
    },
    
    // Cerrar todos los modales
    closeAll() {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            if (modal.id) {
                this.close(modal.id);
            }
        });
        
        // Forzar limpieza del body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }
};

// ===== FUNCIONES GLOBALES PARA COMPATIBILIDAD =====
function openModal(modalId) {
    return ModalSystem.open(modalId);
}

function closeModal(modalId) {
    return ModalSystem.close(modalId);
}

// ===== MANEJO DE PRODUCTOS =====
function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    if (!grid) {
        console.error('categoriesGrid no encontrado.');
        return;
    }
    
    let html = `
        <button class="category-btn ${!POSState.selectedCategory ? 'active' : ''}" 
                onclick="selectCategory(null)">
            <i class="fas fa-th-large"></i>
            <span>Todos</span>
        </button>
    `;
    
    POSState.categories.forEach(category => {
        html += `
            <button class="category-btn ${POSState.selectedCategory === category.id ? 'active' : ''}" 
                    onclick="selectCategory(${category.id})">
                <i class="fas fa-tag"></i>
                <span>${category.name}</span>
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

function selectCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    loadCategories();
    loadProducts();
}

function loadProducts() {
    const grid = document.getElementById('productsGrid');
    if (!grid) {
        console.error('productsGrid no encontrado.');
        return;
    }
    
    let filteredProducts = POSState.products;
    
    if (POSState.selectedCategory) {
        filteredProducts = POSState.products.filter(product => 
            product.category_id == POSState.selectedCategory
        );
    }
    
    if (filteredProducts.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No hay productos</h3>
                <p>No se encontraron productos en esta categoría</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = filteredProducts.map(product => `
        <div class="product-card" onclick="addToCart(${product.id})">
            <div class="product-image">
                <img src="${product.image_url || 'assets/images/product-placeholder.png'}" 
                     alt="${product.name}" 
                     onerror="this.src='assets/images/product-placeholder.png'">
            </div>
            <div class="product-info">
                <h4>${product.name}</h4>
                <p class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</p>
                <p class="product-stock">Stock: ${product.stock_quantity || 0}</p>
            </div>
        </div>
    `).join('');
}

function handleProductSearch() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    
    let filteredProducts = POSState.products.filter(product =>
        product.name.toLowerCase().includes(searchTerm) ||
        (product.sku && product.sku.toLowerCase().includes(searchTerm)) ||
        (product.barcode && product.barcode.toLowerCase().includes(searchTerm))
    );
    
    if (POSState.selectedCategory) {
        filteredProducts = filteredProducts.filter(product => 
            product.category_id == POSState.selectedCategory
        );
    }
    
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    if (filteredProducts.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Sin resultados</h3>
                <p>No se encontraron productos que coincidan con "${searchTerm}"</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = filteredProducts.map(product => `
        <div class="product-card" onclick="addToCart(${product.id})">
            <div class="product-image">
                <img src="${product.image_url || 'assets/images/product-placeholder.png'}" 
                     alt="${product.name}" 
                     onerror="this.src='assets/images/product-placeholder.png'">
            </div>
            <div class="product-info">
                <h4>${product.name}</h4>
                <p class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</p>
                <p class="product-stock">Stock: ${product.stock_quantity || 0}</p>
            </div>
        </div>
    `).join('');
}

function clearSearch() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.value = '';
        loadProducts();
    }
}

// ===== MANEJO DEL CARRITO =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    if (product.stock_quantity <= 0) {
        showMessage('Producto sin stock disponible', 'warning');
        return;
    }
    
    const existingItem = POSState.cart.find(item => item.product_id == productId);
    
    if (existingItem) {
        if (existingItem.quantity >= product.stock_quantity) {
            showMessage('No hay suficiente stock disponible', 'warning');
            return;
        }
        existingItem.quantity++;
        existingItem.subtotal = existingItem.quantity * existingItem.price;
    } else {
        POSState.cart.push({
            product_id: product.id,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            subtotal: parseFloat(product.selling_price),
            stock_available: product.stock_quantity
        });
    }
    
    updateCartDisplay();
    updateTotals();
    
    showMessage(`${product.name} agregado al carrito`, 'success');
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    if (!cartItems) return;
    
    if (POSState.cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>Carrito vacío</h3>
                <p>Agrega productos para comenzar una venta</p>
            </div>
        `;
        return;
    }
    
    cartItems.innerHTML = POSState.cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-info">
                <h4>${item.name}</h4>
                <p class="item-price">S/ ${item.price.toFixed(2)} c/u</p>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-controls">
                    <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})">-</button>
                    <input type="number" class="quantity-input" value="${item.quantity}" 
                           onchange="updateQuantity(${item.product_id}, this.value)" min="1" max="${item.stock_available}">
                    <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">+</button>
                </div>
                <span class="item-total">S/ ${item.subtotal.toFixed(2)}</span>
                <button class="remove-btn" onclick="removeFromCart(${item.product_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function updateQuantity(productId, newQuantity) {
    const quantity = parseInt(newQuantity);
    
    if (quantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    const item = POSState.cart.find(item => item.product_id == productId);
    if (!item) return;
    
    if (quantity > item.stock_available) {
        showMessage('Cantidad excede el stock disponible', 'warning');
        return;
    }
    
    item.quantity = quantity;
    item.subtotal = item.quantity * item.price;
    
    updateCartDisplay();
    updateTotals();
}

function removeFromCart(productId) {
    POSState.cart = POSState.cart.filter(item => item.product_id != productId);
    updateCartDisplay();
    updateTotals();
}

// ===== CÁLCULOS Y TOTALES =====
function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;
    
    const subtotalElement = document.getElementById('subtotalAmount');
    const taxElement = document.getElementById('taxAmount');
    const totalElement = document.getElementById('totalAmount');
    
    if (subtotalElement) subtotalElement.textContent = `S/ ${subtotal.toFixed(2)}`;
    if (taxElement) taxElement.textContent = `S/ ${tax.toFixed(2)}`;
    if (totalElement) totalElement.textContent = `S/ ${total.toFixed(2)}`;
    
    const completeBtn = document.getElementById('completeSaleBtn');
    if (completeBtn) {
        completeBtn.disabled = POSState.cart.length === 0;
    }
    
    calculateChange();
}

function toggleIgv() {
    POSState.includeIgv = !POSState.includeIgv;
    updateTotals();
    updateIgvButtonState();
}

function updateIgvButtonState() {
    const igvBtn = document.getElementById('igvToggleBtn');
    if (igvBtn) {
        if (POSState.includeIgv) {
            igvBtn.classList.add('active');
            igvBtn.innerHTML = '<i class="fas fa-check"></i> IGV Incluido';
        } else {
            igvBtn.classList.remove('active');
            igvBtn.innerHTML = '<i class="fas fa-times"></i> Sin IGV';
        }
    }
}

function calculateChange() {
    const cashInput = document.getElementById('cashReceivedInput');
    const changeAmountDiv = document.getElementById('changeAmount');
    const changeValueSpan = document.getElementById('changeValue');
    const completeBtn = document.getElementById('completeSaleBtn');
    
    if (!cashInput || !changeAmountDiv || !changeValueSpan || !completeBtn) {
        console.warn('Elementos de cambio no encontrados');
        return;
    }
    
    if (POSState.paymentMethod !== 'cash') {
        changeAmountDiv.style.display = 'none';
        completeBtn.disabled = POSState.cart.length === 0;
        completeBtn.classList.remove('btn-success');
        completeBtn.classList.add('btn-primary');
        completeBtn.innerHTML = '<i class="fas fa-check"></i> Completar Venta';
        return;
    }

    const cashReceived = parseFloat(cashInput.value) || 0;
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const total = POSState.includeIgv ? subtotal * 1.18 : subtotal;
    const change = cashReceived - total;
    
    console.log('💰 Monto recibido:', cashReceived, 'Total:', total, 'Cambio:', change);
    
    if (cashReceived > 0) {
        changeAmountDiv.style.display = 'block';
        if (change >= 0) {
            changeValueSpan.textContent = `S/ ${change.toFixed(2)}`;
            changeAmountDiv.style.color = 'var(--success-700)';
            changeAmountDiv.style.backgroundColor = 'var(--success-50)';
            changeAmountDiv.style.borderColor = 'var(--success-200)';
            completeBtn.disabled = false;
            completeBtn.classList.remove('btn-primary');
            completeBtn.classList.add('btn-success');
            completeBtn.innerHTML = '<i class="fas fa-check-double"></i> Completar Venta';
        } else {
            const amountNeeded = Math.abs(change);
            changeValueSpan.textContent = `Faltan S/ ${amountNeeded.toFixed(2)}`;
            changeAmountDiv.style.color = 'var(--error-700)';
            changeAmountDiv.style.backgroundColor = 'var(--error-50)';
            changeAmountDiv.style.borderColor = 'var(--error-200)';
            completeBtn.disabled = true;
            completeBtn.classList.remove('btn-success');
            completeBtn.classList.add('btn-primary');
            completeBtn.innerHTML = '<i class="fas fa-check"></i> Completar Venta';
        }
    } else {
        changeAmountDiv.style.display = 'none';
        completeBtn.disabled = true;
        completeBtn.classList.remove('btn-success');
        completeBtn.classList.add('btn-primary');
        completeBtn.innerHTML = '<i class="fas fa-check"></i> Completar Venta';
    }
    
    POSState.cashReceived = cashReceived;
    console.log('✅ Cálculo de cambio completado');
}

// ===== MÉTODOS DE PAGO =====
function setupPaymentMethods() {
    selectPaymentMethod('cash');
}

function selectPaymentMethod(method) {
    console.log('💳 Seleccionando método de pago:', method);
    
    POSState.paymentMethod = method;
    
    const paymentMethodButtons = document.querySelectorAll('.payment-section .payment-method-btn');
    paymentMethodButtons.forEach(btn => {
        if (btn.dataset.method === method) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    const cashPaymentSection = document.getElementById('cashPayment');
    if (cashPaymentSection) {
        cashPaymentSection.style.display = method === 'cash' ? 'block' : 'none';
    }
    
    updateTotals();
    calculateChange();
}

// ===== MODAL DE PAGO - FUNCIONES PRINCIPALES =====
function showPaymentConfirmation() {
    console.log('💳 Preparando modal de pago...');
    
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return false;
    }
    
    // Calcular totales
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;
    
    // Crear el modal si no existe
    createPaymentModalIfNeeded();
    
    const paymentModal = document.getElementById('paymentModal');
    const paymentContent = document.getElementById('paymentContent');
    
    if (!paymentModal || !paymentContent) {
        console.error('❌ No se pudo crear el modal de pago');
        showMessage('Error al cargar el formulario de pago', 'error');
        return false;
    }
    
    // Actualizar contenido del modal
    paymentContent.innerHTML = generatePaymentModalContent(subtotal, tax, total);
    
    // Configurar eventos del modal
    setupPaymentModalEvents();
    
    // Abrir el modal usando el sistema unificado
    return ModalSystem.open('paymentModal');
}

function createPaymentModalIfNeeded() {
    if (document.getElementById('paymentModal')) {
        console.log('ℹ️ Modal de pago ya existe');
        return;
    }
    
    console.log('🏗️ Creando modal de pago...');
    
    const modalHTML = `
    <div class="modal-overlay" id="paymentModal">
        <div class="modal modal-payment">
            <div class="modal-header">
                <h3 class="modal-title">💳 Procesar Pago</h3>
                <button type="button" class="modal-close" onclick="closeModal('paymentModal')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        </div>
    </div>`;
    
    const temp = document.createElement('div');
    temp.innerHTML = modalHTML;
    const modalElement = temp.firstElementChild;
    document.body.appendChild(modalElement);
    
    console.log('✅ Modal de pago creado exitosamente');
}

function generatePaymentModalContent(subtotal, tax, total) {
    return `
        <div class="payment-summary">
            <h3>📋 Resumen de la Venta</h3>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span><strong>S/ ${subtotal.toFixed(2)}</strong></span>
            </div>
            ${POSState.includeIgv ? `
            <div class="summary-row">
                <span>IGV (18%):</span>
                <span><strong>S/ ${tax.toFixed(2)}</strong></span>
            </div>` : ''}
            <div class="summary-row total">
                <span>💰 Total a pagar:</span>
                <span><strong>S/ ${total.toFixed(2)}</strong></span>
            </div>
        </div>
        
        <div class="payment-method-selection">
            <h4>💳 Método de pago seleccionado:</h4>
            <div class="payment-methods">
                <button type="button" class="payment-method-btn active" data-method="${POSState.paymentMethod}">
                    <i class="fas ${getPaymentMethodIcon(POSState.paymentMethod)}"></i>
                    <span>${getPaymentMethodName(POSState.paymentMethod)}</span>
                </button>
            </div>
        </div>
        
        ${POSState.paymentMethod === 'cash' ? generateCashPaymentSection(total) : ''}

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" id="confirmPaymentBtn" class="btn btn-primary">
                <i class="fas fa-check"></i> Confirmar Pago
            </button>
        </div>
    `;
}

function generateCashPaymentSection(total) {
    const change = POSState.cashReceived - total;
    
    return `
        <div class="cash-payment-section">
            <div class="form-group">
                <label for="modalCashReceivedInput">💵 Monto recibido:</label>
                <input type="number" id="modalCashReceivedInput" class="form-input" 
                       step="0.01" min="0" value="${POSState.cashReceived.toFixed(2)}" readonly>
            </div>
            <div class="change-amount ${change >= 0 ? 'positive' : 'negative'}" 
                 style="display: ${POSState.cashReceived > 0 ? 'block' : 'none'};">
                ${change >= 0 ? 
                    `✅ Vuelto: <span class="change-value">S/ ${change.toFixed(2)}</span>` :
                    `⚠️ Faltan: <span class="change-value">S/ ${Math.abs(change).toFixed(2)}</span>`
                }
            </div>
        </div>
    `;
}

function setupPaymentModalEvents() {
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    if (confirmBtn) {
        confirmBtn.onclick = processPayment;
    }
}

async function processPayment() {
    console.log('🔄 Iniciando proceso de pago...', POSState);
    
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return false;
    }
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;
    let cashReceived = POSState.cashReceived; 
    
    showMessage('Procesando pago, por favor espere...', 'info');
    
    try {
        closeModal('paymentModal');
        await completeTransaction();
        return true;
    } catch (error) {
        console.error('❌ Error al procesar el pago:', error);
        showMessage('Error al procesar el pago: ' + (error.message || 'Error desconocido'), 'error');
        return false;
    }
}

async function completeTransaction() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;

    const saleData = {
        customer_id: document.getElementById('customerSelect').value || null,
        payment_method: POSState.paymentMethod,
        items: POSState.cart.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.subtotal
        })),
        subtotal: subtotal,
        tax: tax,
        total: total,
        cash_received: POSState.cashReceived,
        change_amount: POSState.paymentMethod === 'cash' ? 
            (POSState.cashReceived - total) : 0,
    };
    
    try {
        const response = await API.post('/sales.php', saleData);
        
        if (response.success) {
            showTransactionComplete(response.data);
            clearCart();
            loadProducts(); 
            if (typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        } else {
            showMessage(response.message || 'Error al procesar la venta', 'error');
        }
    } catch (error) {
        console.error('❌ Error en completeTransaction:', error);
        showMessage('Error de conexión al completar la transacción', 'error');
    }
}

function showTransactionComplete(saleData) {
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    if (!modal || !details) {
        console.error('❌ Modal de transacción no encontrado');
        return;
    }
    
    details.innerHTML = `
        <div class="transaction-summary">
            <h4>🎉 Venta #${saleData.sale_number}</h4>
            <p><strong>📅 Fecha:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>💰 Total:</strong> S/ ${parseFloat(saleData.total).toFixed(2)}</p>
            <p><strong>💳 Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method)}</p>
            ${saleData.change_amount > 0 ? 
                `<p><strong>💵 Vuelto:</strong> S/ ${parseFloat(saleData.change_amount).toFixed(2)}</p>` : ''}
        </div>
    `;
    
    openModal('transactionModal');
}

// ===== FUNCIONES AUXILIARES =====
function getPaymentMethodIcon(method) {
    const icons = {
        'cash': 'fa-money-bill-wave',
        'card': 'fa-credit-card',
        'transfer': 'fa-exchange-alt'
    };
    return icons[method] || 'fa-money-bill-wave';
}

function getPaymentMethodName(method) {
    const names = {
        'cash': 'Efectivo',
        'card': 'Tarjeta',
        'transfer': 'Transferencia'
    };
    return names[method] || method;
}

function closeTransactionModal() {
    closeModal('transactionModal');
}

function newTransaction() {
    closeTransactionModal();
    clearCart();
    selectPaymentMethod('cash'); 
}

// ===== UTILIDADES =====
function clearCart() {
    POSState.cart = [];
    POSState.cashReceived = 0;
    POSState.includeIgv = true;

    const cashReceivedInput = document.getElementById('cashReceivedInput');
    if (cashReceivedInput) {
        cashReceivedInput.value = '0.00';
    }
    const customerSelect = document.getElementById('customerSelect');
    if (customerSelect) {
        customerSelect.value = '';
    }
    
    updateCartDisplay();
    updateTotals();
    updateIgvButtonState();
    calculateChange(); 
}

function updateClock() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        timeElement.textContent = timeString;
    }
}

function showMessage(message, type = 'info') {
    console.log(`📢 ${type.toUpperCase()}: ${message}`);
    
    // Crear elemento de mensaje
    const messageEl = document.createElement('div');
    messageEl.className = `alert alert-${type} alert-toast`;
    
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-triangle',
        'warning': 'fa-exclamation-circle',
        'info': 'fa-info-circle'
    };
    
    messageEl.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Aplicar estilos
    messageEl.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        ${type === 'success' ? 'background: #10b981;' : 
          type === 'error' ? 'background: #ef4444;' :
          type === 'warning' ? 'background: #f59e0b;' :
          'background: #3b82f6;'}
    `;
    
    document.body.appendChild(messageEl);
    
    // Animar entrada
    setTimeout(() => {
        messageEl.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        messageEl.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (messageEl.parentElement) {
                messageEl.parentElement.removeChild(messageEl);
            }
        }, 300);
    }, 5000);
}

// ===== EVENTOS GLOBALES PARA MODALES =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando sistema de modales...');
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ModalSystem.closeAll();
        }
    });
    
    // Cerrar modales clickeando en el overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modalId = e.target.id;
            if (modalId) {
                ModalSystem.close(modalId);
            }
        }
    });
    
    console.log('✅ Sistema de modales inicializado');
});

// ===== FUNCIONES GLOBALES ADICIONALES =====
window.showPaymentConfirmation = showPaymentConfirmation;
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.updateQuantity = updateQuantity;
window.selectCategory = selectCategory;
window.selectPaymentMethod = selectPaymentMethod;
window.toggleIgv = toggleIgv;
window.clearCart = clearCart;
window.closeTransactionModal = closeTransactionModal;
window.newTransaction = newTransaction;