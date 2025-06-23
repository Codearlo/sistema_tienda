/**
 * POINT OF SALE (POS) - JavaScript Completo
 * Sistema funcional de punto de venta
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
    console.log('Inicializando POS...');
    
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

    console.log('POSState.products después de inicializar:', POSState.products);
    console.log('POSState.categories después de inicializar:', POSState.categories);
    console.log('POSState.customers después de inicializar:', POSState.customers);
    
    updateClock();
    setInterval(updateClock, 1000);
    
    setupEventListeners();
    
    loadCategories();
    loadProducts();
    updateCartDisplay();
    updateTotals();
    setupPaymentMethods(); 
    updateIgvButtonState();
    
    console.log('POS inicializado correctamente');
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

// ===== MANEJO DE PRODUCTOS =====
function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    if (!grid) {
        console.error('categoriesGrid no encontrado.');
        return;
    }
    
    let html = `
        <button class="category-btn ${!POSState.selectedCategory ? 'active' : ''}" onclick="filterByCategory(null)" data-category-id="null">
            <i class="fas fa-th-large"></i>
            Todos
        </button>
    `;
    
    POSState.categories.forEach(category => {
        const isActive = POSState.selectedCategory == category.id;
        html += `
            <button class="category-btn ${isActive ? 'active' : ''}" onclick="filterByCategory(${category.id})" data-category-id="${category.id}">
                <i class="${category.icon || 'fas fa-folder'}"></i>
                ${htmlspecialchars(category.name)}
            </button>
        `;
    });
    
    grid.innerHTML = html;
    console.log('Categorías cargadas:', POSState.categories.length);
}

function loadProducts() {
    const grid = document.getElementById('productsGrid');
    const emptyProductsState = document.getElementById('emptyProducts');
    if (!grid) {
        console.error('productsGrid no encontrado.');
        return;
    }
    
    const searchTerm = document.getElementById('productSearch')?.value.toLowerCase() || '';
    
    let filteredProducts = POSState.products.filter(product => {
        const matchesSearch = !searchTerm || 
            product.name.toLowerCase().includes(searchTerm) ||
            (product.sku && product.sku.toLowerCase().includes(searchTerm)) ||
            (product.barcode && product.barcode.toLowerCase().includes(searchTerm));
        
        const matchesCategory = POSState.selectedCategory === null || 
            product.category_id == POSState.selectedCategory;
        
        return matchesSearch && matchesCategory;
    });
    
    if (filteredProducts.length === 0) {
        grid.style.display = 'none';
        if (emptyProductsState) {
            emptyProductsState.style.display = 'flex';
        }
        console.log('No se encontraron productos filtrados.');
        return;
    }
    
    grid.style.display = 'grid';
    if (emptyProductsState) {
        emptyProductsState.style.display = 'none';
    }
    
    let html = '';
    filteredProducts.forEach(product => {
        const stock = product.stock_quantity || 0;
        const isLowStock = stock <= (product.min_stock || 5);
        
        html += `
            <div class="product-card ${stock === 0 ? 'out-of-stock' : ''}" onclick="addToCart(${product.id})">
                <div class="product-image">
                    <img src="${product.image_url || 'assets/images/product-placeholder.png'}" 
                         alt="${htmlspecialchars(product.name)}" 
                         onerror="this.src='assets/images/product-placeholder.png'">
                    ${stock === 0 ? '<div class="out-of-stock-badge">Agotado</div>' : ''}
                    ${isLowStock && stock > 0 ? '<div class="low-stock-badge">Poco stock</div>' : ''}
                </div>
                <div class="product-info">
                    <h3>${htmlspecialchars(product.name)}</h3>
                    <p class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</p>
                    <p class="product-stock">Stock: ${stock}</p>
                    ${product.sku ? `<p class="product-sku">SKU: ${htmlspecialchars(product.sku)}</p>` : ''}
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
    console.log('Productos cargados:', filteredProducts.length);
}

// ===== MANEJO DEL CARRITO =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    const stock = product.stock_quantity || 0;
    if (stock === 0) {
        showMessage('Producto sin stock', 'warning');
        return;
    }
    
    const existingItem = POSState.cart.find(item => item.product_id == productId);
    
    if (existingItem) {
        if (existingItem.quantity >= stock) {
            showMessage('No hay suficiente stock', 'warning');
            return;
        }
        existingItem.quantity += 1;
        existingItem.subtotal = existingItem.quantity * existingItem.price;
    } else {
        POSState.cart.push({
            product_id: productId,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            subtotal: parseFloat(product.selling_price),
            stock: stock 
        });
    }
    
    updateCartDisplay();
    updateTotals();
}

function removeFromCart(productId) {
    const index = POSState.cart.findIndex(item => item.product_id == productId);
    if (index !== -1) {
        POSState.cart.splice(index, 1);
        updateCartDisplay();
        updateTotals();
    }
}

function updateQuantity(productId, newQuantity) {
    const item = POSState.cart.find(item => item.product_id == productId);
    if (!item) return;
    
    newQuantity = parseInt(newQuantity);
    
    if (isNaN(newQuantity) || newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    if (newQuantity > item.stock) {
        showMessage(`Cantidad excede el stock disponible (${item.stock})`, 'warning');
        item.quantity = item.stock;
    } else {
        item.quantity = newQuantity;
    }
    
    item.subtotal = item.quantity * item.price;
    
    updateCartDisplay();
    updateTotals();
}

function updateCartDisplay() {
    const cartItemsContainer = document.getElementById('cartItems');
    const emptyState = cartItemsContainer ? cartItemsContainer.querySelector('.empty-state') : null;
    const completeBtn = document.getElementById('completeBtn');
    const cartCountSpan = document.getElementById('cartCount');
    const cartSummaryDiv = document.getElementById('cartSummary');
    
    if (!cartItemsContainer) return;
    
    if (POSState.cart.length === 0) {
        if (cartItemsContainer) cartItemsContainer.innerHTML = `<div class="empty-state"><i class="fas fa-shopping-cart fa-2x"></i><h3>El carrito está vacío</h3><p>Agregue productos para comenzar</p></div>`;
        if (emptyState) emptyState.style.display = 'flex';
        if (completeBtn) completeBtn.disabled = true;
        if (cartSummaryDiv) cartSummaryDiv.style.display = 'none';
    } else {
        if (emptyState) emptyState.style.display = 'none';
        if (completeBtn) completeBtn.disabled = false;
        if (cartSummaryDiv) cartSummaryDiv.style.display = 'block';
        
        let html = '';
        POSState.cart.forEach(item => {
            html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <h4>${htmlspecialchars(item.name)}</h4>
                        <p class="item-price">S/ ${item.price.toFixed(2)} c/u</p>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-controls">
                            <button onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})" 
                                    class="quantity-btn">-</button>
                            <input type="number" 
                                   value="${item.quantity}" 
                                   min="1" 
                                   max="${item.stock}"
                                   onchange="updateQuantity(${item.product_id}, parseInt(this.value))"
                                   class="quantity-input">
                            <button onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})" 
                                    class="quantity-btn">+</button>
                        </div>
                        <div class="item-total">S/ ${item.subtotal.toFixed(2)}</div>
                        <button onclick="removeFromCart(${item.product_id})" 
                                class="remove-btn" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        cartItemsContainer.innerHTML = html;
    }
    
    if (cartCountSpan) {
        cartCountSpan.textContent = `${POSState.cart.length} productos`;
    }
}

function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;
    
    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }
    
    const elements = {
        'subtotal': `S/ ${subtotal.toFixed(2)}`, 
        'tax': `S/ ${tax.toFixed(2)}`,         
        'total': `S/ ${total.toFixed(2)}`       
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = elements[id];
        }
    });

    const igvRow = document.getElementById('igvRow');
    if (igvRow) {
        igvRow.style.display = POSState.includeIgv ? 'flex' : 'none';
    }
    
    calculateChange();
}

function toggleIgv() {
    POSState.includeIgv = !POSState.includeIgv;
    updateTotals();
    updateIgvButtonState();
}

function updateIgvButtonState() {
    const igvBtn = document.getElementById('toggleIgvBtn');
    if (igvBtn) {
        if (POSState.includeIgv) {
            igvBtn.textContent = 'IGV Incluido ✓';
            igvBtn.classList.remove('btn-outline');
            igvBtn.classList.add('btn-success');
        } else {
            igvBtn.textContent = 'Sin IGV';
            igvBtn.classList.remove('btn-success');
            igvBtn.classList.add('btn-outline');
        }
    }
}

// ===== MÉTODOS DE PAGO =====
function setupPaymentMethods() {
    const initialMethodBtn = document.querySelector('.payment-section .payment-method-btn.active');
    if (initialMethodBtn) {
        selectPaymentMethod(initialMethodBtn.dataset.method);
    } else {
        selectPaymentMethod('cash');
    }
}

// ===== CÁLCULO DE CAMBIO =====
function calculateChange() {
    console.log('Calculando cambio...');
    
    const cashInput = document.getElementById('cashReceivedInput');
    const changeAmountDiv = document.getElementById('changeAmount');
    const changeValueSpan = document.getElementById('changeValue');
    const completeBtn = document.getElementById('completeBtn');

    if (!cashInput || !changeAmountDiv || !changeValueSpan || !completeBtn) {
        console.warn('Algunos elementos del formulario de pago principal no se encontraron. Posiblemente se está inicializando el POS.');
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
    
    console.log('Monto recibido:', cashReceived, 'Total:', total, 'Cambio:', change);
    
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
    console.log('Cálculo de cambio completado');
}

function selectPaymentMethod(method) {
    console.log('Seleccionando método de pago:', method);
    
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
    
    if (method !== 'cash') {
        const cashInput = document.getElementById('cashReceivedInput');
        if (cashInput) {
            cashInput.value = '0.00';
        }
        const changeAmountDiv = document.getElementById('changeAmount');
        if (changeAmountDiv) {
            changeAmountDiv.style.display = 'none';
        }
    } else {
        setTimeout(() => {
            const cashInput = document.getElementById('cashReceivedInput');
            if (cashInput) {
                cashInput.focus();
                cashInput.select();
            }
        }, 100);
    }
    
    calculateChange();
}

// ===== BÚSQUEDA Y FILTROS =====
function handleProductSearch() {
    const searchInput = document.getElementById('productSearch');
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    
    loadProducts(); 
    
    const clearBtn = document.querySelector('.search-clear-btn');
    if (clearBtn) {
        clearBtn.style.display = searchTerm ? 'flex' : 'none';
    }
}

function clearSearch() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.value = '';
    }
    
    const clearBtn = document.querySelector('.search-clear-btn');
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    
    loadProducts();
}

function filterByCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    loadProducts();
    
    const categoryButtons = document.querySelectorAll('.category-btn');
    if (categoryButtons.length > 0) {
        categoryButtons.forEach(btn => {
            const btnCategoryId = btn.getAttribute('data-category-id');
            if ( (categoryId === null && btnCategoryId === "null") || (btnCategoryId == categoryId) ) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
}

function clearFilters() {
    POSState.selectedCategory = null;
    document.getElementById('productSearch').value = '';
    document.querySelector('.search-clear-btn').style.display = 'none';
    loadCategories();
    loadProducts();
}

// ===== TRANSACCIONES =====
function showPaymentModal() {
    console.log('Mostrando modal de pago...');
    
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return false;
    }
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;
    
    // Crear el modal si no existe
    createPaymentModal(); 
    
    const paymentModal = document.getElementById('paymentModal');
    const paymentContent = document.getElementById('paymentContent');
    
    if (!paymentModal || !paymentContent) {
        console.error('No se pudo crear el modal de pago');
        showMessage('Error al cargar el formulario de pago', 'error');
        return false;
    }
    
    // Actualizar el contenido del modal de pago - ESTO ES PARA EL MODAL DE CONFIRMACIÓN
    paymentContent.innerHTML = `
        <div class="payment-summary">
            <h3>Resumen de la Venta</h3>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>S/ ${subtotal.toFixed(2)}</span>
            </div>
            ${POSState.includeIgv ? `
            <div class="summary-row">
                <span>IGV (18%):</span>
                <span>S/ ${tax.toFixed(2)}</span>
            </div>` : ''}
            <div class="summary-row total">
                <span>Total a pagar:</span>
                <span>S/ ${total.toFixed(2)}</span>
            </div>
        </div>
        
        <div class="payment-method-selection">
            <h4>Método de pago seleccionado:</h4>
            <div class="payment-methods">
                <button type="button" class="payment-method-btn active" 
                        data-method="${POSState.paymentMethod}">
                    <i class="fas ${POSState.paymentMethod === 'cash' ? 'fa-money-bill' : (POSState.paymentMethod === 'card' ? 'fa-credit-card' : 'fa-exchange-alt')}"></i>
                    <span>${getPaymentMethodName(POSState.paymentMethod)}</span>
                </button>
            </div>
        </div>
        
        ${POSState.paymentMethod === 'cash' ? `
        <div id="modalCashPaymentSection" class="cash-payment-section">
            <div class="form-group">
                <label for="modalCashReceivedInput">Monto recibido:</label>
                <input type="number" id="modalCashReceivedInput" class="form-input" 
                       step="0.01" min="0" value="${POSState.cashReceived.toFixed(2)}" 
                       readonly>
            </div>
            <div id="modalChangeAmount" class="change-amount" style="display: block;">
                Vuelto: <span id="modalChangeValue" class="change-value">S/ ${(POSState.cashReceived - total).toFixed(2)}</span>
            </div>
        </div>
        ` : ''}

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" id="confirmPaymentBtn" class="btn btn-primary">
                <i class="fas fa-check"></i> Confirmar Pago
            </button>
        </div>
    `;
    
    const confirmBtnInModal = document.getElementById('confirmPaymentBtn');
    if (confirmBtnInModal) {
        confirmBtnInModal.onclick = processPayment; 
    }
    
    openModal('paymentModal');
    
    return false;
}

// Función para crear el modal de pago dinámicamente si no existe
function createPaymentModal() {
    console.log('Creando modal de pago...');
    
    if (document.getElementById('paymentModal')) {
        console.log('El modal de pago ya existe');
        return;
    }
    
    const modalHTML = `
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Procesar Pago</h3>
                <button type="button" class="modal-close" onclick="closeModal('paymentModal')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    </div>
            </div>
        </div>
    </div>`;
    
    const temp = document.createElement('div');
    temp.innerHTML = modalHTML;
    
    const modalElement = temp.firstElementChild;
    document.body.appendChild(modalElement);
    
    console.log('Modal de pago creado exitosamente');
}

async function processPayment() {
    console.log('Iniciando proceso de pago...', POSState);
    
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return false;
    }
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const total = POSState.includeIgv ? subtotal * 1.18 : subtotal;
    let cashReceived = POSState.cashReceived; 
    
    showMessage('Procesando pago, por favor espere...', 'info');
    
    try {
        closeModal('paymentModal');
        await completeTransaction();
        return true;
    } catch (error) {
        console.error('Error al procesar el pago:', error);
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
        console.error('Error en completeTransaction:', error);
        showMessage('Error de conexión al completar la transacción', 'error');
    }
}

function showTransactionComplete(saleData) {
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    if (!modal || !details) {
        console.error('Modal de transacción no encontrado');
        return;
    }
    
    details.innerHTML = `
        <div class="transaction-summary">
            <h4>Venta #${saleData.sale_number}</h4>
            <p><strong>Fecha:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total:</strong> S/ ${parseFloat(saleData.total).toFixed(2)}</p>
            <p><strong>Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method)}</p>
            ${saleData.change_amount > 0 ? 
                `<p><strong>Vuelto:</strong> S/ ${parseFloat(saleData.change_amount).toFixed(2)}</p>` : ''}
        </div>
    `;
    
    openModal('transactionModal');
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

// ===== FUNCIONES DE MODAL =====
function openModal(modalId) {
    console.log('Abriendo modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex'; 
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            const firstInput = modal.querySelector('input, select, textarea, button');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    } else {
        console.error('No se pudo encontrar el modal con ID:', modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = ''; 
        }, 300);
    }
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
    if (!timeElement) return;
    
    const now = new Date();
    const options = {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    };
    const dateTimeString = now.toLocaleDateString('es-PE', options);
    timeElement.innerHTML = dateTimeString;
}

function printReceipt() {
    showMessage('Funcionalidad de impresión en desarrollo', 'info');
}

function showMessage(message, type = 'info') {
    const notificationContainer = document.querySelector('.pos-container');
    if (!notificationContainer) return;

    const existingAlert = notificationContainer.querySelector('.pos-alert');
    if (existingAlert) {
        existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `pos-alert pos-alert-${type}`;
    alertDiv.innerHTML = `
        <span class="pos-alert-icon">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
        </span>
        <span class="pos-alert-message">${message}</span>
        <button class="pos-alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    notificationContainer.insertBefore(alertDiv, notificationContainer.firstChild);

    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 10);

    setTimeout(() => {
        alertDiv.classList.remove('show');
        alertDiv.addEventListener('transitionend', () => alertDiv.remove(), { once: true });
    }, 3000);
}

function htmlspecialchars(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
        if (sidebar.classList.contains('mobile-open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}