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
}

// ===== MANEJO DE PRODUCTOS =====
function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
    if (!grid) return;
    
    let html = `
        <button class="category-btn ${!POSState.selectedCategory ? 'active' : ''}" onclick="filterByCategory(null)">
            <i class="fas fa-th-large"></i>
            Todos
        </button>
    `;
    
    POSState.categories.forEach(category => {
        html += `
            <button class="category-btn ${POSState.selectedCategory == category.id ? 'active' : ''}" 
                    onclick="filterByCategory(${category.id})">
                <i class="fas fa-tag"></i>
                ${category.name}
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

function loadProducts() {
                refreshProducts(); 
}

function handleProductSearch() {
    const searchValue = document.getElementById('productSearch').value;
    const clearBtn = document.querySelector('.search-clear-btn');
    
    clearBtn.style.display = searchValue ? 'block' : 'none';
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
    
    // Verificar stock
    if (!product.stock_quantity || product.stock_quantity <= 0) {
        showMessage('Producto sin stock disponible', 'warning');
        return;
    }
    
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
            subtotal: parseFloat(product.selling_price)
        });
    }
    
    updateCartDisplay();
    updateTotals();
    checkCanComplete();
}

function removeFromCart(productId) {
    POSState.cart = POSState.cart.filter(item => item.product_id != productId);
    updateCartDisplay();
    updateTotals();
    checkCanComplete();
}

function updateQuantity(productId, newQuantity) {
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
    checkCanComplete();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    if (!cartItems) return;
    
    if (POSState.cart.length === 0) {
        cartItems.innerHTML = '<div class="empty-cart">No hay productos en el carrito</div>';
        return;
    }
    
    let html = '';
    POSState.cart.forEach(item => {
        html += `
            <div class="cart-item">
                <div class="item-info">
                    <h5>${item.name}</h5>
                    <p>S/ ${item.unit_price.toFixed(2)} c/u</p>
                </div>
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})" class="qty-btn">-</button>
                        <input type="number" value="${item.quantity}" 
                               onchange="updateQuantity(${item.product_id}, parseInt(this.value))"
                               class="qty-input">
                        <button onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})" class="qty-btn">+</button>
                    </div>
                    <div class="item-total">S/ ${item.subtotal.toFixed(2)}</div>
                    <button onclick="removeFromCart(${item.product_id})" class="remove-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartItems.innerHTML = html;
}

// ===== CÁLCULOS Y TOTALES =====
function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;

    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }

    // Actualizar display
    document.getElementById('subtotalAmount').textContent = `S/ ${subtotal.toFixed(2)}`;
    document.getElementById('taxAmount').textContent = `S/ ${tax.toFixed(2)}`;
    document.getElementById('totalAmount').textContent = `S/ ${total.toFixed(2)}`;
    
    calculateChange();
}

function toggleIgv() {
    POSState.includeIgv = !POSState.includeIgv;
    updateIgvButtonState();
    updateTotals();
}

function updateIgvButtonState() {
    const igvBtn = document.getElementById('igvToggle');
    if (igvBtn) {
        igvBtn.textContent = POSState.includeIgv ? 'IGV: ON' : 'IGV: OFF';
        igvBtn.className = `btn ${POSState.includeIgv ? 'btn-success' : 'btn-outline'}`;
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
    });
    document.querySelector(`[data-method="${method}"]`).classList.add('active');
    
    // Mostrar/ocultar campos de efectivo
    const cashSection = document.getElementById('cashSection');
    if (cashSection) {
        cashSection.style.display = method === 'cash' ? 'block' : 'none';
    }
    
    checkCanComplete();
}

function calculateChange() {
    if (POSState.paymentMethod !== 'cash') return;
    
    const cashReceived = parseFloat(document.getElementById('cashReceived')?.value || 0);
    POSState.cashReceived = cashReceived;
    
    let total = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    if (POSState.includeIgv) {
        total *= 1.18;
    }
    
    const change = Math.max(0, cashReceived - total);
    document.getElementById('changeAmount').textContent = `S/ ${change.toFixed(2)}`;
    
    checkCanComplete();
}

function checkCanComplete() {
    const completeBtn = document.getElementById('completeBtn');
    if (!completeBtn) return;
    
    let canComplete = POSState.cart.length > 0;
    
    if (POSState.paymentMethod === 'cash') {
        let total = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
        if (POSState.includeIgv) {
            total *= 1.18;
        }
        canComplete = canComplete && POSState.cashReceived >= total;
    }
    
    completeBtn.disabled = !canComplete;
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
        change_amount: POSState.paymentMethod === 'cash' ? (POSState.cashReceived - total) : 0,
        includeIgv: POSState.includeIgv
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
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    details.innerHTML = `
        <div class="transaction-summary">
            <h4>Venta #${saleData.sale_number}</h4>
            <p><strong>Fecha:</strong> ${new Date().toLocaleString()}</p>
            <p><strong>Total:</strong> S/ ${saleData.total}</p>
            <p><strong>Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method)}</p>
            ${saleData.change_amount > 0 ? `<p><strong>Vuelto:</strong> S/ ${saleData.change_amount.toFixed(2)}</p>` : ''}
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
    document.getElementById('transactionModal').style.display = 'none';
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
    POSState.paymentMethod = 'cash';
    
    document.getElementById('cashReceived').value = '';
    updateCartDisplay();
    updateTotals();
    setupPaymentMethods();
    updateIgvButtonState();
    checkCanComplete();
}

function updateClock() {
    const clockElement = document.getElementById('currentTime');
    if (clockElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        clockElement.textContent = timeString;
    }
}

function showMessage(message, type = 'info') {
    // Crear elemento de mensaje
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="alert-close">&times;</button>
    `;
    
    // Agregar al DOM
    const container = document.querySelector('.pos-container') || document.body;
    container.insertBefore(messageDiv, container.firstChild);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 5000);
}

function printReceipt() {
    // Implementar impresión de recibo
    showMessage('Función de impresión no implementada', 'info');
}

// ===== FUNCIONES ADICIONALES DEL POS =====
function renderProducts() {
    const grid = document.getElementById('productsGrid');
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
    
    let html = '';
    filteredProducts.forEach(product => {
        const inStock = (product.stock_quantity || 0) > 0;
        const stockClass = inStock ? '' : 'out-of-stock';
        
        html += `
            <div class="product-card ${stockClass}" onclick="addToCart(${product.id})">
                <div class="product-image">
                    ${product.image_url ? 
                        `<img src="${product.image_url}" alt="${product.name}">` :
                        '<i class="fas fa-box"></i>'
                    }
                </div>
                <div class="product-info">
                    <h4>${product.name}</h4>
                    <p class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</p>
                    <p class="product-stock">Stock: ${product.stock_quantity || 0}</p>
                </div>
                ${!inStock ? '<div class="stock-badge">Sin Stock</div>' : ''}
            </div>
        `;
    });
    
    grid.innerHTML = html || '<p class="no-results">No se encontraron productos</p>';
}

// Función para recargar productos desde el servidor
async function refreshProducts() {
    try {
        const response = await API.getProducts();
        if (response.success) {
            POSState.products = response.products || response.data;
                        loadProducts(); 
            showMessage('Productos actualizados', 'success');
        }
    } catch (error) {
        console.error('Error actualizando productos:', error);
        showMessage('Error al actualizar productos', 'error');
    }
}