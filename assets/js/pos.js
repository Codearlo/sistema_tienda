// Estado global del POS
const POSState = {
    cart: [],
    products: [],
    categories: [],
    customers: [],
    selectedCustomer: null,
    selectedCategory: null,
    searchTerm: '',
    isLoading: false,
    paymentMethod: 'cash'
};

// ===== FUNCIONES DE INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializePOS();
    
    // Event listeners
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(searchProducts, 300));
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    // Cerrar modales al hacer clic fuera
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
    });
});

async function initializePOS() {
    showLoading(true);
    
    try {
        // Cargar datos en paralelo para mejor rendimiento
        const [productsRes, categoriesRes, customersRes] = await Promise.all([
            fetch('backend/api/index.php?endpoint=products'),
            fetch('backend/api/index.php?endpoint=categories'),
            fetch('backend/api/index.php?endpoint=customers')
        ]);
        
        if (!productsRes.ok || !categoriesRes.ok || !customersRes.ok) {
            throw new Error('Error al cargar datos');
        }
        
        const productsData = await productsRes.json();
        const categoriesData = await categoriesRes.json();
        const customersData = await customersRes.json();
        
        if (productsData.success) {
            POSState.products = productsData.products || [];
        }
        
        if (categoriesData.success) {
            POSState.categories = categoriesData.categories || [];
        }
        
        if (customersData.success) {
            POSState.customers = customersData.customers || [];
        }
        
        // Cargar UI
        loadCategories();
        loadProducts();
        loadCustomers();
        updateCartDisplay();
        
    } catch (error) {
        console.error('Error inicializando POS:', error);
        showMessage('Error al cargar datos del POS', 'error');
    } finally {
        showLoading(false);
    }
}

// ===== FUNCIONES DE UI =====
function showLoading(show) {
    POSState.isLoading = show;
    const loader = document.getElementById('posLoader');
    if (loader) {
        loader.style.display = show ? 'flex' : 'none';
    }
}

function showMessage(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ===== GESTIÓN DE PRODUCTOS =====
function searchProducts() {
    const searchTerm = document.getElementById('productSearch').value;
    POSState.searchTerm = searchTerm;
    loadProducts();
}

function filterByCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    
    // Actualizar botones de categorías
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (categoryId) {
        event.target.classList.add('active');
    } else {
        document.querySelector('.category-btn[onclick="filterByCategory(null)"]').classList.add('active');
    }
    
    loadProducts();
}

function loadCategories() {
    const grid = document.getElementById('categoriesGrid');
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
                ${category.name}
            </button>
        `;
    });
    
    grid.innerHTML = html;
}

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
        emptyState.style.display = 'flex';
        return;
    }
    
    grid.style.display = 'grid';
    emptyState.style.display = 'none';
    
    const html = filteredProducts.map(product => `
        <div class="product-card ${product.stock_quantity <= 0 ? 'out-of-stock' : ''}" 
             onclick="${product.stock_quantity > 0 ? `addToCart(${product.id})` : ''}">
            <div class="product-image">
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` :
                    '<div class="product-placeholder"><i class="fas fa-box"></i></div>'
                }
            </div>
            <div class="product-info">
                <h4 class="product-name">${product.name}</h4>
                <p class="product-category">${product.category_name || 'Sin categoría'}</p>
                <div class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</div>
                <div class="product-stock ${product.stock_quantity <= 5 ? 'low-stock' : ''}">
                    Stock: ${product.stock_quantity || 0}
                </div>
            </div>
        </div>
    `).join('');
    
    grid.innerHTML = html;
}

// ===== MANEJO DEL CARRITO =====
function addToCart(productId) {
    const product = POSState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    // Verificar stock
    const currentQuantity = POSState.cart.reduce((sum, item) => 
        item.product_id === productId ? sum + item.quantity : sum, 0);
    
    if (currentQuantity >= (product.stock_quantity || 0)) {
        showMessage('Stock insuficiente', 'warning');
        return;
    }
    
    // Buscar si ya existe en el carrito
    const existingItem = POSState.cart.find(item => item.product_id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
        existingItem.subtotal = existingItem.quantity * existingItem.price;
    } else {
        POSState.cart.push({
            product_id: productId,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            subtotal: parseFloat(product.selling_price)
        });
    }
    
    updateCartDisplay();
    updateTotals();
    showMessage(`${product.name} agregado al carrito`, 'success');
}

function removeFromCart(productId) {
    POSState.cart = POSState.cart.filter(item => item.product_id !== productId);
    updateCartDisplay();
    updateTotals();
}

function updateQuantity(productId, newQuantity) {
    const item = POSState.cart.find(item => item.product_id === productId);
    if (!item) return;
    
    if (newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }
    
    // Verificar stock
    const product = POSState.products.find(p => p.id == productId);
    if (newQuantity > (product.stock_quantity || 0)) {
        showMessage('Stock insuficiente', 'warning');
        return;
    }
    
    item.quantity = newQuantity;
    item.subtotal = item.quantity * item.price;
    
    updateCartDisplay();
    updateTotals();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    
    if (!cartItems) return;
    
    if (POSState.cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-shopping-cart fa-2x"></i>
                <h3>El carrito está vacío</h3>
                <p>Agregue productos para comenzar una venta</p>
            </div>
        `;
        if (cartCount) cartCount.textContent = '0 productos';
        return;
    }
    
    const html = POSState.cart.map(item => `
        <div class="cart-item" data-product-id="${item.product_id}">
            <div class="cart-item-info">
                <h4>${item.name}</h4>
                <div class="cart-item-price">S/ ${item.price.toFixed(2)}</div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-control">
                    <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" 
                           class="qty-input" 
                           value="${item.quantity}" 
                           min="1" 
                           onchange="updateQuantity(${item.product_id}, parseInt(this.value))">
                    <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="cart-item-subtotal">
                    S/ ${item.subtotal.toFixed(2)}
                </div>
                <button class="btn-icon delete" onclick="removeFromCart(${item.product_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    cartItems.innerHTML = html;
    
    const totalItems = POSState.cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCount) cartCount.textContent = `${totalItems} productos`;
}

function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    const tax = subtotal * 0.18; // IGV 18%
    const total = subtotal + tax;
    
    document.getElementById('subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `S/ ${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `S/ ${total.toFixed(2)}`;
}

function clearCart() {
    if (POSState.cart.length === 0) return;
    
    if (confirm('¿Está seguro de limpiar el carrito?')) {
        POSState.cart = [];
        updateCartDisplay();
        updateTotals();
        showMessage('Carrito limpiado', 'info');
    }
}

// ===== GESTIÓN DE CLIENTES =====
function loadCustomers() {
    const select = document.getElementById('customerSelect');
    if (!select) return;
    
    const html = `
        <option value="">Cliente General</option>
        ${POSState.customers.map(customer => 
            `<option value="${customer.id}">${customer.first_name} ${customer.last_name || ''}</option>`
        ).join('')}
    `;
    
    select.innerHTML = html;
}

function selectCustomer() {
    const select = document.getElementById('customerSelect');
    POSState.selectedCustomer = select.value ? parseInt(select.value) : null;
}

function showAddCustomerModal() {
    document.getElementById('addCustomerModal').style.display = 'flex';
}

function closeAddCustomerModal() {
    document.getElementById('addCustomerModal').style.display = 'none';
    document.getElementById('addCustomerForm').reset();
}

async function addCustomerSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const customerData = {
        first_name: formData.get('first_name'),
        last_name: formData.get('last_name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        document_number: formData.get('document_number')
    };
    
    try {
        const response = await fetch('backend/api/index.php?endpoint=customers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Cliente agregado exitosamente', 'success');
            closeAddCustomerModal();
            
            // Recargar clientes
            const customersRes = await fetch('backend/api/index.php?endpoint=customers');
            const customersData = await customersRes.json();
            if (customersData.success) {
                POSState.customers = customersData.customers;
                loadCustomers();
                
                // Seleccionar el nuevo cliente
                document.getElementById('customerSelect').value = result.customer.id;
                POSState.selectedCustomer = result.customer.id;
            }
        } else {
            showMessage(result.message || 'Error al agregar cliente', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

// ===== PROCESAMIENTO DE PAGOS =====
function showPaymentModal() {
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return;
    }
    
    const modal = document.getElementById('paymentModal');
    const total = parseFloat(document.getElementById('total').textContent.replace('S/ ', ''));
    
    document.getElementById('paymentTotal').textContent = `S/ ${total.toFixed(2)}`;
    document.getElementById('cashReceived').value = '';
    document.getElementById('changeAmount').textContent = 'S/ 0.00';
    
    modal.style.display = 'flex';
    
    // Focus en el campo de efectivo recibido
    setTimeout(() => {
        document.getElementById('cashReceived').focus();
    }, 100);
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function selectPaymentMethod(method) {
    POSState.paymentMethod = method;
    
    // Actualizar UI
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Mostrar/ocultar campo de efectivo
    const cashField = document.getElementById('cashReceivedField');
    if (method === 'cash') {
        cashField.style.display = 'block';
        document.getElementById('cashReceived').focus();
    } else {
        cashField.style.display = 'none';
        document.getElementById('changeAmount').textContent = 'S/ 0.00';
    }
}

function calculateChange() {
    const total = parseFloat(document.getElementById('paymentTotal').textContent.replace('S/ ', ''));
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    const change = Math.max(0, received - total);
    
    document.getElementById('changeAmount').textContent = `S/ ${change.toFixed(2)}`;
}

async function processSale() {
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return;
    }
    
    // Validar efectivo recibido si es pago en efectivo
    if (POSState.paymentMethod === 'cash') {
        const total = parseFloat(document.getElementById('paymentTotal').textContent.replace('S/ ', ''));
        const received = parseFloat(document.getElementById('cashReceived').value) || 0;
        
        if (received < total) {
            showMessage('El efectivo recibido es insuficiente', 'error');
            return;
        }
    }
    
    showLoading(true);
    
    const saleData = {
        customer_id: POSState.selectedCustomer,
        items: POSState.cart,
        payment_method: POSState.paymentMethod,
        notes: document.getElementById('saleNotes')?.value || ''
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
            showMessage('Venta procesada exitosamente', 'success');
            
            // Limpiar carrito y actualizar stock
            POSState.cart = [];
            updateCartDisplay();
            updateTotals();
            closePaymentModal();
            
            // Recargar productos para actualizar stock
            const productsRes = await fetch('backend/api/index.php?endpoint=products');
            const productsData = await productsRes.json();
            if (productsData.success) {
                POSState.products = productsData.products;
                loadProducts();
            }
            
            // Preguntar si desea imprimir recibo
            if (confirm('¿Desea imprimir el recibo?')) {
                printReceipt(result.sale);
            }
        } else {
            showMessage(result.message || 'Error al procesar la venta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    } finally {
        showLoading(false);
    }
}

// ===== FUNCIONES DE IMPRESIÓN =====
function printReceipt(sale) {
    // Crear ventana de impresión
    const printWindow = window.open('', '_blank', 'width=300,height=600');
    
    const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Recibo - ${sale.sale_number}</title>
            <style>
                body {
                    font-family: monospace;
                    font-size: 12px;
                    margin: 0;
                    padding: 10px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 10px;
                }
                .divider {
                    border-top: 1px dashed #000;
                    margin: 10px 0;
                }
                .item {
                    margin: 5px 0;
                }
                .total {
                    font-weight: bold;
                    font-size: 14px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>TREINTA POS</h2>
                <p>Recibo: ${sale.sale_number}</p>
                <p>${new Date(sale.sale_date).toLocaleString()}</p>
            </div>
            
            <div class="divider"></div>
            
            <div class="items">
                ${sale.items.map(item => `
                    <div class="item">
                        <div>${item.name}</div>
                        <div>${item.quantity} x S/ ${item.price.toFixed(2)} = S/ ${item.subtotal.toFixed(2)}</div>
                    </div>
                `).join('')}
            </div>
            
            <div class="divider"></div>
            
            <div class="totals">
                <div>Subtotal: S/ ${sale.subtotal.toFixed(2)}</div>
                <div>IGV (18%): S/ ${sale.tax_amount.toFixed(2)}</div>
                <div class="total">TOTAL: S/ ${sale.total_amount.toFixed(2)}</div>
            </div>
            
            <div class="divider"></div>
            
            <div class="payment">
                <div>Método: ${sale.payment_method.toUpperCase()}</div>
                ${sale.payment_method === 'cash' ? `
                    <div>Recibido: S/ ${sale.cash_received.toFixed(2)}</div>
                    <div>Cambio: S/ ${sale.change_amount.toFixed(2)}</div>
                ` : ''}
            </div>
            
            <div class="footer">
                <p>¡Gracias por su compra!</p>
                <p>Powered by Treinta POS</p>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(receiptHTML);
    printWindow.document.close();
    
    // Imprimir automáticamente
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };
}

// ===== FUNCIONES AUXILIARES =====
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

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

function handleKeyboardShortcuts(e) {
    // F2 - Buscar producto
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('productSearch')?.focus();
    }
    
    // F4 - Procesar pago
    if (e.key === 'F4') {
        e.preventDefault();
        showPaymentModal();
    }
    
    // F6 - Limpiar carrito
    if (e.key === 'F6') {
        e.preventDefault();
        clearCart();
    }
    
    // ESC - Cerrar modales
    if (e.key === 'Escape') {
        closeAllModals();
    }
}

// Función para manejar el menú móvil
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}