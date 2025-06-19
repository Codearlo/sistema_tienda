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
    includeIgv: true, // Nuevo estado para controlar el IGV
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializePOS();
});

function initializePOS() {
    console.log('Inicializando POS...');
    
    // Limpiar cualquier modal que pueda estar abierto
    const modal = document.getElementById('transactionModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Usar datos del PHP
    if (typeof products !== 'undefined') {
        POSState.products = products;
    }
    if (typeof categories !== 'undefined') {
        POSState.categories = categories;
    }
    if (typeof customers !== 'undefined') {
        POSState.customers = customers;
    }
    
    // Inicializar reloj
    updateClock();
    setInterval(updateClock, 1000);
    
    // Configurar eventos
    setupEventListeners();
    
    // Cargar interfaz
    loadCategories();
    loadProducts();
    updateCartDisplay();
    updateTotals();
    setupPaymentMethods();

    // Inicializar estado del botón IGV
    updateIgvButtonState();
    
    console.log('POS inicializado correctamente');
}

// ===== CONFIGURACIÓN DE EVENTOS =====
function setupEventListeners() {
    // Búsqueda de productos
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', handleProductSearch);
    }
    
    // Botón limpiar búsqueda
    const clearBtn = document.querySelector('.search-clear-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearSearch);
    }
    
    // Monto recibido en efectivo
    const cashInput = document.getElementById('cashReceived');
    if (cashInput) {
        cashInput.addEventListener('input', calculateChange);
    }
    
    // Métodos de pago
    const paymentMethods = document.querySelectorAll('.payment-method');
    paymentMethods.forEach(btn => {
        btn.addEventListener('click', () => selectPaymentMethod(btn.dataset.method));
    });
    
    // IGV toggle button
    const igvBtn = document.getElementById('igvToggleBtn');
    if (igvBtn) {
        igvBtn.addEventListener('click', toggleIgv);
    }
}

// ===== MANEJO DE PRODUCTOS =====
function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    if (!grid) return;
    
    let html = `
        <button class="category-btn ${!POSState.selectedCategory ? 'active' : ''}" onclick="filterByCategory(null)">
            Todos
        </button>
    `;
    
    POSState.categories.forEach(category => {
        html += `
            <button class="category-btn ${POSState.selectedCategory == category.id ? 'active' : ''}" 
                    onclick="filterByCategory(${category.id})">
                ${htmlspecialchars(category.name)}
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

function loadProducts() {
    const grid = document.getElementById('productsGrid');
    const empty = document.getElementById('emptyProducts');
    
    if (!grid) return;
    
    let filteredProducts = POSState.products;
    
    // Filtrar por categoría
    if (POSState.selectedCategory) {
        filteredProducts = filteredProducts.filter(p => p.category_id == POSState.selectedCategory);
    }
    
    // Filtrar por búsqueda
    const searchTerm = document.getElementById('productSearch')?.value?.toLowerCase();
    if (searchTerm) {
        filteredProducts = filteredProducts.filter(p => 
            p.name.toLowerCase().includes(searchTerm) || 
            (p.barcode && p.barcode.toLowerCase().includes(searchTerm))
        );
    }
    
    if (filteredProducts.length === 0) {
        grid.style.display = 'none';
        empty.style.display = 'flex';
        return;
    }
    
    empty.style.display = 'none';
    grid.style.display = 'flex';
    
    let html = '';
    filteredProducts.forEach(product => {
        const isOutOfStock = product.stock_quantity <= 0;
        const isLowStock = product.stock_quantity <= (product.min_stock || 5);
        
        html += `
            <div class="product-card ${isOutOfStock ? 'out-of-stock' : ''}" 
                 onclick="${isOutOfStock ? '' : `addToCart(${product.id})`}">
                <div class="product-image">
                    ${product.image_url ? 
                        `<img src="${product.image_url}" alt="${htmlspecialchars(product.name)}">` : 
                        '<div class="product-placeholder"><i class="fas fa-box"></i></div>'
                    }
                </div>
                
                <div class="product-info">
                    <h4 class="product-name">${htmlspecialchars(product.name)}</h4>
                    <p class="product-category">${htmlspecialchars(product.category_name || 'Sin categoría')}</p>
                    
                    <div class="product-details">
                        <div class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</div>
                        <div class="product-stock ${isLowStock ? 'low-stock' : ''}">
                            Stock: ${product.stock_quantity}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

function handleProductSearch(event) {
    const searchTerm = event.target.value;
    const clearBtn = document.querySelector('.search-clear-btn');
    
    if (searchTerm.length > 0) {
        clearBtn.style.display = 'block';
    } else {
        clearBtn.style.display = 'none';
    }
    
    loadProducts();
}

function clearSearch() {
    document.getElementById('productSearch').value = '';
    document.querySelector('.search-clear-btn').style.display = 'none';
    loadProducts();
}

function filterByCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    loadCategories();
    loadProducts();
}

function clearFilters() {
    POSState.selectedCategory = null;
    document.getElementById('productSearch').value = '';
    document.querySelector('.search-clear-btn').style.display = 'none';
    loadCategories();
    loadProducts();
}

// ===== CARRITO DE COMPRAS =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) return;
    
    const existingItem = POSState.cart.find(item => item.product_id == productId);
    
    if (existingItem) {
        if (existingItem.quantity >= product.stock_quantity) {
            showMessage('No hay suficiente stock disponible', 'warning');
            return;
        }
        existingItem.quantity += 1;
        existingItem.subtotal = existingItem.quantity * existingItem.unit_price;
    } else {
        POSState.cart.push({
            product_id: productId,
            name: product.name,
            unit_price: parseFloat(product.selling_price),
            quantity: 1,
            subtotal: parseFloat(product.selling_price),
            image_url: product.image_url
        });
    }
    
    updateCartDisplay();
    updateTotals();
}

function updateCartQuantity(productId, newQuantity) {
    const item = POSState.cart.find(item => item.product_id == productId);
    const product = POSState.products.find(p => p.id == productId);
    
    if (!item || !product) return;
    
    if (newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    if (newQuantity > product.stock_quantity) {
        showMessage('No hay suficiente stock disponible', 'warning');
        return;
    }
    
    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.unit_price;
    
    updateCartDisplay();
    updateTotals();
}

function removeFromCart(productId) {
    POSState.cart = POSState.cart.filter(item => item.product_id != productId);
    updateCartDisplay();
    updateTotals();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.querySelector('.cart-count');
    
    if (!cartItems) return;
    
    const totalItems = POSState.cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = `${totalItems} productos`;
    
    if (POSState.cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-3x"></i>
                <h3>El carrito está vacío</h3>
                <p>Agregue productos para comenzar</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    POSState.cart.forEach(item => {
        html += `
            <div class="cart-item">
                <div class="cart-item-image">
                    ${item.image_url ? 
                        `<img src="${item.image_url}" alt="${htmlspecialchars(item.name)}">` : 
                        '<i class="fas fa-box"></i>'
                    }
                </div>
                
                <div class="cart-item-info">
                    <h4 class="cart-item-name">${htmlspecialchars(item.name)}</h4>
                    <p class="cart-item-price">S/ ${item.unit_price.toFixed(2)} c/u</p>
                </div>
                
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateCartQuantity(${item.product_id}, ${item.quantity - 1})">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" value="${item.quantity}" 
                               onchange="updateCartQuantity(${item.product_id}, parseInt(this.value))">
                        <button class="quantity-btn" onclick="updateCartQuantity(${item.product_id}, ${item.quantity + 1})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <button class="remove-item-btn" onclick="removeFromCart(${item.product_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;
}

function updateTotals() {
    const subtotalEl = document.getElementById('subtotalAmount');
    const taxEl = document.getElementById('taxAmount');
    const totalEl = document.getElementById('totalAmount');
    const completeBtn = document.getElementById('completeBtn');
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;
    
    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }
    
    if (subtotalEl) subtotalEl.textContent = `S/ ${subtotal.toFixed(2)}`;
    if (taxEl) taxEl.textContent = `S/ ${tax.toFixed(2)}`;
    if (totalEl) totalEl.textContent = `S/ ${total.toFixed(2)}`;
    
    if (completeBtn) {
        completeBtn.disabled = POSState.cart.length === 0;
    }
    
    // Actualizar cambio si es pago en efectivo
    if (POSState.paymentMethod === 'cash') {
        calculateChange();
    }
}

// ===== IGV MANAGEMENT =====
function toggleIgv() {
    POSState.includeIgv = !POSState.includeIgv;
    updateIgvButtonState();
    updateTotals();
}

function updateIgvButtonState() {
    const btn = document.getElementById('igvToggleBtn');
    if (!btn) return;
    
    if (POSState.includeIgv) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-check"></i> IGV (18%) Incluido';
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-times"></i> Sin IGV';
    }
}

// ===== MÉTODOS DE PAGO =====
function setupPaymentMethods() {
    selectPaymentMethod('cash');
}

function selectPaymentMethod(method) {
    POSState.paymentMethod = method;
    
    // Actualizar botones
    document.querySelectorAll('.payment-method').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.method === method) {
            btn.classList.add('active');
        }
    });
    
    // Mostrar/ocultar sección de efectivo
    const cashSection = document.getElementById('cashSection');
    if (cashSection) {
        cashSection.style.display = method === 'cash' ? 'block' : 'none';
    }
    
    updateTotals();
}

function calculateChange() {
    const cashInput = document.getElementById('cashReceived');
    const changeDisplay = document.getElementById('changeAmount');
    
    if (!cashInput || !changeDisplay) return;
    
    const cashReceived = parseFloat(cashInput.value) || 0;
    POSState.cashReceived = cashReceived;
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let total = subtotal;
    
    if (POSState.includeIgv) {
        total = subtotal * 1.18;
    }
    
    const change = cashReceived - total;
    
    changeDisplay.textContent = `S/ ${change.toFixed(2)}`;
    changeDisplay.className = `change-display ${change >= 0 ? 'positive' : 'negative'}`;
}

// ===== TRANSACCIONES =====
async function completeTransaction() {
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return;
    }
    
    if (POSState.paymentMethod === 'cash') {
        let total = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
        if (POSState.includeIgv) {
            total *= 1.18;
        }

        if (POSState.cashReceived < total) {
            showMessage('El monto recibido es insuficiente', 'warning');
            return;
        }
    }
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;

    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }

    const saleData = {
        customer_id: document.getElementById('customerSelect').value || null,
        payment_method: POSState.paymentMethod,
        items: POSState.cart,
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
            showMessage('Venta completada exitosamente', 'success');
            loadProducts(); 
        } else {
            showMessage(response.message || 'Error al procesar la venta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

function showTransactionComplete(saleData) {
    if (!saleData || !saleData.sale_number) {
        console.error('Datos de venta inválidos:', saleData);
        return;
    }
    
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
            <p><strong>Total:</strong> S/ ${parseFloat(saleData.total || 0).toFixed(2)}</p>
            <p><strong>Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method)}</p>
            ${saleData.change_amount && saleData.change_amount > 0 ?
                `<p><strong>Vuelto:</strong> S/ ${parseFloat(saleData.change_amount).toFixed(2)}</p>` : ''}
        </div>
    `;
    
    modal.style.display = 'flex';
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
    const modal = document.getElementById('transactionModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function newTransaction() {
    closeTransactionModal();
    clearCart();
}

// ===== UTILIDADES =====
function clearCart() {
    POSState.cart = [];
    POSState.cashReceived = 0;
    POSState.includeIgv = true;

    const cashInput = document.getElementById('cashReceived');
    const customerSelect = document.getElementById('customerSelect');
    
    if (cashInput) cashInput.value = '';
    if (customerSelect) customerSelect.value = '';
    
    updateCartDisplay();
    updateTotals();
    updateIgvButtonState();
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
    if (type === 'error') {
        alert('❌ ' + message);
    } else if (type === 'warning') {
        alert('⚠️ ' + message);
    } else if (type === 'success') {
        alert('✅ ' + message);
    } else {
        alert('ℹ️ ' + message);
    }
}

function htmlspecialchars(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// ===== MOBILE MENU =====
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    }
}