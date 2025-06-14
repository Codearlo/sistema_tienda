/**
 * POINT OF SALE (POS) - JavaScript
 * Sistema completo de punto de venta
 */

// Estado global del POS
const POSState = {
    cart: [],
    products: [],
    categories: [],
    customers: [],
    selectedCategory: null,
    currentSale: null,
    paymentMethod: 'cash',
    discount: 0,
    cashReceived: 0
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializePOS();
});

function initializePOS() {
    // Inicializar reloj
    updateClock();
    setInterval(updateClock, 1000);

    // Configurar eventos
    setupPOSEvents();
    
    // Cargar datos iniciales
    loadCategories();
    loadProducts();
    loadCustomers();
    
    // Inicializar carrito vacío
    updateCartDisplay();
    updateTotals();
    
    // Configurar métodos de pago
    setupPaymentMethods();
    
    console.log('POS inicializado correctamente');
}

// ===== EVENT LISTENERS =====
function setupPOSEvents() {
    // Búsqueda de productos
    const searchInput = document.getElementById('productSearch');
    searchInput.addEventListener('input', Utils.debounce(handleProductSearch, 300));
    
    // Limpiar búsqueda
    const clearBtn = document.getElementById('searchClearBtn');
    clearBtn.addEventListener('click', clearProductSearch);
    
    // Descuento
    const discountInput = document.getElementById('discountAmount');
    discountInput.addEventListener('input', handleDiscountChange);
    
    // Monto recibido en efectivo
    const cashInput = document.getElementById('cashReceived');
    cashInput.addEventListener('input', calculateChange);
    
    // Formularios
    document.getElementById('quickCustomerForm').addEventListener('submit', handleQuickCustomer);
    document.getElementById('quickProductForm').addEventListener('submit', handleQuickProduct);
    
    // Teclas rápidas
    document.addEventListener('keydown', handleKeyboardShortcuts);
}

function setupPaymentMethods() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            POSState.paymentMethod = this.value;
            
            // Actualizar labels
            document.querySelectorAll('.payment-method').forEach(label => {
                label.classList.remove('active');
            });
            this.closest('.payment-method').classList.add('active');
            
            // Mostrar/ocultar campos de efectivo
            const cashDetails = document.getElementById('cashDetails');
            if (this.value === 'cash') {
                cashDetails.style.display = 'block';
            } else {
                cashDetails.style.display = 'none';
            }
            
            updateActionButtons();
        });
    });
}

// ===== CARGA DE DATOS =====
async function loadProducts(search = '', categoryId = null) {
    try {
        showProductsLoading(true);
        
        // Simulación de productos
        let products = generateSampleProducts();
        
        // Filtrar por búsqueda
        if (search.trim()) {
            const searchTerm = search.toLowerCase();
            products = products.filter(product => 
                product.name.toLowerCase().includes(searchTerm) ||
                (product.sku && product.sku.toLowerCase().includes(searchTerm)) ||
                (product.barcode && product.barcode.includes(searchTerm))
            );
        }
        
        // Filtrar por categoría
        if (categoryId) {
            products = products.filter(product => product.category_id === categoryId);
        }
        
        POSState.products = products;
        renderProducts();
        
    } catch (error) {
        console.error('Error cargando productos:', error);
        Notifications.error('Error al cargar productos');
    } finally {
        showProductsLoading(false);
    }
}

async function loadCategories() {
    try {
        // Simulación de categorías
        const categories = [
            { id: 1, name: 'Alimentación', color: '#10B981' },
            { id: 2, name: 'Electrónicos', color: '#3B82F6' },
            { id: 3, name: 'Ropa', color: '#8B5CF6' },
            { id: 4, name: 'Hogar', color: '#F59E0B' },
            { id: 5, name: 'Salud', color: '#EF4444' }
        ];
        
        POSState.categories = categories;
        renderCategories();
        
    } catch (error) {
        console.error('Error cargando categorías:', error);
    }
}

async function loadCustomers() {
    try {
        // Simulación de clientes
        const customers = [
            { id: 1, name: 'María García', phone: '999123456', email: 'maria@email.com' },
            { id: 2, name: 'Juan Pérez', phone: '999654321', email: 'juan@email.com' },
            { id: 3, name: 'Ana López', phone: '999789123', email: 'ana@email.com' }
        ];
        
        POSState.customers = customers;
        renderCustomerOptions();
        
    } catch (error) {
        console.error('Error cargando clientes:', error);
    }
}

// ===== RENDERIZADO =====
function renderCategories() {
    const container = document.getElementById('categoriesGrid');
    
    let html = `
        <button class="category-quick ${!POSState.selectedCategory ? 'active' : ''}" 
                onclick="selectCategory(null)">
            Todas
        </button>
    `;
    
    html += POSState.categories.map(category => `
        <button class="category-quick ${POSState.selectedCategory === category.id ? 'active' : ''}" 
                onclick="selectCategory(${category.id})"
                style="border-color: ${category.color};">
            ${category.name}
        </button>
    `).join('');
    
    container.innerHTML = html;
}

function renderProducts() {
    const container = document.getElementById('productsGridPos');
    const emptyState = document.getElementById('emptyState');
    
    if (POSState.products.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    emptyState.classList.add('hidden');
    
    container.innerHTML = POSState.products.map(product => createProductCardPOS(product)).join('');
}

function createProductCardPOS(product) {
    const isOutOfStock = product.stock_quantity <= 0;
    const isLowStock = product.stock_quantity <= product.min_stock && product.stock_quantity > 0;
    
    let stockBadge = '';
    if (isOutOfStock) {
        stockBadge = '<span class="stock-badge out">Agotado</span>';
    } else if (isLowStock) {
        stockBadge = '<span class="stock-badge low">Bajo</span>';
    }
    
    return `
        <div class="product-card-pos ${isOutOfStock ? 'out-of-stock' : ''}" 
             onclick="${isOutOfStock ? '' : `addToCart(${product.id})`}">
            ${stockBadge}
            <div class="product-image-pos">
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` :
                    `<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21,15 16,10 5,21"/>
                    </svg>`
                }
            </div>
            <div class="product-name-pos">${product.name}</div>
            <div class="product-price-pos">${Utils.formatCurrency(product.selling_price)}</div>
            <div class="product-stock-pos">Stock: ${product.stock_quantity}</div>
        </div>
    `;
}

function renderCustomerOptions() {
    const select = document.getElementById('customerSelect');
    
    // Limpiar opciones existentes (excepto la primera)
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    // Agregar clientes
    POSState.customers.forEach(customer => {
        const option = document.createElement('option');
        option.value = customer.id;
        option.textContent = customer.name;
        select.appendChild(option);
    });
}

function updateCartDisplay() {
    const container = document.getElementById('cartItems');
    const emptyState = document.getElementById('cartEmpty');
    const clearBtn = document.getElementById('clearCartBtn');
    
    if (POSState.cart.length === 0) {
        container.innerHTML = '';
        container.appendChild(emptyState);
        clearBtn.disabled = true;
        return;
    }
    
    clearBtn.disabled = false;
    emptyState.remove();
    
    container.innerHTML = POSState.cart.map((item, index) => createCartItem(item, index)).join('');
}

function createCartItem(item, index) {
    const lineTotal = item.quantity * item.price;
    
    return `
        <div class="cart-item" data-index="${index}">
            <div class="cart-item-image">
                ${item.image ? 
                    `<img src="${item.image}" alt="${item.name}">` :
                    `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21,15 16,10 5,21"/>
                    </svg>`
                }
            </div>
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">${Utils.formatCurrency(item.price)} c/u</div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-controls">
                    <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity - 1})">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <input type="number" class="quantity-input" value="${item.quantity}" 
                           min="1" onchange="updateQuantity(${index}, this.value)">
                    <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity + 1})">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                </div>
                <div class="cart-item-total">${Utils.formatCurrency(lineTotal)}</div>
                <button class="remove-item" onclick="removeFromCart(${index})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    `;
}

// ===== GESTIÓN DEL CARRITO =====
function addToCart(productId, quantity = 1) {
    const product = POSState.products.find(p => p.id === productId);
    if (!product || product.stock_quantity <= 0) {
        Notifications.warning('Producto sin stock disponible');
        return;
    }
    
    // Verificar si ya está en el carrito
    const existingIndex = POSState.cart.findIndex(item => item.product_id === productId);
    
    if (existingIndex >= 0) {
        // Actualizar cantidad
        const newQuantity = POSState.cart[existingIndex].quantity + quantity;
        if (newQuantity > product.stock_quantity) {
            Notifications.warning(`Stock insuficiente. Disponible: ${product.stock_quantity}`);
            return;
        }
        POSState.cart[existingIndex].quantity = newQuantity;
    } else {
        // Agregar nuevo item
        if (quantity > product.stock_quantity) {
            Notifications.warning(`Stock insuficiente. Disponible: ${product.stock_quantity}`);
            return;
        }
        
        POSState.cart.push({
            product_id: productId,
            name: product.name,
            price: product.selling_price,
            cost_price: product.cost_price,
            quantity: quantity,
            image: product.image,
            sku: product.sku
        });
    }
    
    updateCartDisplay();
    updateTotals();
    updateActionButtons();
    
    // Feedback visual
    Notifications.success(`${product.name} agregado al carrito`);
}

function updateQuantity(index, newQuantity) {
    newQuantity = parseInt(newQuantity);
    
    if (newQuantity < 1) {
        removeFromCart(index);
        return;
    }
    
    const item = POSState.cart[index];
    const product = POSState.products.find(p => p.id === item.product_id);
    
    if (product && newQuantity > product.stock_quantity) {
        Notifications.warning(`Stock insuficiente. Disponible: ${product.stock_quantity}`);
        return;
    }
    
    POSState.cart[index].quantity = newQuantity;
    updateCartDisplay();
    updateTotals();
}

function removeFromCart(index) {
    POSState.cart.splice(index, 1);
    updateCartDisplay();
    updateTotals();
    updateActionButtons();
}

function clearCart() {
    if (POSState.cart.length === 0) return;
    
    Modal.confirm('¿Estás seguro de que deseas limpiar el carrito?', 'Confirmar')
        .then(confirmed => {
            if (confirmed) {
                POSState.cart = [];
                POSState.discount = 0;
                POSState.cashReceived = 0;
                
                document.getElementById('discountAmount').value = '';
                document.getElementById('cashReceived').value = '';
                
                updateCartDisplay();
                updateTotals();
                updateActionButtons();
                
                Notifications.success('Carrito limpiado');
            }
        });
}

// ===== CÁLCULOS =====
function updateTotals() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
    const discount = POSState.discount;
    const subtotalAfterDiscount = subtotal - discount;
    const tax = subtotalAfterDiscount * 0.18; // IGV 18%
    const total = subtotalAfterDiscount + tax;
    
    // Actualizar DOM
    document.getElementById('subtotalAmount').textContent = Utils.formatCurrency(subtotal);
    document.getElementById('taxAmount').textContent = Utils.formatCurrency(tax);
    document.getElementById('totalAmount').textContent = Utils.formatCurrency(total);
    
    // Calcular cambio si es efectivo
    if (POSState.paymentMethod === 'cash') {
        calculateChange();
    }
}

function calculateChange() {
    if (POSState.paymentMethod !== 'cash') return;
    
    const total = getTotal();
    const received = POSState.cashReceived;
    const change = received - total;
    
    document.getElementById('changeAmount').textContent = Utils.formatCurrency(Math.max(0, change));
    
    updateActionButtons();
}

function getTotal() {
    const subtotal = POSState.cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
    const discount = POSState.discount;
    const subtotalAfterDiscount = subtotal - discount;
    const tax = subtotalAfterDiscount * 0.18;
    return subtotalAfterDiscount + tax;
}

// ===== EVENTOS =====
function handleProductSearch(event) {
    const searchTerm = event.target.value;
    const clearBtn = document.getElementById('searchClearBtn');
    
    if (searchTerm.trim()) {
        clearBtn.classList.remove('hidden');
    } else {
        clearBtn.classList.add('hidden');
    }
    
    loadProducts(searchTerm, POSState.selectedCategory);
}

function clearProductSearch() {
    const searchInput = document.getElementById('productSearch');
    const clearBtn = document.getElementById('searchClearBtn');
    
    searchInput.value = '';
    clearBtn.classList.add('hidden');
    
    loadProducts('', POSState.selectedCategory);
    searchInput.focus();
}

function selectCategory(categoryId) {
    POSState.selectedCategory = categoryId;
    renderCategories();
    
    const searchTerm = document.getElementById('productSearch').value;
    loadProducts(searchTerm, categoryId);
}

function handleDiscountChange(event) {
    POSState.discount = parseFloat(event.target.value) || 0;
    updateTotals();
}

function handleKeyboardShortcuts(event) {
    // Esc - Limpiar búsqueda
    if (event.key === 'Escape') {
        clearProductSearch();
    }
    
    // Enter en búsqueda - Si hay solo un producto, agregarlo
    if (event.key === 'Enter' && event.target.id === 'productSearch') {
        if (POSState.products.length === 1) {
            addToCart(POSState.products[0].id);
        }
    }
    
    // F1 - Nueva venta
    if (event.key === 'F1') {
        event.preventDefault();
        newSale();
    }
    
    // F2 - Procesar venta
    if (event.key === 'F2') {
        event.preventDefault();
        if (!document.getElementById('processSaleBtn').disabled) {
            processSale();
        }
    }
}

// ===== PROCESAMIENTO DE VENTA =====
function updateActionButtons() {
    const holdBtn = document.getElementById('holdSaleBtn');
    const processBtn = document.getElementById('processSaleBtn');
    
    const hasItems = POSState.cart.length > 0;
    const canProcess = hasItems && validatePayment();
    
    holdBtn.disabled = !hasItems;
    processBtn.disabled = !canProcess;
}

function validatePayment() {
    if (POSState.paymentMethod === 'cash') {
        return POSState.cashReceived >= getTotal();
    }
    return true; // Otros métodos no requieren validación adicional
}

async function processSale() {
    if (POSState.cart.length === 0) {
        Notifications.warning('El carrito está vacío');
        return;
    }
    
    if (!validatePayment()) {
        Notifications.warning('Monto recibido insuficiente');
        return;
    }
    
    try {
        // Preparar datos de la venta
        const saleData = {
            items: POSState.cart,
            customer_id: document.getElementById('customerSelect').value || null,
            payment_method: POSState.paymentMethod,
            discount_amount: POSState.discount,
            cash_received: POSState.cashReceived,
            notes: ''
        };
        
        // Simular procesamiento
        Notifications.info('Procesando venta...');
        
        await simulateAPICall('process-sale', saleData);
        
        // Generar recibo
        const saleNumber = generateSaleNumber();
        generateReceipt(saleData, saleNumber);
        
        // Mostrar modal de recibo
        showReceiptModal();
        
        Notifications.success('Venta procesada exitosamente');
        
    } catch (error) {
        console.error('Error procesando venta:', error);
        Notifications.error('Error al procesar la venta');
    }
}

function holdSale() {
    if (POSState.cart.length === 0) return;
    
    Notifications.info('Venta suspendida - Funcionalidad en desarrollo');
    // Aquí implementarías la lógica para guardar la venta suspendida
}

// ===== MODALES =====
function openCustomerModal() {
    const modal = document.getElementById('customerModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus en el primer campo
    setTimeout(() => {
        document.getElementById('customerName').focus();
    }, 100);
}

function closeCustomerModal() {
    const modal = document.getElementById('customerModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    // Limpiar formulario
    document.getElementById('quickCustomerForm').reset();
}

function openQuickProduct() {
    const modal = document.getElementById('quickProductModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        document.getElementById('quickProductName').focus();
    }, 100);
}

function closeQuickProductModal() {
    const modal = document.getElementById('quickProductModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    document.getElementById('quickProductForm').reset();
}

function showReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// ===== FORMULARIOS =====
async function handleQuickCustomer(event) {
    event.preventDefault();
    
    const formData = Forms.serialize(event.target);
    
    try {
        await simulateAPICall('add-customer', formData);
        
        // Agregar a la lista local
        const newCustomer = {
            id: Date.now(), // ID temporal
            name: formData.name,
            phone: formData.phone,
            email: formData.email
        };
        
        POSState.customers.push(newCustomer);
        renderCustomerOptions();
        
        // Seleccionar el nuevo cliente
        document.getElementById('customerSelect').value = newCustomer.id;
        
        closeCustomerModal();
        Notifications.success('Cliente agregado exitosamente');
        
    } catch (error) {
        console.error('Error agregando cliente:', error);
        Notifications.error('Error al agregar cliente');
    }
}

async function handleQuickProduct(event) {
    event.preventDefault();
    
    const formData = Forms.serialize(event.target);
    
    // Validar datos
    if (!formData.name || !formData.price || formData.price <= 0) {
        Notifications.error('Completa todos los campos requeridos');
        return;
    }
    
    // Crear producto temporal
    const quickProduct = {
        id: Date.now(), // ID temporal
        name: formData.name,
        selling_price: parseFloat(formData.price),
        cost_price: 0,
        stock_quantity: 999, // Stock ilimitado para productos rápidos
        min_stock: 0,
        category_id: null,
        image: null,
        sku: null
    };
    
    // Agregar al carrito
    const quantity = parseInt(formData.quantity) || 1;
    addToCart(quickProduct.id, quantity);
    
    // Agregar a productos temporalmente
    POSState.products.unshift(quickProduct);
    
    closeQuickProductModal();
    Notifications.success('Producto agregado al carrito');
}

// ===== RECIBOS =====
function generateReceipt(saleData, saleNumber) {
    const container = document.getElementById('receiptContent');
    const currentDate = new Date();
    
    const subtotal = POSState.cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
    const discount = POSState.discount;
    const subtotalAfterDiscount = subtotal - discount;
    const tax = subtotalAfterDiscount * 0.18;
    const total = subtotalAfterDiscount + tax;
    
    const customer = POSState.customers.find(c => c.id == saleData.customer_id);
    
    container.innerHTML = `
        <div class="receipt-header">
            <div class="receipt-business">MI NEGOCIO</div>
            <div class="receipt-info">
                RUC: 20123456789<br>
                Av. Principal 123, Lima<br>
                Tel: (01) 123-4567
            </div>
        </div>
        
        <div class="receipt-details">
            <div class="receipt-row">
                <span>Recibo #:</span>
                <span>${saleNumber}</span>
            </div>
            <div class="receipt-row">
                <span>Fecha:</span>
                <span>${Utils.formatDate(currentDate, 'DD/MM/YYYY HH:mm')}</span>
            </div>
            <div class="receipt-row">
                <span>Cliente:</span>
                <span>${customer ? customer.name : 'Cliente General'}</span>
            </div>
            <div class="receipt-row">
                <span>Cajero:</span>
                <span>Administrador</span>
            </div>
        </div>
        
        <div class="receipt-items">
            ${POSState.cart.map(item => `
                <div class="receipt-item">
                    <div class="receipt-item-name">${item.name}</div>
                    <div class="receipt-item-details">
                        <span>${item.quantity} x ${Utils.formatCurrency(item.price)}</span>
                        <span>${Utils.formatCurrency(item.quantity * item.price)}</span>
                    </div>
                </div>
            `).join('')}
        </div>
        
        <div class="receipt-totals">
            <div class="receipt-row">
                <span>Subtotal:</span>
                <span>${Utils.formatCurrency(subtotal)}</span>
            </div>
            ${discount > 0 ? `
                <div class="receipt-row">
                    <span>Descuento:</span>
                    <span>-${Utils.formatCurrency(discount)}</span>
                </div>
            ` : ''}
            <div class="receipt-row">
                <span>IGV (18%):</span>
                <span>${Utils.formatCurrency(tax)}</span>
            </div>
            <div class="receipt-row receipt-total">
                <span>TOTAL:</span>
                <span>${Utils.formatCurrency(total)}</span>
            </div>
            <div class="receipt-row">
                <span>Método de pago:</span>
                <span>${getPaymentMethodName(POSState.paymentMethod)}</span>
            </div>
            ${POSState.paymentMethod === 'cash' ? `
                <div class="receipt-row">
                    <span>Recibido:</span>
                    <span>${Utils.formatCurrency(POSState.cashReceived)}</span>
                </div>
                <div class="receipt-row">
                    <span>Cambio:</span>
                    <span>${Utils.formatCurrency(POSState.cashReceived - total)}</span>
                </div>
            ` : ''}
        </div>
        
        <div class="receipt-footer">
            ¡Gracias por su compra!<br>
            Conserve este recibo
        </div>
    `;
}

function getPaymentMethodName(method) {
    const methods = {
        'cash': 'Efectivo',
        'card': 'Tarjeta',
        'transfer': 'Transferencia',
        'credit': 'Crédito'
    };
    return methods[method] || method;
}

function printReceipt() {
    const receiptContent = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Recibo de Venta</title>
                <style>
                    body { font-family: monospace; font-size: 12px; margin: 20px; }
                    .receipt { max-width: 300px; margin: 0 auto; }
                    .receipt-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
                    .receipt-total { font-weight: bold; border-top: 1px solid #000; padding-top: 5px; }
                    .receipt-header { text-align: center; margin-bottom: 20px; }
                    .receipt-footer { text-align: center; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="receipt">${receiptContent}</div>
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
}

function sendReceipt() {
    Notifications.info('Funcionalidad de envío por email en desarrollo');
}

function newSale() {
    closeReceiptModal();
    
    // Limpiar carrito y resetear estado
    POSState.cart = [];
    POSState.discount = 0;
    POSState.cashReceived = 0;
    POSState.paymentMethod = 'cash';
    
    // Resetear formularios
    document.getElementById('discountAmount').value = '';
    document.getElementById('cashReceived').value = '';
    document.getElementById('customerSelect').value = '';
    document.querySelector('input[name="payment_method"][value="cash"]').checked = true;
    
    // Actualizar displays
    updateCartDisplay();
    updateTotals();
    updateActionButtons();
    setupPaymentMethods();
    
    // Focus en búsqueda
    document.getElementById('productSearch').focus();
    
    Notifications.success('Nueva venta iniciada');
}

// ===== UTILIDADES =====
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-PE', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    });
    const dateString = now.toLocaleDateString('es-PE', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.innerHTML = `${dateString}<br>${timeString}`;
    }
}

function showProductsLoading(show) {
    const loading = document.getElementById('productsLoading');
    const grid = document.getElementById('productsGridPos');
    
    if (show) {
        loading.classList.remove('hidden');
        grid.classList.add('hidden');
    } else {
        loading.classList.add('hidden');
        grid.classList.remove('hidden');
    }
}

function generateSaleNumber() {
    const now = new Date();
    const dateString = now.toISOString().slice(0, 10).replace(/-/g, '');
    const timeString = now.toTimeString().slice(0, 8).replace(/:/g, '');
    return `${dateString}-${timeString}`;
}

function openCalculator() {
    // Abrir calculadora del sistema o mostrar calculadora web
    Notifications.info('Abriendo calculadora del sistema...');
}

async function simulateAPICall(endpoint, data) {
    // Simular delay
    await new Promise(resolve => setTimeout(resolve, 500 + Math.random() * 1000));
    return { success: true, data };
}

function generateSampleProducts() {
    return [
        {
            id: 1,
            name: 'Coca Cola 500ml',
            sku: 'COC-500-001',
            category_id: 1,
            selling_price: 4.00,
            cost_price: 2.50,
            stock_quantity: 50,
            min_stock: 10,
            image: null
        },
        {
            id: 2,
            name: 'Cable USB-C',
            sku: 'ELE-USB-001',
            category_id: 2,
            selling_price: 25.00,
            cost_price: 15.00,
            stock_quantity: 2,
            min_stock: 5,
            image: null
        },
        {
            id: 3,
            name: 'Camiseta Básica',
            sku: 'ROP-CAM-001',
            category_id: 3,
            selling_price: 45.00,
            cost_price: 25.00,
            stock_quantity: 0,
            min_stock: 5,
            image: null
        },
        {
            id: 4,
            name: 'Pan Integral',
            sku: 'ALM-PAN-001',
            category_id: 1,
            selling_price: 2.50,
            cost_price: 1.20,
            stock_quantity: 15,
            min_stock: 10,
            image: null
        },
        {
            id: 5,
            name: 'Smartphone Android',
            sku: 'ELE-PHO-001',
            category_id: 2,
            selling_price: 899.00,
            cost_price: 650.00,
            stock_quantity: 8,
            min_stock: 3,
            image: null
        },
        {
            id: 6,
            name: 'Detergente 1kg',
            sku: 'HOG-DET-001',
            category_id: 4,
            selling_price: 12.50,
            cost_price: 8.00,
            stock_quantity: 25,
            min_stock: 10,
            image: null
        }
    ];
}

// Exportar funciones globales
window.addToCart = addToCart;
window.updateQuantity = updateQuantity;
window.removeFromCart = removeFromCart;
window.clearCart = clearCart;
window.selectCategory = selectCategory;
window.clearProductSearch = clearProductSearch;
window.processSale = processSale;
window.holdSale = holdSale;
window.openCustomerModal = openCustomerModal;
window.closeCustomerModal = closeCustomerModal;
window.openQuickProduct = openQuickProduct;
window.closeQuickProductModal = closeQuickProductModal;
window.closeReceiptModal = closeReceiptModal;
window.printReceipt = printReceipt;
window.sendReceipt = sendReceipt;
window.newSale = newSale;
window.openCalculator = openCalculator;