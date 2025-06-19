/**
 * Lógica principal de la interfaz de Punto de Venta (POS).
 * Archivo: assets/js/pos.js
 */

document.addEventListener('DOMContentLoaded', async () => {
    console.log('Inicializando POS...');

    // Elementos del DOM
    const productGrid = document.getElementById('product-grid');
    const productList = document.getElementById('product-list');
    const searchInput = document.getElementById('product-search');
    const categoryFilter = document.getElementById('category-filter');
    const viewToggle = document.getElementById('view-toggle');
    const cartItemsContainer = document.getElementById('cart-items');
    const subtotalDisplay = document.getElementById('subtotal-display');
    const taxDisplay = document.getElementById('tax-display');
    const totalDisplay = document.getElementById('total-display');
    const completeSaleButton = document.getElementById('complete-sale-btn');
    const paymentModal = new Modal('payment-modal'); // Instanciar el modal de pago
    const cashReceivedInput = document.getElementById('cash-received');
    const changeDueDisplay = document.getElementById('change-due');
    const paymentMethodSelect = document.getElementById('payment-method');
    const confirmPaymentButton = document.getElementById('confirm-payment-btn');
    const newCustomerModal = new Modal('new-customer-modal');
    const newCustomerForm = document.getElementById('new-customer-form');
    const customerSelect = document.getElementById('customer-select');
    const quickActionsContainer = document.getElementById('quick-actions-container');
    const receiptModal = new Modal('receipt-modal');
    const receiptContent = document.getElementById('receipt-content');
    const printReceiptBtn = document.getElementById('print-receipt-btn');
    const clearCartButton = document.getElementById('clear-cart-btn');

    let products = []; // Almacena todos los productos cargados
    let filteredProducts = []; // Productos actualmente mostrados en la interfaz
    let cart = []; // Carrito de compras
    let currentSale = {}; // Objeto para almacenar los datos de la venta actual

    // Constantes
    const TAX_RATE = 0.18; // 18% IGV

    // Inicialización
    await loadProducts();
    await loadCategories();
    await loadCustomers();
    renderProducts();
    updateCartDisplay();
    setupEventListeners();
    setupQuickActions();

    console.log('POS inicializado correctamente');

    // ===== CARGA DE DATOS =====

    async function loadProducts() {
        try {
            const response = await API.getProducts();
            if (response.success) {
                products = response.data;
                filteredProducts = [...products]; // Inicialmente, todos los productos están filtrados
            } else {
                App.showToast('Error al cargar productos: ' + (response.message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            App.showToast('Error de conexión al cargar productos.', 'error');
            console.error('Error loading products:', error);
        }
    }

    async function loadCategories() {
        try {
            const response = await API.getCategories();
            if (response.success) {
                // Limpiar select antes de añadir opciones
                categoryFilter.innerHTML = '<option value="">Todas las categorías</option>';
                response.data.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    categoryFilter.appendChild(option);
                });
            } else {
                App.showToast('Error al cargar categorías: ' + (response.message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            App.showToast('Error de conexión al cargar categorías.', 'error');
            console.error('Error loading categories:', error);
        }
    }

    async function loadCustomers() {
        try {
            const response = await API.getCustomers();
            if (response.success) {
                customerSelect.innerHTML = '<option value="general">Cliente General</option>'; // Opción por defecto
                response.data.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.id;
                    option.textContent = `${customer.first_name} ${customer.last_name} (${customer.document_number})`;
                    customerSelect.appendChild(option);
                });
            } else {
                App.showToast('Error al cargar clientes: ' + (response.message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            App.showToast('Error de conexión al cargar clientes.', 'error');
            console.error('Error loading customers:', error);
        }
    }

    // ===== RENDERIZADO DE PRODUCTOS =====

    function renderProducts() {
        productGrid.innerHTML = '';
        productList.innerHTML = '';

        if (filteredProducts.length === 0) {
            productGrid.innerHTML = '<p class="text-center">No se encontraron productos.</p>';
            productList.innerHTML = '<p class="text-center">No se encontraron productos.</p>';
            return;
        }

        // Renderizado en formato de cuadrícula (cards)
        filteredProducts.forEach(product => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.dataset.id = product.id;
            card.innerHTML = `
                <div class="product-image-container">
                    <img src="${product.image_url || 'assets/img/default-product.png'}" alt="${product.name}" class="product-image">
                </div>
                <div class="product-info">
                    <h3 class="product-name">${product.name}</h3>
                    <p class="product-category">${product.category_name}</p>
                    <p class="product-price">S/ ${parseFloat(product.sale_price).toFixed(2)}</p>
                    <p class="product-stock">Stock: ${product.stock}</p>
                </div>
            `;
            card.addEventListener('click', () => addProductToCart(product));
            productGrid.appendChild(card);
        });

        // Renderizado en formato de lista (tabla)
        const table = document.createElement('table');
        table.className = 'data-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        const tbody = table.querySelector('tbody');

        filteredProducts.forEach(product => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${product.name}</td>
                <td>${product.category_name}</td>
                <td>S/ ${parseFloat(product.sale_price).toFixed(2)}</td>
                <td>${product.stock}</td>
                <td class="text-right">
                    <button class="btn btn-primary btn-sm add-to-cart-list-btn" data-id="${product.id}">
                        <i class="fas fa-plus"></i> Añadir
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        productList.appendChild(table);

        // Adjuntar event listeners a los botones de añadir en la vista de lista
        productList.querySelectorAll('.add-to-cart-list-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const productId = e.currentTarget.dataset.id;
                const productToAdd = products.find(p => p.id == productId);
                if (productToAdd) {
                    addProductToCart(productToAdd);
                }
            });
        });

        updateView(); // Asegurar que la vista correcta esté activa
    }

    function updateView() {
        if (viewToggle.value === 'grid') {
            productGrid.classList.remove('hidden');
            productList.classList.add('hidden');
        } else {
            productGrid.classList.add('hidden');
            productList.classList.remove('hidden');
        }
    }

    // ===== LÓGICA DEL CARRITO =====

    function addProductToCart(product) {
        // Verificar stock
        if (product.stock <= 0) {
            App.showToast('Producto agotado.', 'warning');
            return;
        }

        const existingItem = cart.find(item => item.id === product.id);

        if (existingItem) {
            if (existingItem.quantity < product.stock) {
                existingItem.quantity++;
                App.showToast(`Se añadió una unidad de ${product.name}`, 'info');
            } else {
                App.showToast(`No hay suficiente stock de ${product.name}. Stock máximo alcanzado.`, 'warning');
                return;
            }
        } else {
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.sale_price),
                quantity: 1,
                stock: product.stock // Guardar el stock actual para validación
            });
            App.showToast(`${product.name} añadido al carrito`, 'success');
        }
        updateCartDisplay();
    }

    function removeProductFromCart(productId) {
        const index = cart.findIndex(item => item.id === productId);
        if (index > -1) {
            cart.splice(index, 1);
            App.showToast('Producto eliminado del carrito', 'info');
            updateCartDisplay();
        }
    }

    function updateCartItemQuantity(productId, newQuantity) {
        const item = cart.find(i => i.id === productId);
        const product = products.find(p => p.id === productId); // Obtener el producto original para el stock

        if (item && product) {
            newQuantity = parseInt(newQuantity);
            if (isNaN(newQuantity) || newQuantity <= 0) {
                removeProductFromCart(productId);
                return;
            }
            if (newQuantity > product.stock) {
                newQuantity = product.stock; // Ajustar a stock máximo disponible
                App.showToast(`Solo quedan ${product.stock} unidades de ${product.name}. Cantidad ajustada.`, 'warning');
            }
            item.quantity = newQuantity;
            updateCartDisplay();
        }
    }

    function calculateCartTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.price * item.quantity;
        });

        const tax = subtotal * TAX_RATE;
        const total = subtotal + tax;

        return { subtotal, tax, total };
    }

    function updateCartDisplay() {
        cartItemsContainer.innerHTML = '';
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<p class="text-center text-gray-500">El carrito está vacío.</p>';
            completeSaleButton.disabled = true;
            clearCartButton.disabled = true;
        } else {
            cart.forEach(item => {
                const cartItemElement = document.createElement('div');
                cartItemElement.className = 'cart-item';
                cartItemElement.innerHTML = `
                    <div class="cart-item-details">
                        <span class="cart-item-name">${item.name}</span>
                        <span class="cart-item-price">S/ ${item.price.toFixed(2)}</span>
                    </div>
                    <div class="cart-item-actions">
                        <input type="number" min="1" value="${item.quantity}" class="cart-item-quantity-input" data-id="${item.id}">
                        <span class="cart-item-subtotal">S/ ${(item.price * item.quantity).toFixed(2)}</span>
                        <button class="btn btn-danger btn-sm remove-cart-item-btn" data-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                cartItemsContainer.appendChild(cartItemElement);
            });

            // Actualizar inputs de cantidad
            cartItemsContainer.querySelectorAll('.cart-item-quantity-input').forEach(input => {
                input.addEventListener('change', (e) => {
                    updateCartItemQuantity(parseInt(e.target.dataset.id), parseInt(e.target.value));
                });
            });

            // Actualizar botones de remover
            cartItemsContainer.querySelectorAll('.remove-cart-item-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    removeProductFromCart(parseInt(e.target.dataset.id));
                });
            });

            completeSaleButton.disabled = false;
            clearCartButton.disabled = false;
        }

        const { subtotal, tax, total } = calculateCartTotals();
        subtotalDisplay.textContent = subtotal.toFixed(2);
        taxDisplay.textContent = tax.toFixed(2);
        totalDisplay.textContent = total.toFixed(2);

        // Resetear cálculo de cambio si el carrito cambia
        resetPaymentCalculations();
    }

    function clearCart() {
        cart = [];
        updateCartDisplay();
        App.showToast('Carrito vaciado.', 'info');
    }

    // ===== PROCESO DE VENTA =====

    function openPaymentModal() {
        if (cart.length === 0) {
            App.showToast('El carrito está vacío.', 'warning');
            return;
        }
        paymentModal.open();
        cashReceivedInput.value = ''; // Limpiar input
        paymentMethodSelect.value = 'cash'; // Seleccionar efectivo por defecto
        resetPaymentCalculations();
        cashReceivedInput.focus();
    }

    function resetPaymentCalculations() {
        changeDueDisplay.textContent = '0.00';
        confirmPaymentButton.disabled = false; // Habilitar por defecto al abrir
    }

    function calculateChange() {
        const total = parseFloat(totalDisplay.textContent);
        const cashReceived = parseFloat(cashReceivedInput.value) || 0;
        const change = cashReceived - total;
        changeDueDisplay.textContent = change.toFixed(2);

        // Habilitar/deshabilitar botón de confirmar según el método de pago y el monto
        if (paymentMethodSelect.value === 'cash') {
            confirmPaymentButton.disabled = cashReceived < total;
        } else {
            confirmPaymentButton.disabled = false; // Para tarjeta/transferencia, solo se habilita
        }
    }

    async function completeTransaction() {
        App.showLoading('Completando venta...');
        const { subtotal, tax, total } = calculateCartTotals();

        const paymentMethod = paymentMethodSelect.value;
        const cashReceived = parseFloat(cashReceivedInput.value) || 0;
        const customerId = customerSelect.value === 'general' ? null : parseInt(customerSelect.value);

        // Validaciones previas
        if (cart.length === 0) {
            App.showToast('El carrito está vacío.', 'warning');
            App.hideLoading();
            return;
        }
        if (paymentMethod === 'cash' && cashReceived < total) {
            App.showToast('El monto recibido es insuficiente.', 'error');
            App.hideLoading();
            return;
        }

        currentSale = {
            customer_id: customerId,
            items: cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                price_at_sale: item.price // Precio al momento de la venta
            })),
            subtotal: subtotal,
            tax_amount: tax,
            total_amount: total,
            payment_method: paymentMethod,
            cash_received: paymentMethod === 'cash' ? cashReceived : total, // Si no es efectivo, el recibido es el total
            change_given: paymentMethod === 'cash' ? (cashReceived - total) : 0,
            sale_date: new Date().toISOString().slice(0, 19).replace('T', ' ') // Formato MySQL datetime
        };

        try {
            const response = await API.createSale(currentSale);

            if (response.success) {
                App.showToast('Venta completada exitosamente!', 'success');
                paymentModal.close();
                clearCart();
                renderReceipt(response.data); // Asumiendo que la API devuelve los datos de la venta confirmada
                receiptModal.open();
                await loadProducts(); // Recargar productos para reflejar el stock actualizado
            } else {
                App.showToast('Error al completar la venta: ' + (response.message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            App.showToast('Error de conexión al completar la venta.', 'error');
            console.error('Error completing transaction:', error);
        } finally {
            App.hideLoading();
        }
    }

    function renderReceipt(saleData) {
        receiptContent.innerHTML = ''; // Limpiar contenido anterior

        if (!saleData) {
            receiptContent.innerHTML = '<p class="text-center text-red-500">No se pudo generar el recibo. Datos de venta no disponibles.</p>';
            return;
        }

        const itemsHtml = saleData.items.map(item => `
            <div class="receipt-item">
                <span>${item.quantity} x ${item.name}</span>
                <span>S/ ${(item.price_at_sale * item.quantity).toFixed(2)}</span>
            </div>
        `).join('');

        receiptContent.innerHTML = `
            <div class="text-center mb-4">
                <h2 class="text-xl font-bold">${saleData.business_name || 'Tu Negocio'}</h2>
                <p class="text-sm">${saleData.business_address || 'Dirección no disponible'}</p>
                <p class="text-sm">${saleData.business_phone || 'Teléfono no disponible'}</p>
                <p class="text-sm">${saleData.business_ruc ? 'RUC: ' + saleData.business_ruc : ''}</p>
            </div>
            <div class="receipt-section">
                <p><strong>Fecha:</strong> ${new Date(saleData.sale_date).toLocaleString()}</p>
                <p><strong>Venta #ID:</strong> ${saleData.id}</p>
                <p><strong>Cajero:</strong> ${saleData.cashier_name || 'N/A'}</p>
                <p><strong>Cliente:</strong> ${saleData.customer_name || 'Cliente General'}</p>
            </div>
            <div class="receipt-section items">
                <h3 class="font-bold border-b border-gray-600 pb-1 mb-2">Detalle de Venta:</h3>
                ${itemsHtml}
            </div>
            <div class="receipt-section totals">
                <div class="flex justify-between"><span>Subtotal:</span><span>S/ ${saleData.subtotal.toFixed(2)}</span></div>
                <div class="flex justify-between"><span>IGV (${(TAX_RATE * 100).toFixed(0)}%):</span><span>S/ ${saleData.tax_amount.toFixed(2)}</span></div>
                <div class="flex justify-between text-lg font-bold"><span>Total:</span><span>S/ ${saleData.total_amount.toFixed(2)}</span></div>
            </div>
            <div class="receipt-section payment-info">
                <div class="flex justify-between"><span>Método de Pago:</span><span>${saleData.payment_method.charAt(0).toUpperCase() + saleData.payment_method.slice(1)}</span></div>
                ${saleData.payment_method === 'cash' ? `
                    <div class="flex justify-between"><span>Efectivo Recibido:</span><span>S/ ${saleData.cash_received.toFixed(2)}</span></div>
                    <div class="flex justify-between text-lg font-bold"><span>Cambio:</span><span>S/ ${saleData.change_given.toFixed(2)}</span></div>
                ` : ''}
            </div>
            <div class="text-center mt-4 text-sm">
                <p>¡Gracias por tu compra!</p>
            </div>
        `;
    }

    function printReceipt() {
        const printContent = receiptContent.innerHTML;
        const originalBody = document.body.innerHTML;
        
        // Crear una nueva ventana para imprimir
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Recibo de Venta</title>');
        // Incluir los estilos CSS del recibo para que se imprima correctamente
        printWindow.document.write('<link rel="stylesheet" href="assets/css/style.css">'); // Estilos generales
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Inter', sans-serif; margin: 20px; color: #333; }
            .receipt-section { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #ccc; }
            .receipt-section.items .receipt-item { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .receipt-section.totals div, .receipt-section.payment-info div { display: flex; justify-content: space-between; margin-bottom: 5px; }
            .text-center { text-align: center; }
            .mb-4 { margin-bottom: 1rem; }
            .mt-4 { margin-top: 1rem; }
            .text-xl { font-size: 1.25rem; }
            .text-lg { font-size: 1.125rem; }
            .text-sm { font-size: 0.875rem; }
            .font-bold { font-weight: 700; }
            .flex { display: flex; }
            .justify-between { justify-content: space-between; }
            .pb-1 { padding-bottom: 0.25rem; }
            .mb-2 { margin-bottom: 0.5rem; }
            .border-b { border-bottom-width: 1px; }
            .border-gray-600 { border-color: #4B5563; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContent);
        printWindow.document.close(); // Cierra el documento que se está escribiendo
        printWindow.focus(); // Enfoca la nueva ventana
        printWindow.print(); // Abre el diálogo de impresión
        // No cerramos la ventana automáticamente para que el usuario pueda interactuar con el diálogo de impresión
    }

    // ===== EVENT LISTENERS =====

    function setupEventListeners() {
        searchInput.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        viewToggle.addEventListener('change', updateView);
        completeSaleButton.addEventListener('click', openPaymentModal);
        cashReceivedInput.addEventListener('input', calculateChange);
        paymentMethodSelect.addEventListener('change', calculateChange); // Recalcular al cambiar método
        confirmPaymentButton.addEventListener('click', completeTransaction);
        clearCartButton.addEventListener('click', clearCart);
        printReceiptBtn.addEventListener('click', printReceipt);

        // Listener para añadir nuevo cliente desde el modal
        newCustomerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            App.showLoading('Creando cliente...');
            const formData = App.serializeForm(newCustomerForm);
            try {
                const response = await API.createCustomer(formData);
                if (response.success) {
                    App.showToast('Cliente creado exitosamente!', 'success');
                    newCustomerModal.close();
                    App.clearForm(newCustomerForm);
                    await loadCustomers(); // Recargar la lista de clientes
                    // Seleccionar el nuevo cliente si es necesario
                    if (response.data && response.data.id) {
                        customerSelect.value = response.data.id;
                    }
                } else {
                    App.showToast('Error al crear cliente: ' + (response.message || 'Error desconocido'), 'error');
                }
            } catch (error) {
                App.showToast('Error de conexión al crear cliente.', 'error');
                console.error('Error creating customer:', error);
            } finally {
                App.hideLoading();
            }
        });

        // Abrir modal de nuevo cliente
        document.getElementById('add-new-customer-btn').addEventListener('click', () => {
            newCustomerModal.open();
        });
    }

    // ===== FILTROS Y BÚSQUEDA =====

    function filterProducts() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;

        filteredProducts = products.filter(product => {
            const matchesSearch = product.name.toLowerCase().includes(searchTerm) ||
                                  (product.sku && product.sku.toLowerCase().includes(searchTerm)) ||
                                  (product.barcode && product.barcode.toLowerCase().includes(searchTerm));
            const matchesCategory = selectedCategory === '' || product.category_id == selectedCategory;

            return matchesSearch && matchesCategory;
        });

        renderProducts();
    }

    // ===== ACCIONES RÁPIDAS =====
    // Los productos de acciones rápidas deben cargarse dinámicamente o definirse estáticamente
    // Aquí se define estáticamente a modo de ejemplo, pero podría venir de una configuración.
    function setupQuickActions() {
        // Ejemplo: cargar los 5 productos más vendidos o más recientes como acciones rápidas
        // Por ahora, solo es un placeholder visual
        // const quickProductIds = [1, 2, 3, 4, 5]; // IDs de productos para acciones rápidas
        // const quickProducts = products.filter(p => quickProductIds.includes(p.id));
        
        // Simplemente tomo los primeros 5 productos disponibles para acciones rápidas de demostración
        const quickProducts = products.slice(0, 5); // Tomar los primeros 5 productos

        if (quickProducts.length > 0) {
            quickActionsContainer.innerHTML = ''; // Limpiar cualquier contenido previo
            quickProducts.forEach(product => {
                const actionButton = document.createElement('button');
                actionButton.className = 'btn btn-secondary btn-sm quick-action-btn';
                actionButton.textContent = product.name;
                actionButton.dataset.id = product.id;
                actionButton.addEventListener('click', () => addProductToCart(product));
                quickActionsContainer.appendChild(actionButton);
            });
        } else {
            quickActionsContainer.innerHTML = '<p class="text-center text-gray-500">No hay acciones rápidas disponibles.</p>';
        }
    }

    // Exponer algunas funciones al ámbito global para depuración si es necesario
    window.pos = {
        cart,
        products,
        filteredProducts,
        addProductToCart,
        removeProductFromCart,
        updateCartItemQuantity,
        calculateCartTotals,
        updateCartDisplay,
        openPaymentModal,
        completeTransaction,
        renderProducts,
        filterProducts,
        clearCart
    };
});
