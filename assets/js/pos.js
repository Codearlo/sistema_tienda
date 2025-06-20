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
                    <img src="${product.image_url || 'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http://www.w3.org/2000/svg%22%20width%3D%22120%22%20height%3D%22120%22%3E%3Crect%20width%3D%22120%22%20height%3D%22120%22%20fill%3D%22%23dddddd%22/%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-size%3D%2214%22%20fill%3D%22%23666666%22%3ESin%20Imagen%3C/text%3E%3C/svg%3E'}" 
                         alt="${htmlspecialchars(product.name)}" 
                         >
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

// ===== CÁLCULO DE CAMBIO =====
function calculateChange() {
    console.log('Calculando cambio...');
    
    // Verificar que el método de pago sea efectivo
    if (POSState.paymentMethod !== 'cash') {
        console.log('No es pago en efectivo, no se calcula cambio');
        return;
    }
    
    // Obtener referencias a los elementos del DOM
    const cashInput = document.getElementById('cashReceivedInput');
    const changeAmount = document.getElementById('changeAmount');
    const changeValue = document.getElementById('changeValue');
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    
    // Verificar que todos los elementos necesarios existan
    if (!cashInput || !changeAmount || !changeValue || !confirmBtn) {
        console.error('Elementos del formulario de pago no encontrados');
        return;
    }
    
    // Convertir el valor a número y asegurarse de que sea un número válido
    const cashReceived = parseFloat(cashInput.value) || 0;
    
    // Calcular el total de la compra
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const total = POSState.includeIgv ? subtotal * 1.18 : subtotal;
    const change = cashReceived - total;
    
    console.log('Monto recibido:', cashReceived, 'Total:', total, 'Cambio:', change);
    
    // Actualizar la interfaz de usuario
    if (cashReceived > 0) {
        if (change >= 0) {
            // Monto suficiente
            changeValue.textContent = `S/ ${change.toFixed(2)}`;
            changeAmount.style.display = 'block';
            changeAmount.style.color = '#10b981'; // Verde para cambio positivo
            confirmBtn.disabled = false;
            
            // Resaltar el botón de confirmación
            confirmBtn.classList.remove('btn-primary');
            confirmBtn.classList.add('btn-success');
            confirmBtn.innerHTML = '<i class="fas fa-check-double"></i> Confirmar Pago';
        } else {
            // Monto insuficiente
            const amountNeeded = Math.abs(change);
            changeValue.textContent = `Faltan S/ ${amountNeeded.toFixed(2)}`;
            changeAmount.style.display = 'block';
            changeAmount.style.color = '#ef4444'; // Rojo para monto insuficiente
            confirmBtn.disabled = true;
            
            // Restaurar el estilo del botón
            confirmBtn.classList.remove('btn-success');
            confirmBtn.classList.add('btn-primary');
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirmar Pago';
        }
    } else {
        // Monto no ingresado o cero
        changeAmount.style.display = 'none';
        confirmBtn.disabled = true;
        
        // Restaurar el estilo del botón
        confirmBtn.classList.remove('btn-success');
        confirmBtn.classList.add('btn-primary');
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirmar Pago';
    }
    
    // Actualizar el estado global con el monto recibido
    POSState.cashReceived = cashReceived;
    
    console.log('Cálculo de cambio completado');
}

function selectPaymentMethod(method) {
    console.log('Seleccionando método de pago:', method);
    
    if (!['cash', 'card'].includes(method)) {
        console.error('Método de pago no válido:', method);
        return false;
    }
    
    // Actualizar el estado global
    POSState.paymentMethod = method;
    
    // Actualizar botones de método de pago
    const paymentButtons = document.querySelectorAll('.payment-method-btn');
    if (paymentButtons.length > 0) {
        paymentButtons.forEach(btn => {
            if (btn.dataset.method === method) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    // Mostrar/ocultar sección de efectivo
    const cashSection = document.getElementById('cashPaymentSection');
    if (cashSection) {
        cashSection.style.display = method === 'cash' ? 'block' : 'none';
    }
    
    // Si es pago con tarjeta, limpiar el campo de monto recibido
    if (method === 'card') {
        const cashInput = document.getElementById('cashReceivedInput');
        if (cashInput) {
            cashInput.value = '0.00';
        }
        
        const changeAmount = document.getElementById('changeAmount');
        if (changeAmount) {
            changeAmount.style.display = 'none';
        }
    }
    
    // Calcular cambio si es pago en efectivo
    if (method === 'cash') {
        // Pequeño retraso para asegurar que el input esté visible
        setTimeout(() => {
            const cashInput = document.getElementById('cashReceivedInput');
            if (cashInput) {
                cashInput.focus();
                cashInput.select();
            }
            calculateChange();
        }, 300); // Aumentar el tiempo para asegurar que el input esté listo
    }
    
    // Actualizar estado del botón de confirmar pago
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    if (confirmBtn) {
        if (method === 'cash') {
            // Para pago en efectivo, el botón se habilita solo si hay suficiente monto
            const cashInput = document.getElementById('cashReceivedInput');
            if (cashInput) {
                const cashReceived = parseFloat(cashInput.value) || 0;
                const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
                const total = POSState.includeIgv ? subtotal * 1.18 : subtotal;
                confirmBtn.disabled = cashReceived < total;
            } else {
                confirmBtn.disabled = true;
            }
        } else {
            // Para pago con tarjeta, el botón siempre está habilitado
            confirmBtn.disabled = false;
        }
    }
    
    return true;
}

// ===== BÚSQUEDA Y FILTROS =====
function handleProductSearch() {
    const searchInput = document.getElementById('productSearch');
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    
    if (searchTerm) {
        const filtered = POSState.products.filter(product => 
            product.name.toLowerCase().includes(searchTerm) ||
            (product.barcode && product.barcode.includes(searchTerm))
        );
        loadProducts(filtered);
    } else {
        loadProducts();
    }
    
    // Mostrar/ocultar botón de limpiar búsqueda
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
    POSState.selectedCategory = categoryId === 'all' ? null : categoryId;
    loadProducts();
    
    // Actualizar botones de categoría
    const categoryButtons = document.querySelectorAll('.category-btn');
    if (categoryButtons.length > 0) {
        categoryButtons.forEach(btn => {
            const btnCategoryId = btn.getAttribute('data-category-id');
            if ((!categoryId && !btnCategoryId) || btnCategoryId === categoryId) {
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
    if (!document.getElementById('paymentModal')) {
        createPaymentModal();
    }
    
    const paymentModal = document.getElementById('paymentModal');
    const paymentContent = document.getElementById('paymentContent');
    
    if (!paymentModal || !paymentContent) {
        console.error('No se pudo crear el modal de pago');
        showMessage('Error al cargar el formulario de pago', 'error');
        return false;
    }
    
    // Actualizar el contenido del modal de pago
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
            <h4>Método de pago</h4>
            <div class="payment-methods">
                <button type="button" class="payment-method-btn ${POSState.paymentMethod === 'cash' ? 'active' : ''}" 
                        data-method="cash" 
                        onclick="selectPaymentMethod('cash')">
                    <i class="fas fa-money-bill"></i>
                    <span>Efectivo</span>
                </button>
                <button type="button" class="payment-method-btn ${POSState.paymentMethod === 'card' ? 'active' : ''}" 
                        data-method="card" 
                        onclick="selectPaymentMethod('card')">
                    <i class="fas fa-credit-card"></i>
                    <span>Tarjeta</span>
                </button>
            </div>
        </div>
        
        <div id="cashPaymentSection" class="cash-payment-section" style="display: ${POSState.paymentMethod === 'cash' ? 'block' : 'none'}">
            <div class="form-group">
                <label for="cashReceivedInput">Monto recibido:</label>
                <input type="number" id="cashReceivedInput" class="form-control" 
                       step="0.01" min="0" value="0.00" 
                       oninput="calculateChange()"
                       placeholder="0.00">
            </div>
            <div id="changeAmount" class="change-amount" style="display: none;">
                Vuelto: <span id="changeValue" class="change-value">S/ 0.00</span>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" id="confirmPaymentBtn" class="btn btn-primary" ${POSState.paymentMethod === 'cash' ? 'disabled' : ''}>
                <i class="fas fa-check"></i> Confirmar Pago
            </button>
        </div>
    `;
    
    // Configurar evento click para el botón de confirmar pago
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', processPayment);
    }
    
    // Mostrar el modal después de actualizar su contenido
    openModal('paymentModal');
    
    // Configurar el método de pago inicial
    selectPaymentMethod(POSState.paymentMethod || 'cash');
    
    // Enfocar el campo de monto recibido si es pago en efectivo
    if (POSState.paymentMethod === 'cash') {
        setTimeout(() => {
            const cashInput = document.getElementById('cashReceivedInput');
            if (cashInput) {
                cashInput.focus();
                cashInput.select();
            }
        }, 300); // Aumentar el tiempo para asegurar que el input esté listo
    }
    
    return false; // Prevenir comportamiento por defecto del botón
}

// Función para crear el modal de pago dinámicamente si no existe
function createPaymentModal() {
    console.log('Creando modal de pago...');
    
    // Verificar si ya existe el modal
    if (document.getElementById('paymentModal')) {
        console.log('El modal de pago ya existe');
        return;
    }
    
    // Crear el elemento del modal
    const modalHTML = `
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Procesar Pago</h3>
                <button type="button" class="modal-close" onclick="closeModal('paymentModal')" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div id="paymentContent">
                    <!-- El contenido se llenará dinámicamente con JavaScript -->
                </div>
            </div>
        </div>
    </div>`;
    
    // Crear un contenedor temporal
    const temp = document.createElement('div');
    temp.innerHTML = modalHTML;
    
    // Agregar el modal al final del body
    const modalElement = temp.firstElementChild;
    document.body.appendChild(modalElement);
    
    console.log('Modal de pago creado exitosamente');
}

async function processPayment() {
    console.log('Iniciando proceso de pago...', POSState);
    
    // Validar que haya productos en el carrito
    if (POSState.cart.length === 0) {
        showMessage('El carrito está vacío', 'warning');
        return false;
    }
    
    // Calcular totales
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const total = POSState.includeIgv ? subtotal * 1.18 : subtotal;
    let cashReceived = 0;
    
    // Validar método de pago
    if (POSState.paymentMethod === 'cash') {
        // Validar monto recibido para pago en efectivo
        const cashInput = document.getElementById('cashReceivedInput');
        if (!cashInput) {
            console.error('No se encontró el campo de monto recibido');
            showMessage('Error al procesar el pago en efectivo', 'error');
            return false;
        }
        
        cashReceived = parseFloat(cashInput.value) || 0;
        
        // Validar que el monto sea suficiente
        if (cashReceived <= 0) {
            showMessage('Ingrese un monto válido', 'warning');
            return false;
        } else if (cashReceived < total) {
            showMessage('El monto recibido es insuficiente', 'warning');
            return false;
        }
        
        // Actualizar estado con el monto recibido
        POSState.cashReceived = cashReceived;
        console.log('Pago en efectivo validado. Monto recibido:', cashReceived);
    } else if (POSState.paymentMethod === 'card') {
        console.log('Procesando pago con tarjeta...');
        // Aquí podrías agregar validaciones específicas para tarjeta si es necesario
    } else {
        console.error('Método de pago no válido:', POSState.paymentMethod);
        showMessage('Método de pago no válido', 'error');
        return false;
    }
    
    // Mostrar mensaje de confirmación
    showMessage('Procesando pago, por favor espere...', 'info');
    
    try {
        // Cerrar el modal de pago
        closeModal('paymentModal');
        
        // Completar la transacción
        await completeTransaction();
        
        // Mostrar mensaje de éxito
        showMessage('Pago procesado exitosamente', 'success');
        
        return true;
    } catch (error) {
        console.error('Error al procesar el pago:', error);
        showMessage('Error al procesar el pago: ' + (error.message || 'Error desconocido'), 'error');
        return false;
    }
}

async function completeTransaction() {
    console.log('Completando transacción...');
    
    // Validar que haya productos en el carrito
    if (POSState.cart.length === 0) {
        console.error('No hay productos en el carrito');
        throw new Error('No hay productos en el carrito');
    }
    
    // Calcular totales
    const subtotal = POSState.cart.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
    const tax = POSState.includeIgv ? subtotal * 0.18 : 0;
    const total = subtotal + tax;
    
    console.log('Datos de la venta:', { subtotal, tax, total, paymentMethod: POSState.paymentMethod });
    
    // Preparar datos de la venta
    const saleData = {
        customer_id: document.getElementById('customerSelect') ? document.getElementById('customerSelect').value : null,
        payment_method: POSState.paymentMethod || 'cash',
        items: POSState.cart.map(item => ({
            product_id: item.id,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.subtotal,
            name: item.name
        })),
        subtotal: subtotal,
        tax: tax,
        total: total,
        cash_received: POSState.cashReceived || 0,
        change_amount: POSState.paymentMethod === 'cash' ? 
            ((POSState.cashReceived || 0) - total) : 0,
    };
    
    console.log('Enviando datos de venta al servidor:', saleData);
    
    try {
        // Mostrar indicador de carga
        showMessage('Procesando transacción, por favor espere...', 'info');
        
        // Enviar datos al servidor
        const response = await API.post('/sales.php', saleData);
        
        console.log('Respuesta del servidor:', response);
        
        if (response && response.success) {
            // Mostrar recibo o resumen de la venta
            if (response.data) {
                showTransactionComplete(response.data);
            }
            
            // Limpiar el carrito
            clearCart();
            
            // Actualizar la lista de productos
            loadProducts();
            
            // Mostrar mensaje de éxito
            showMessage('Venta completada exitosamente', 'success');
            
            return response.data; // Devolver los datos de la venta
        } else {
            // Manejar error del servidor
            const errorMessage = response && response.message ? response.message : 'Error desconocido al procesar la venta';
            console.error('Error en la respuesta del servidor:', errorMessage);
            throw new Error(errorMessage);
        }
    } catch (error) {
        console.error('Error al completar la transacción:', error);
        throw error; // Relanzar el error para que sea manejado por processPayment
    }
}

function showTransactionComplete(saleData) {
    console.log('Mostrando resumen de transacción:', saleData);
    
    // Asegurarse de que saleData sea un objeto
    if (!saleData || typeof saleData !== 'object') {
        console.error('Datos de transacción no válidos:', saleData);
        return;
    }
    
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    if (!modal || !details) {
        console.error('Elementos del modal de transacción no encontrados');
        return;
    }
    
    // Formatear la fecha
    const saleDate = saleData.created_at ? new Date(saleData.created_at) : new Date();
    const formattedDate = saleDate.toLocaleString('es-PE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Calcular totales si no están incluidos en saleData
    const subtotal = saleData.subtotal || (saleData.items ? 
        saleData.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0) : 0);
    const tax = saleData.tax || (saleData.include_igv ? subtotal * 0.18 : 0);
    const total = saleData.total || (subtotal + tax);
    
    // Generar lista de productos
    const itemsList = saleData.items && Array.isArray(saleData.items) ? 
        saleData.items.map(item => `
            <div class="transaction-item">
                <span class="item-name">${item.quantity}x ${item.name || 'Producto'}</span>
                <span class="item-price">S/ ${(parseFloat(item.subtotal) || 0).toFixed(2)}</span>
            </div>`
        ).join('') : '';
    
    // Construir el HTML del resumen
    details.innerHTML = `
        <div class="transaction-summary">
            <div class="transaction-header">
                <h3>¡Venta Completada!</h3>
                <p class="sale-number">#${saleData.sale_number || 'N/A'}</p>
            </div>
            
            <div class="transaction-details">
                <div class="transaction-info">
                    <p><strong>Fecha:</strong> ${formattedDate}</p>
                    <p><strong>Método de pago:</strong> ${getPaymentMethodName(saleData.payment_method || 'cash')}</p>
                    ${saleData.customer_name ? 
                        `<p><strong>Cliente:</strong> ${saleData.customer_name}</p>` : ''}
                </div>
                
                <div class="transaction-items">
                    <h4>Productos</h4>
                    ${itemsList || '<p>No hay productos en esta venta</p>'}
                </div>
                
                <div class="transaction-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>S/ ${subtotal.toFixed(2)}</span>
                    </div>
                    ${saleData.include_igv ? `
                    <div class="total-row">
                        <span>IGV (18%):</span>
                        <span>S/ ${tax.toFixed(2)}</span>
                    </div>` : ''}
                    <div class="total-row grand-total">
                        <span>Total:</span>
                        <span>S/ ${total.toFixed(2)}</span>
                    </div>
                    ${saleData.payment_method === 'cash' && saleData.cash_received ? `
                    <div class="total-row">
                        <span>Recibido:</span>
                        <span>S/ ${parseFloat(saleData.cash_received).toFixed(2)}</span>
                    </div>
                    <div class="total-row change-amount">
                        <span>Vuelto:</span>
                        <span>S/ ${(saleData.change_amount || 0).toFixed(2)}</span>
                    </div>` : ''}
                </div>
            </div>
            
            <div class="transaction-actions">
                <button type="button" class="btn btn-outline" onclick="closeTransactionModal()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
                <button type="button" class="btn btn-primary" onclick="printReceipt(${saleData.id || ''})">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button type="button" class="btn btn-success" onclick="newTransaction()">
                    <i class="fas fa-plus"></i> Nueva Venta
                </button>
            </div>
        </div>
    `;
    
    // Mostrar el modal
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
    console.log('Abriendo modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        // Asegurarse de que el modal sea visible
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        modal.style.visibility = 'visible';
        
        // Forzar un reflow para que la animación funcione
        void modal.offsetWidth;
        
        // Agregar clase para animación
        setTimeout(() => {
            modal.style.opacity = '1';
            modal.classList.add('show');
        }, 10);
        
        // Prevenir scroll del body
        document.body.style.overflow = 'hidden';
        
        // Enfocar el primer elemento interactivo si existe
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

function printReceipt(saleId = null) {
    console.log('Preparando recibo para impresión...', { saleId });
    
    // Obtener los datos de la venta actual o usar los datos del modal
    let saleData = null;
    const transactionDetails = document.getElementById('transactionDetails');
    
    if (transactionDetails && transactionDetails.innerHTML) {
        // Si hay un modal de transacción abierto, usamos esos datos
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = transactionDetails.innerHTML;
        
        // Extraer datos del DOM (esto es un ejemplo básico, podrías necesitar ajustarlo)
        const saleNumber = tempDiv.querySelector('.sale-number')?.textContent || 'N/A';
        const dateElement = tempDiv.querySelector('.transaction-info p:first-child');
        const date = dateElement ? dateElement.textContent.replace('Fecha:', '').trim() : new Date().toLocaleString();
        
        // Crear un objeto con los datos básicos
        saleData = {
            id: saleId,
            sale_number: saleNumber.replace('#', ''),
            created_at: date,
            items: [],
            subtotal: 0,
            tax: 0,
            total: 0,
            payment_method: 'cash',
            cash_received: 0,
            change_amount: 0
        };
        
        // Extraer totales
        const totalElements = tempDiv.querySelectorAll('.total-row');
        totalElements.forEach(row => {
            const label = row.querySelector('span:first-child')?.textContent?.trim();
            const value = row.querySelector('span:last-child')?.textContent?.replace('S/', '').trim() || '0';
            
            if (label && value) {
                if (label.includes('Subtotal')) saleData.subtotal = parseFloat(value);
                else if (label.includes('IGV')) saleData.tax = parseFloat(value);
                else if (label.includes('Total')) saleData.total = parseFloat(value);
                else if (label.includes('Recibido')) saleData.cash_received = parseFloat(value);
                else if (label.includes('Vuelto')) saleData.change_amount = parseFloat(value);
            }
        });
        
        // Extraer productos
        const items = tempDiv.querySelectorAll('.transaction-item');
        items.forEach(item => {
            const nameElement = item.querySelector('.item-name');
            const priceElement = item.querySelector('.item-price');
            
            if (nameElement && priceElement) {
                const nameText = nameElement.textContent.trim();
                const quantityMatch = nameText.match(/^(\d+)x/);
                const quantity = quantityMatch ? parseInt(quantityMatch[1]) : 1;
                const name = nameText.replace(/^\d+x\s*/, '').trim();
                const price = parseFloat(priceElement.textContent.replace('S/', '').trim()) || 0;
                
                saleData.items.push({
                    name: name,
                    quantity: quantity,
                    price: price / quantity, // Precio unitario
                    subtotal: price
                });
            }
        });
    } else if (saleId) {
        // Aquí podrías hacer una petición al servidor para obtener los datos de la venta
        console.log('Obteniendo datos de la venta desde el servidor...', saleId);
        // Por ahora mostramos un mensaje
        showMessage('No se pudo generar el recibo. Por favor, intente nuevamente.', 'error');
        return;
    } else {
        console.error('No hay datos de venta disponibles para imprimir');
        showMessage('No hay datos de venta disponibles para imprimir', 'error');
        return;
    }
    
    // Crear una ventana emergente con el contenido del recibo
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        showMessage('No se pudo abrir la ventana de impresión. Asegúrese de permitir ventanas emergentes.', 'error');
        return;
    }
    
    // Estilos para el recibo
    const styles = `
        <style>
            @media print {
                body { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.2; }
                .no-print { display: none !important; }
                .receipt { width: 80mm; margin: 0 auto; padding: 10px; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .divider { border-top: 1px dashed #000; margin: 5px 0; }
                .items { margin: 10px 0; }
                .item { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .totals { margin-top: 10px; }
                .total-row { display: flex; justify-content: space-between; margin: 5px 0; }
                .grand-total { font-weight: bold; font-size: 1.1em; margin-top: 10px; }
                .thank-you { text-align: center; margin-top: 15px; font-style: italic; }
            }
        </style>
    `;
    
    // Generar el contenido del recibo
    const receiptContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Recibo de Venta #${saleData.sale_number}</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            ${styles}
        </head>
        <body>
            <div class="receipt">
                <div class="text-center">
                    <h2>${document.querySelector('title')?.textContent || 'Sistema de Ventas'}</h2>
                    <p>${window.location.hostname}</p>
                    <p>${saleData.created_at || new Date().toLocaleString()}</p>
                    <p>Venta #${saleData.sale_number}</p>
                </div>
                
                <div class="divider"></div>
                
                <div class="items">
                    ${saleData.items.map(item => `
                        <div class="item">
                            <span>${item.quantity}x ${item.name}</span>
                            <span>S/ ${item.subtotal.toFixed(2)}</span>
                        </div>
                    `).join('')}
                </div>
                
                <div class="divider"></div>
                
                <div class="totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>S/ ${saleData.subtotal?.toFixed(2) || '0.00'}</span>
                    </div>
                    ${saleData.tax ? `
                    <div class="total-row">
                        <span>IGV (18%):</span>
                        <span>S/ ${saleData.tax.toFixed(2)}</span>
                    </div>` : ''}
                    <div class="total-row grand-total">
                        <span>Total:</span>
                        <span>S/ ${saleData.total?.toFixed(2) || '0.00'}</span>
                    </div>
                    ${saleData.payment_method === 'cash' ? `
                    <div class="total-row">
                        <span>Efectivo:</span>
                        <span>S/ ${saleData.cash_received?.toFixed(2) || '0.00'}</span>
                    </div>
                    <div class="total-row">
                        <span>Vuelto:</span>
                        <span>S/ ${saleData.change_amount?.toFixed(2) || '0.00'}</span>
                    </div>` : ''}
                </div>
                
                <div class="divider"></div>
                
                <div class="thank-you">
                    <p>¡Gracias por su compra!</p>
                    <p>Vuelva pronto</p>
                </div>
                
                <div class="no-print" style="margin-top: 20px; text-align: center;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Imprimir Recibo
                    </button>
                    <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                        Cerrar
                    </button>
                </div>
            </div>
            
            <script>
                // Intentar imprimir automáticamente
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        // Cerrar la ventana después de un tiempo si no se ha cerrado
                        setTimeout(function() {
                            if (!window.closed) {
                                window.close();
                            }
                        }, 5000);
                    }, 500);
                };
            </script>
        </body>
        </html>
    `;
    
    // Escribir el contenido en la ventana
    printWindow.document.open();
    printWindow.document.write(receiptContent);
    printWindow.document.close();
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