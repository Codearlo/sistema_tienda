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
    suspendedSales: [], // Nuevo estado para las ventas suspendidas
    currentSuspendedSaleId: null // ID de la venta suspendida que se está reanudando
};

// ===== INICIALIZACIÓN =====
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
    if (typeof initialSuspendedSales !== 'undefined') {
        POSState.suspendedSales = initialSuspendedSales;
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
}

// ===== MANEJO DE PRODUCTOS =====
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
        <div class="product-card" onclick="addToCart(${product.id})">
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
                <div class="product-stock ${product.current_stock <= 5 ? 'low-stock' : ''}">
                    Stock: ${product.current_stock || 0}
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
    
    if (currentQuantity >= (product.current_stock || 0)) {
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
    if (newQuantity > (product.current_stock || 0)) {
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
                <p>Agregue productos para comenzar</p>
            </div>
        `;
        cartCount.textContent = '0 productos';
        document.getElementById('customerSelect').value = ''; // Limpiar cliente
    } else {
        const html = POSState.cart.map(item => `
            <div class="cart-item">
                <div class="item-info">
                    <h4 class="item-name">${item.name}</h4>
                    <p class="item-price">S/ ${item.price.toFixed(2)}</p>
                </div>
                <div class="item-controls">
                    <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="item-quantity">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="item-total">
                    S/ ${item.subtotal.toFixed(2)}
                </div>
                <button class="remove-btn" onclick="removeFromCart(${item.product_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');
        
        cartItems.innerHTML = html;
        cartCount.textContent = `${POSState.cart.length} productos`;
    }
}

// ===== CÁLCULOS =====
function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    const igvRow = document.getElementById('igvRow');

    if (POSState.includeIgv) {
        tax = subtotal * 0.18; // IGV 18%
        if (igvRow) igvRow.style.display = 'flex'; // Mostrar fila de IGV
    } else {
        if (igvRow) igvRow.style.display = 'none'; // Ocultar fila de IGV
    }
    
    const total = subtotal + tax;
    
    document.getElementById('subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `S/ ${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `S/ ${total.toFixed(2)}`;
    
    // Mostrar/ocultar secciones
    const cartSummary = document.getElementById('cartSummary');
    const paymentSection = document.getElementById('paymentSection');
    const completeBtn = document.getElementById('completeBtn');
    
    if (POSState.cart.length > 0) {
        cartSummary.style.display = 'block';
        paymentSection.style.display = 'block';
        completeBtn.disabled = false;
    } else {
        cartSummary.style.display = 'none';
        paymentSection.style.display = 'none';
        completeBtn.disabled = true;
    }
}

// Función para alternar el IGV
function toggleIgv() {
    POSState.includeIgv = !POSState.includeIgv;
    updateIgvButtonState();
    updateTotals();
    showMessage(POSState.includeIgv ? 'IGV incluido' : 'IGV no incluido', 'info');
}

// Actualizar el estado visual del botón IGV
function updateIgvButtonState() {
    const toggleIgvBtn = document.getElementById('toggleIgvBtn');
    if (toggleIgvBtn) {
        if (POSState.includeIgv) {
            toggleIgvBtn.classList.add('active'); // Opcional: añadir clase 'active' para estilos visuales
            toggleIgvBtn.textContent = 'IGV (18%) Incluido';
        } else {
            toggleIgvBtn.classList.remove('active');
            toggleIgvBtn.textContent = 'IGV (18%) No Incluido';
        }
    }
}

// ===== MÉTODOS DE PAGO =====
function setupPaymentMethods() {
    const methods = document.querySelectorAll('.payment-method');
    methods.forEach(method => {
        method.addEventListener('click', () => {
            methods.forEach(m => m.classList.remove('active'));
            method.classList.add('active');
            POSState.paymentMethod = method.dataset.method;
            
            // Mostrar/ocultar sección de efectivo
            const cashPayment = document.getElementById('cashPayment');
            if (POSState.paymentMethod === 'cash') {
                cashPayment.style.display = 'block';
            } else {
                cashPayment.style.display = 'none';
            }
        });
    });
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
        change_amount: POSState.paymentMethod === 'cash' ? (POSState.cashReceived - total) : 0,
        suspended_sale_id: POSState.currentSuspendedSaleId // Enviar ID de venta suspendida si aplica
    };
    
    try {
        const response = await API.post('/ventas.php', saleData);
        
        if (response.success) {
            showTransactionComplete(response.data);
            clearCart();
            showMessage('Venta completada exitosamente', 'success');
            // Si la venta se completó y venía de una suspendida, actualizar la lista
            if (POSState.currentSuspendedSaleId) {
                removeSuspendedSaleFromList(POSState.currentSuspendedSaleId);
                POSState.currentSuspendedSaleId = null; // Resetear
            }
            // Recargar productos para reflejar el stock actualizado (asumiendo que backend lo maneja)
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



async function holdTransaction() {
    console.log('🔍 Iniciando holdTransaction()');
    
    if (POSState.cart.length === 0) {
        showMessage('No hay productos en el carrito para suspender la venta.', 'warning');
        return;
    }

    const subtotal = POSState.cart.reduce((sum, item) => sum + item.subtotal, 0);
    let tax = 0;
    let total = subtotal;

    if (POSState.includeIgv) {
        tax = subtotal * 0.18;
        total = subtotal + tax;
    }

    const suspendedSaleData = {
        customer_id: document.getElementById('customerSelect').value || null,
        items: POSState.cart,
        subtotal: subtotal,
        tax: tax,
        total: total,
        includeIgv: POSState.includeIgv
    };

    console.log('📦 Datos a enviar:', suspendedSaleData);
    console.log('🎯 URL de destino:', API.baseURL + '/suspended_sales.php');

    try {
        console.log('🌐 Enviando petición...');
        // Modificación: Eliminar el parámetro 'debug=1' de la URL de la API
        const response = await API.post('/suspended_sales.php', suspendedSaleData);
        console.log('✅ Respuesta recibida:', response);
        
        if (response.success) {
            showMessage('Venta suspendida exitosamente. Nº de Venta Suspendida: ' + response.data.sale_number, 'success');
            POSState.suspendedSales.unshift(response.data);
            clearCart();
        } else {
            console.error('❌ Error en respuesta:', response);
            showMessage(response.message || 'Error al suspender la venta', 'error');
        }
    } catch (error) {
        console.error('💥 Error capturado:', error);
        console.error('📊 Detalles del error:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        showMessage('Error de conexión al suspender venta: ' + error.message, 'error');
    }
}

function openSuspendedSalesModal() {
    const modal = document.getElementById('suspendedSalesModal');
    if (modal) {
        renderSuspendedSalesList(); // Renderizar la lista cada vez que se abre
        modal.style.display = 'flex';
    }
}

function closeSuspendedSalesModal() {
    const modal = document.getElementById('suspendedSalesModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function renderSuspendedSalesList() {
    const listContainer = document.getElementById('suspendedSalesList');
    if (!listContainer) return;

    if (POSState.suspendedSales.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-box-open fa-2x"></i>
                <p>No hay ventas suspendidas.</p>
            </div>
        `;
        return;
    }

    const html = POSState.suspendedSales.map(sale => {
        const customer = POSState.customers.find(c => c.id == sale.customer_id);
        const customerName = customer ? customer.name : 'Cliente General';
        const saleDate = new Date(sale.created_at).toLocaleString('es-PE', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        return `
            <div class="suspended-sale-item" data-sale-id="${sale.id}">
                <div class="sale-info">
                    <h4>Venta Suspendida #${sale.sale_number || sale.id}</h4>
                    <p>Cliente: ${customerName}</p>
                    <p>Total Estimado: S/ ${parseFloat(sale.total).toFixed(2)}</p>
                    <p>Fecha: ${saleDate}</p>
                </div>
                <div class="sale-actions">
                    <button class="btn btn-primary btn-sm" onclick="resumeSuspendedSale(${sale.id})">
                        <i class="fas fa-play"></i> Reanudar
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteSuspendedSale(${sale.id})">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        `;
    }).join('');

    listContainer.innerHTML = html;
}

async function resumeSuspendedSale(saleId) {
    try {
        const response = await API.get(`/suspended_sales.php?id=${saleId}`);
        
        if (response.success && response.data) {
            const suspendedSale = response.data;
            
            // Limpiar el carrito actual antes de cargar la venta suspendida
            clearCart(); 

            // Cargar los items de la venta suspendida al carrito
            POSState.cart = suspendedSale.items.map(item => ({
                product_id: item.product_id,
                name: item.product_name,
                price: parseFloat(item.price),
                quantity: parseInt(item.quantity),
                subtotal: parseFloat(item.subtotal)
            }));

            // Establecer el cliente si existe
            if (suspendedSale.customer_id) {
                document.getElementById('customerSelect').value = suspendedSale.customer_id;
            }

            // Establecer el estado del IGV (asumiendo que se guardó, o por defecto)
            POSState.includeIgv = suspendedSale.include_igv !== undefined ? suspendedSale.include_igv : true;

            POSState.currentSuspendedSaleId = saleId; // Guardar el ID de la venta suspendida reanudada

            updateCartDisplay();
            updateTotals();
            updateIgvButtonState();
            closeSuspendedSalesModal();
            showMessage(`Venta suspendida #${suspendedSale.sale_number} reanudada.`, 'success');

        } else {
            showMessage(response.message || 'Error al reanudar la venta suspendida', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión al reanudar venta', 'error');
    }
}

async function deleteSuspendedSale(saleId) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta venta suspendida? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
        const response = await API.delete(`/suspended_sales.php?id=${saleId}`);
        
        if (response.success) {
            showMessage('Venta suspendida eliminada exitosamente.', 'success');
            removeSuspendedSaleFromList(saleId);
            renderSuspendedSalesList(); // Volver a renderizar la lista
        } else {
            showMessage(response.message || 'Error al eliminar la venta suspendida', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión al eliminar venta', 'error');
    }
}

function removeSuspendedSaleFromList(saleId) {
    POSState.suspendedSales = POSState.suspendedSales.filter(sale => sale.id != saleId);
}


// ===== UTILIDADES =====
function clearCart() {
    POSState.cart = [];
    POSState.cashReceived = 0;
    POSState.includeIgv = true; // Resetear el estado del IGV al limpiar el carrito
    POSState.currentSuspendedSaleId = null; // Asegurarse de limpiar el ID de venta suspendida

    document.getElementById('cashReceived').value = '';
    document.getElementById('customerSelect').value = '';
    
    updateCartDisplay();
    updateTotals();
    updateIgvButtonState(); // Actualizar el botón del IGV
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
    
    // Divide la fecha y la hora si es necesario o muestra como una sola línea
    // const parts = dateTimeString.split(', ');
    // timeElement.innerHTML = `${parts[0]}<br>${parts[1]}`;
    timeElement.innerHTML = dateTimeString;
}

function printReceipt() {
    showMessage('Funcionalidad de impresión en desarrollo', 'info');
}

function showMessage(message, type = 'info') {
    // Usar sistema básico de alertas
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

// ===== MOBILE MENU =====
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    }
}