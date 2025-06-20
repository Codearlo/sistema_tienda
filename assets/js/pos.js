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
        const isActive = POSState.selectedCategory == category.id;
        html += `
            <button class="category-btn ${isActive ? 'active' : ''}" onclick="filterByCategory(${category.id})">
                <i class="${category.icon || 'fas fa-folder'}"></i>
                ${htmlspecialchars(category.name)}
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

function loadProducts() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    const searchTerm = document.getElementById('productSearch')?.value.toLowerCase() || '';
    
    let filteredProducts = POSState.products.filter(product => {
        const matchesSearch = !searchTerm || 
            product.name.toLowerCase().includes(searchTerm) ||
            (product.sku && product.sku.toLowerCase().includes(searchTerm)) ||
            (product.barcode && product.barcode.toLowerCase().includes(searchTerm));
        
        const matchesCategory = !POSState.selectedCategory || 
            product.category_id == POSState.selectedCategory;
        
        return matchesSearch && matchesCategory;
    });
    
    if (filteredProducts.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No se encontraron productos</h3>
                <p>Intenta con otros términos de búsqueda</p>
                <button class="btn btn-outline" onclick="clearFilters()">
                    <i class="fas fa-refresh"></i> Limpiar filtros
                </button>
            </div>
        `;
        return;
    }
    
    let html = '';
    filteredProducts.forEach(product => {
        const stock = product.current_stock || product.stock_quantity || 0;
        const isLowStock = stock <= (product.min_stock || 5);
        
        html += `
            <div class="product-card ${stock === 0 ? 'out-of-stock' : ''}" onclick="addToCart(${product.id})">
                <div class="product-image">
                    <img src="${product.image_url || '/assets/images/product-placeholder.png'}" 
                         alt="${htmlspecialchars(product.name)}" 
                         onerror="this.src='/assets/images/product-placeholder.png'">
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
}

// ===== MANEJO DEL CARRITO =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    const stock = product.current_stock || product.stock_quantity || 0;
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
    
    if (newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    if (newQuantity > item.stock) {
        showMessage('Cantidad excede el stock disponible', 'warning');
        return;
    }
    
    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.price;
    
    updateCartDisplay();
    updateTotals();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const emptyState = document.getElementById('emptyCartState');
    const completeBtn = document.getElementById('completeBtn');
    
    if (!cartItems) return;
    
    if (POSState.cart.length === 0) {
        cartItems.style.display = 'none';
        if (emptyState) emptyState.style.display = 'flex';
        if (completeBtn) completeBtn.disabled = true;
        return;
    }
    
    cartItems.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (completeBtn) completeBtn.disabled = false;
    
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
    
    cartItems.innerHTML = html;
}

function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;
    
    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }
    
    // Actualizar elementos en la interfaz
    const elements = {
        'cartSubtotal': `S/ ${subtotal.toFixed(2)}`,
        'cartTax': `S/ ${tax.toFixed(2)}`,
        'cartTotal': `S/ ${total.toFixed(2)}`
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = elements[id];
        }
    });
    
    // Actualizar cambio si es necesario
    if (POSState.paymentMethod === 'cash') {
        calculateChange();
    }
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
    selectPaymentMethod('cash');
}

function selectPaymentMethod(method) {
    POSState.paymentMethod = method;
    
    // Actualizar botones
    document.querySelectorAll('.payment-method').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-method="${method}"]`).classList.add('active');
    
    // Mostrar/ocultar sección de efectivo
    const cashPayment = document.getElementById('cashPayment');
    if (method === 'cash') {
        cashPayment.style.display = 'block';
    } else {
        cashPayment.style.display = 'none';
    }
}

function calculateChange() {
    const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
    let total = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    if (POSState.includeIgv) {
        total *= 1.18;
    }
    
    POSState.cashReceived = cashReceived;
    
    const changeAmount = document.getElementById('changeAmount');
    const changeValue = document.getElementById('changeValue');
    
    if (cashReceived >= total && cashReceived > 0) {
        const change = cashReceived - total;
        changeValue.textContent = `S/ ${change.toFixed(2)}`;
        changeAmount.style.display = 'block';
    } else {
        changeAmount.style.display = 'none';
    }
}

// ===== BÚSQUEDA Y FILTROS =====
function handleProductSearch() {
    loadProducts();
    
    const searchInput = document.getElementById('productSearch');
    const clearBtn = document.querySelector('.search-clear-btn');
    
    if (searchInput.value.length > 0) {
        clearBtn.style.display = 'block';
    } else {
        clearBtn.style.display = 'none';
    }
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
            <p><strong>Total:</strong> S/ ${saleData.total}</p>
            <p><strong>Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method)}</p>
            ${saleData.change_amount > 0 ? 
                `<p><strong>Vuelto:</strong> S/ ${saleData.change_amount.toFixed(2)}</p>` : ''}
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
}

// ===== FUNCIONES DE MODAL =====
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Asegurarse de que el modal sea visible
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
        
        // Agregar clase para animación
        setTimeout(() => {
            modal.classList.add('show');
            modal.style.opacity = '1';
            modal.style.visibility = 'visible';
        }, 10);
        
        // Enfocar el primer elemento interactivo si existe
        const firstInput = modal.querySelector('input, select, textarea, button');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    } else {
        console.error('No se pudo encontrar el modal con ID:', modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Remover clase de animación
        modal.classList.remove('show');
        modal.style.opacity = '0';
        
        // Ocultar el modal después de la animación
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.visibility = 'hidden';
            document.body.style.overflow = ''; // Restaurar scroll del body
        }, 300);
    }
}

// ===== UTILIDADES =====
function clearCart() {
    POSState.cart = [];
    POSState.cashReceived = 0;
    POSState.includeIgv = true;

    document.getElementById('cashReceived').value = '';
    document.getElementById('customerSelect').value = '';
    
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
    }
}