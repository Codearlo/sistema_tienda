/**
 * PUNTO DE VENTA JAVASCRIPT - CORREGIDO
 * Archivo: assets/js/pos.js
 */

let POSState = {
    products: [],
    categories: [],
    customers: [],
    cart: [],
    selectedCategory: null,
    currentTransaction: null
};

// ===== INICIALIZACIÓN =====
function initializePOS() {
    // Inicializar datos desde variables globales
    if (typeof products !== 'undefined') POSState.products = products;
    if (typeof categories !== 'undefined') POSState.categories = categories;
    if (typeof customers !== 'undefined') POSState.customers = customers;
    
    // Cargar interfaz
    loadCategories();
    loadProducts();
    updateCartDisplay();
    
    // Event listeners
    setupEventListeners();
    
    // Actualizar reloj
    updateClock();
    setInterval(updateClock, 1000);
    
    console.log('POS initialized with:', {
        products: POSState.products.length,
        categories: POSState.categories.length,
        customers: POSState.customers.length
    });
}

function setupEventListeners() {
    // Búsqueda de productos
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterProducts, 300));
    }
    
    // Calculadora de cambio
    const amountReceived = document.getElementById('amountReceived');
    if (amountReceived) {
        amountReceived.addEventListener('input', calculateChange);
    }
    
    // Método de pago
    const paymentMethod = document.getElementById('paymentMethod');
    if (paymentMethod) {
        paymentMethod.addEventListener('change', handlePaymentMethodChange);
    }
}

function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    
    const clockElement = document.getElementById('currentTime');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// ===== GESTIÓN DE CATEGORÍAS =====
function loadCategories() {
    const grid = document.getElementById('categoriesTabs');
    if (!grid) return;
    
    let html = `
        <button class="category-btn ${!POSState.selectedCategory ? 'active' : ''}" 
                onclick="filterByCategory(null)">
            <i class="fas fa-th"></i>
            Todos
        </button>
    `;
    
    POSState.categories.forEach(category => {
        html += `
            <button class="category-btn ${POSState.selectedCategory === category.id ? 'active' : ''}" 
                    onclick="filterByCategory(${category.id})">
                <i class="fas fa-tag"></i>
                ${escapeHtml(category.name)}
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

function filterByCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    loadCategories();
    loadProducts();
}

// ===== GESTIÓN DE PRODUCTOS =====
function loadProducts() {
    const grid = document.getElementById('productsGrid');
    const emptyState = document.getElementById('emptyProducts');
    
    if (!grid) return;
    
    let filteredProducts = POSState.products;
    
    // Filtrar por categoría
    if (POSState.selectedCategory) {
        filteredProducts = filteredProducts.filter(p => p.category_id == POSState.selectedCategory);
    }
    
    // Filtrar por búsqueda
    const searchTerm = document.getElementById('productSearch')?.value.toLowerCase() || '';
    if (searchTerm) {
        filteredProducts = filteredProducts.filter(p => 
            p.name.toLowerCase().includes(searchTerm) ||
            (p.sku && p.sku.toLowerCase().includes(searchTerm)) ||
            (p.barcode && p.barcode.toLowerCase().includes(searchTerm))
        );
    }
    
    if (filteredProducts.length === 0) {
        grid.style.display = 'none';
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }
    
    grid.style.display = 'grid';
    if (emptyState) emptyState.style.display = 'none';
    
    const html = filteredProducts.map(product => {
        const isOutOfStock = product.track_stock && product.stock_quantity <= 0;
        const isLowStock = product.track_stock && product.stock_quantity <= product.min_stock && product.stock_quantity > 0;
        
        return `
            <div class="product-card ${isOutOfStock ? 'out-of-stock' : ''}" 
                 onclick="addToCart(${product.id})" 
                 ${isOutOfStock ? 'style="cursor: not-allowed;"' : ''}>
                <div class="product-image">
                    ${product.image ? 
                        `<img src="${product.image}" alt="${escapeHtml(product.name)}">` :
                        '<div class="product-placeholder"><i class="fas fa-box"></i></div>'
                    }
                </div>
                <div class="product-info">
                    <h4 class="product-name">${escapeHtml(product.name)}</h4>
                    <p class="product-category">${escapeHtml(product.category_name || 'Sin categoría')}</p>
                    <div class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</div>
                    <div class="product-stock ${isLowStock ? 'low-stock' : ''}">
                        Stock: ${product.stock_quantity || 0} ${product.unit || 'unidades'}
                    </div>
                    ${isOutOfStock ? '<div class="out-of-stock-label">Sin Stock</div>' : ''}
                </div>
            </div>
        `;
    }).join('');
    
    grid.innerHTML = html;
}

function filterProducts() {
    loadProducts();
}

function clearSearch() {
    document.getElementById('productSearch').value = '';
    loadProducts();
}

// ===== MANEJO DEL CARRITO =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) {
        showNotification('Producto no encontrado', 'error');
        return;
    }
    
    // Verificar stock disponible
    if (product.track_stock && product.stock_quantity <= 0) {
        showNotification('Producto sin stock disponible', 'error');
        return;
    }
    
    // Verificar si ya está en el carrito
    const existingItem = POSState.cart.find(item => item.product_id === productId);
    
    if (existingItem) {
        // Verificar stock antes de incrementar
        if (product.track_stock && existingItem.quantity >= product.stock_quantity) {
            showNotification(`Stock máximo disponible: ${product.stock_quantity}`, 'warning');
            return;
        }
        existingItem.quantity += 1;
    } else {
        // Agregar nuevo item
        POSState.cart.push({
            product_id: productId,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            track_stock: product.track_stock,
            available_stock: product.stock_quantity
        });
    }
    
    updateCartDisplay();
    showNotification(`${product.name} agregado al carrito`, 'success');
}

function removeFromCart(productId) {
    POSState.cart = POSState.cart.filter(item => item.product_id !== productId);
    updateCartDisplay();
}

function updateCartQuantity(productId, newQuantity) {
    const item = POSState.cart.find(item => item.product_id === productId);
    if (!item) return;
    
    if (newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    // Verificar stock disponible
    if (item.track_stock && newQuantity > item.available_stock) {
        showNotification(`Stock máximo disponible: ${item.available_stock}`, 'warning');
        return;
    }
    
    item.quantity = newQuantity;
    updateCartDisplay();
}

function clearCart() {
    if (POSState.cart.length === 0) return;
    
    if (confirm('¿Estás seguro de que quieres limpiar el carrito?')) {
        POSState.cart = [];
        updateCartDisplay();
        showNotification('Carrito limpiado', 'info');
    }
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartSummary = document.getElementById('cartSummary');
    const paymentSection = document.getElementById('paymentSection');
    const clearCartBtn = document.getElementById('clearCartBtn');
    
    if (!cartItems) return;
    
    if (POSState.cart.length === 0) {
        // Carrito vacío
        cartItems.innerHTML = `
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <p>El carrito está vacío</p>
                <small>Selecciona productos para comenzar una venta</small>
            </div>
        `;
        
        if (cartSummary) cartSummary.style.display = 'none';
        if (paymentSection) paymentSection.style.display = 'none';
        if (clearCartBtn) clearCartBtn.disabled = true;
        
        return;
    }
    
    // Mostrar items del carrito
    const itemsHtml = POSState.cart.map(item => `
        <div class="cart-item">
            <div class="item-info">
                <div class="item-name">${escapeHtml(item.name)}</div>
                <div class="item-price">S/ ${item.price.toFixed(2)} c/u</div>
            </div>
            <div class="item-controls">
                <div class="quantity-controls">
                    <button class="qty-btn" onclick="updateCartQuantity(${item.product_id}, ${item.quantity - 1})">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="quantity">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateCartQuantity(${item.product_id}, ${item.quantity + 1})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="item-total">S/ ${(item.price * item.quantity).toFixed(2)}</div>
                <button class="remove-btn" onclick="removeFromCart(${item.product_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    cartItems.innerHTML = itemsHtml;
    
    // Calcular totales
    const subtotal = POSState.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.18; // 18% IGV
    const total = subtotal + tax;
    
    // Actualizar resumen
    if (cartSummary) {
        document.getElementById('subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
        document.getElementById('tax').textContent = `S/ ${tax.toFixed(2)}`;
        document.getElementById('total').textContent = `S/ ${total.toFixed(2)}`;
        cartSummary.style.display = 'block';
    }
    
    // Mostrar sección de pago
    if (paymentSection) {
        paymentSection.style.display = 'block';
    }
    
    // Habilitar botón de limpiar
    if (clearCartBtn) {
        clearCartBtn.disabled = false;
    }
    
    // Limpiar campo de monto recibido
    const amountReceived = document.getElementById('amountReceived');
    if (amountReceived) {
        amountReceived.value = '';
        calculateChange();
    }
}

// ===== GESTIÓN DE PAGOS =====
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const cashPayment = document.getElementById('cashPayment');
    
    if (paymentMethod === 'cash') {
        cashPayment.style.display = 'block';
    } else {
        cashPayment.style.display = 'none';
        document.getElementById('amountReceived').value = '';
        calculateChange();
    }
}

function calculateChange() {
    const total = POSState.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18;
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    const change = amountReceived - total;
    
    const changeAmount = document.getElementById('changeAmount');
    const changeSpan = document.getElementById('change');
    
    if (amountReceived > 0 && change >= 0) {
        changeSpan.textContent = `S/ ${change.toFixed(2)}`;
        changeAmount.style.display = 'block';
        changeAmount.className = 'change-amount positive';
    } else if (amountReceived > 0 && change < 0) {
        changeSpan.textContent = `Falta: S/ ${Math.abs(change).toFixed(2)}`;
        changeAmount.style.display = 'block';
        changeAmount.className = 'change-amount negative';
    } else {
        changeAmount.style.display = 'none';
    }
}

async function processPayment() {
    if (POSState.cart.length === 0) {
        showNotification('El carrito está vacío', 'error');
        return;
    }
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    const customerId = document.getElementById('customerSelect').value || null;
    
    // Validar pago en efectivo
    if (paymentMethod === 'cash') {
        const total = POSState.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18;
        const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
        
        if (amountReceived < total) {
            showNotification('El monto recibido es insuficiente', 'error');
            return;
        }
    }
    
    const saleData = {
        customer_id: customerId,
        payment_method: paymentMethod,
        items: POSState.cart.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            price: item.price
        }))
    };
    
    try {
        const response = await fetch('backend/api/index.php?endpoint=sales', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saleData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            POSState.currentTransaction = result.data;
            showTransactionModal(result.data);
            clearCart();
            showNotification('Venta procesada exitosamente', 'success');
        } else {
            showNotification(result.message || 'Error al procesar la venta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error de conexión al procesar la venta', 'error');
    }
}

// ===== MODALES =====
function showTransactionModal(transaction) {
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    if (!modal || !details) return;
    
    const html = `
        <div class="transaction-summary">
            <div class="transaction-info">
                <h4>Venta #${transaction.sale_number}</h4>
                <p><strong>Fecha:</strong> ${new Date(transaction.created_at).toLocaleString('es-PE')}</p>
                <p><strong>Total:</strong> S/ ${parseFloat(transaction.total_amount).toFixed(2)}</p>
                <p><strong>Cliente:</strong> ${transaction.customer_name || 'Cliente Genérico'}</p>
                <p><strong>Método de Pago:</strong> ${getPaymentMethodName(transaction.payment_method)}</p>
            </div>
        </div>
    `;
    
    details.innerHTML = html;
    modal.style.display = 'flex';
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
    document.getElementById('customerSelect').value = '';
    document.getElementById('paymentMethod').value = 'cash';
    handlePaymentMethodChange();
}

// ===== FUNCIONES DE UTILIDAD =====
function getPaymentMethodName(method) {
    const methods = {
        'cash': 'Efectivo',
        'card': 'Tarjeta',
        'transfer': 'Transferencia',
        'mixed': 'Mixto'
    };
    return methods[method] || method;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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

function holdTransaction() {
    // Implementar lógica para suspender transacción
    showNotification('Función de suspender venta en desarrollo', 'info');
}

function printReceipt() {
    if (!POSState.currentTransaction) {
        showNotification('No hay recibo para imprimir', 'warning');
        return;
    }
    
    // Implementar lógica de impresión
    showNotification('Función de impresión en desarrollo', 'info');
}