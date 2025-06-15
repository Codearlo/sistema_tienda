<?php
/**
 * PUNTO DE VENTA - TREINTA APP
 * Sistema completo de ventas con carrito
 */

session_start();

// ===== VERIFICACIÓN DE AUTENTICACIÓN =====
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once __DIR__ . '/cache_control.php';



// ===== CONFIGURACIÓN DE BASE DE DATOS =====
$host = 'localhost';
$db_name = 'u347334547_inv_db';
$username = 'u347334547_inv_user';
$db_password = 'CH7322a#';

// ===== VARIABLES INICIALES =====
$error_message = null;
$categories = [];
$customers = [];
$products = [];

// ===== CONEXIÓN Y CARGA DE DATOS =====
try {
    // Conexión PDO
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $business_id = $_SESSION['business_id'];
    
    // Cargar categorías
    $categories_query = $pdo->prepare("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name
    ");
    $categories_query->execute([$business_id]);
    $categories = $categories_query->fetchAll();
    
    // Cargar clientes
    $customers_query = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name 
        FROM customers 
        WHERE business_id = ? AND status = 1 
        ORDER BY first_name
    ");
    $customers_query->execute([$business_id]);
    $customers = $customers_query->fetchAll();
    
    // Cargar productos
    $products_query = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.business_id = ? AND p.status = 1 
        ORDER BY p.name ASC
    ");
    $products_query->execute([$business_id]);
    $products = $products_query->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pos-page">
    
    <!-- ===== SIDEBAR ===== -->
    <?php include 'includes/slidebar.php'; ?>

    <!-- ===== CONTENEDOR PRINCIPAL ===== -->
    <div class="pos-container">
        
        <!-- ===== HEADER ===== -->
        <header class="pos-header">
            <div class="pos-title">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <svg class="pos-logo" viewBox="0 0 100 100" width="40" height="40">
                    <circle cx="50" cy="50" r="45" fill="#2563eb"/>
                    <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
                </svg>
                <h1>Punto de Venta</h1>
            </div>
            <div class="pos-header-actions">
                <button class="btn btn-gray" onclick="window.location.href='dashboard.php'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                    Dashboard
                </button>
                <div class="user-info">
                    <span>Cajero: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </div>
        </header>

        <!-- ===== ALERTA DE ERROR ===== -->
        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- ===== CONTENIDO PRINCIPAL ===== -->
        <div class="pos-main">
            
            <!-- ===== PANEL IZQUIERDO - PRODUCTOS ===== -->
            <div class="pos-left">
                
                <!-- Sección de búsqueda -->
                <div class="product-search-section">
                    <div class="search-input-group">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" id="productSearch" class="search-input" 
                               placeholder="Buscar producto por nombre, SKU o código de barras..." autofocus>
                        <button class="search-clear-btn hidden" id="searchClearBtn" onclick="clearSearch()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Sección de categorías -->
                <div class="categories-quick">
                    <h3>Categorías</h3>
                    <div class="categories-grid">
                        <button class="category-quick active" onclick="filterByCategory(null)">Todas</button>
                        <?php foreach ($categories as $category): ?>
                            <button class="category-quick" onclick="filterByCategory(<?php echo $category['id']; ?>)" 
                                    style="border-color: <?php echo htmlspecialchars($category['color'] ?? '#3B82F6'); ?>;">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Lista de productos -->
                <div class="products-list-pos">
                    <?php if (empty($products)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <h3>No hay productos disponibles</h3>
                            <p>Agrega productos desde la sección de inventario</p>
                        </div>
                    <?php else: ?>
                        <div class="products-grid-pos" id="productsGridPos">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card-pos <?php echo $product['stock_quantity'] <= 0 ? 'out-of-stock' : ''; ?>" 
                                     data-product-id="<?php echo $product['id']; ?>"
                                     data-category="<?php echo $product['category_id'] ?? 0; ?>"
                                     data-name="<?php echo strtolower($product['name']); ?>"
                                     data-sku="<?php echo strtolower($product['sku'] ?? ''); ?>"
                                     onclick="<?php echo $product['stock_quantity'] > 0 ? 'addToCart(' . $product['id'] . ')' : ''; ?>">
                                    
                                    <!-- Badge de stock -->
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                        <span class="stock-badge out">Agotado</span>
                                    <?php elseif ($product['stock_quantity'] <= $product['min_stock']): ?>
                                        <span class="stock-badge low">Bajo</span>
                                    <?php endif; ?>
                                    
                                    <!-- Imagen del producto -->
                                    <div class="product-image-pos">
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <polyline points="21,15 16,10 5,21"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Información del producto -->
                                    <div class="product-name-pos"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-price-pos">S/ <?php echo number_format($product['selling_price'], 2); ?></div>
                                    <div class="product-stock-pos">Stock: <?php echo $product['stock_quantity']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== PANEL DERECHO - CARRITO Y CHECKOUT ===== -->
            <div class="pos-right">
                
                <!-- Sección de cliente -->
                <div class="customer-section">
                    <h3>Cliente</h3>
                    <div class="customer-select">
                        <select id="customerSelect" class="form-input">
                            <option value="">Cliente General</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-success btn-small" onclick="openCustomerModal()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Sección del carrito -->
                <div class="cart-section">
                    <div class="cart-header">
                        <h3>Carrito de Compras</h3>
                        <button class="btn btn-gray btn-small" onclick="clearCart()" id="clearCartBtn" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3,6 5,6 21,6"/>
                                <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                            </svg>
                            Limpiar
                        </button>
                    </div>
                    
                    <div class="cart-items" id="cartItems">
                        <div class="cart-empty" id="cartEmpty">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <circle cx="9" cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            <p>El carrito está vacío</p>
                            <span>Agrega productos para comenzar la venta</span>
                        </div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="cart-totals">
                    <div class="totals-row">
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">S/ 0.00</span>
                    </div>
                    <div class="totals-row">
                        <span>Descuento:</span>
                        <div class="discount-input">
                            <input type="number" id="discountAmount" class="discount-field" 
                                   placeholder="0.00" min="0" step="0.01" onchange="updateTotals()">
                            <span>S/</span>
                        </div>
                    </div>
                    <div class="totals-row">
                        <span>IGV (18%):</span>
                        <span id="taxAmount">S/ 0.00</span>
                    </div>
                    <div class="totals-row total-row">
                        <span>Total:</span>
                        <span id="totalAmount">S/ 0.00</span>
                    </div>
                </div>

                <!-- Métodos de pago -->
                <div class="payment-section">
                    <h3>Método de Pago</h3>
                    <div class="payment-methods">
                        <label class="payment-method active">
                            <input type="radio" name="payment_method" value="cash" checked onchange="updatePaymentMethod()">
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <span>Efectivo</span>
                            </div>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="card" onchange="updatePaymentMethod()">
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                <span>Tarjeta</span>
                            </div>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="transfer" onchange="updatePaymentMethod()">
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17,8 12,3 7,8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <span>Transferencia</span>
                            </div>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="credit" onchange="updatePaymentMethod()">
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                                <span>Crédito</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Detalles de efectivo -->
                    <div class="cash-details" id="cashDetails">
                        <div class="form-group">
                            <label class="form-label">Monto Recibido</label>
                            <input type="number" id="cashReceived" class="form-input" 
                                   placeholder="0.00" min="0" step="0.01" onchange="calculateChange()">
                        </div>
                        <div class="change-amount">
                            <span>Cambio: <strong id="changeAmount">S/ 0.00</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Acciones del POS -->
                <div class="pos-actions">
                    <button class="btn btn-gray btn-full" onclick="holdSale()" disabled id="holdSaleBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="4" width="4" height="16"/>
                            <rect x="14" y="4" width="4" height="16"/>
                        </svg>
                        Suspender Venta
                    </button>
                    <button class="btn btn-primary btn-full" onclick="processSale()" disabled id="processSaleBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        Procesar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL DE CLIENTE ===== -->
    <div class="modal-overlay" id="customerModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Agregar Cliente</h3>
                <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <div class="form-group">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="Nombre del cliente">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido</label>
                        <input type="text" name="last_name" class="form-input" placeholder="Apellido del cliente">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" name="phone" class="form-input" placeholder="999 999 999">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeCustomerModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Agregar Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        /**
         * ===== VARIABLES GLOBALES =====
         */
        let cart = [];
        let currentPaymentMethod = 'cash';
        const products = <?php echo json_encode($products); ?>;

        /**
         * ===== INICIALIZACIÓN =====
         */
        document.addEventListener('DOMContentLoaded', function() {
            initializePOS();
        });

        function initializePOS() {
            setupEventListeners();
            initializeClock();
            updateTotals();
            updateButtons();
            updatePaymentMethod();
        }

        function setupEventListeners() {
            // Búsqueda de productos
            document.getElementById('productSearch').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                filterProducts(searchTerm);
                toggleClearButton(searchTerm);
            });

            // Formulario de cliente
            document.getElementById('customerForm').addEventListener('submit', handleCustomerSubmit);

            // Cerrar modales al hacer clic fuera
            document.getElementById('customerModal').addEventListener('click', function(e) {
                if (e.target === this) closeCustomerModal();
            });
        }

        /**
         * ===== RELOJ =====
         */
        function initializeClock() {
            updateClock();
            setInterval(updateClock, 1000);
        }

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

        /**
         * ===== BÚSQUEDA Y FILTROS =====
         */
        function filterProducts(searchTerm = '', categoryId = null) {
            const productCards = document.querySelectorAll('.product-card-pos');
            
            productCards.forEach(card => {
                const name = card.dataset.name;
                const sku = card.dataset.sku;
                const category = card.dataset.category;
                
                let showProduct = true;
                
                // Filtro de búsqueda
                if (searchTerm) {
                    showProduct = name.includes(searchTerm) || sku.includes(searchTerm);
                }
                
                // Filtro de categoría
                if (categoryId !== null && showProduct) {
                    showProduct = category == categoryId || (categoryId === 0 && category == '');
                }
                
                card.style.display = showProduct ? 'block' : 'none';
            });
        }

        function filterByCategory(categoryId) {
            // Actualizar botones activos
            document.querySelectorAll('.category-quick').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filtrar productos
            const searchTerm = document.getElementById('productSearch').value.toLowerCase();
            filterProducts(searchTerm, categoryId);
        }

        function clearSearch() {
            document.getElementById('productSearch').value = '';
            document.getElementById('searchClearBtn').classList.add('hidden');
            filterProducts('');
        }

        function toggleClearButton(searchTerm) {
            const clearBtn = document.getElementById('searchClearBtn');
            if (searchTerm) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }

        /**
         * ===== GESTIÓN DEL CARRITO =====
         */
        function addToCart(productId) {
            const product = products.find(p => p.id == productId);
            if (!product || product.stock_quantity <= 0) {
                alert('Producto sin stock disponible');
                return;
            }
            
            const existingIndex = cart.findIndex(item => item.product_id == productId);
            
            if (existingIndex >= 0) {
                cart[existingIndex].quantity += 1;
            } else {
                cart.push({
                    product_id: productId,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: 1
                });
            }
            
            updateCartDisplay();
            updateTotals();
            updateButtons();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const cartEmpty = document.getElementById('cartEmpty');
            const clearBtn = document.getElementById('clearCartBtn');
            
            if (cart.length === 0) {
                cartItems.innerHTML = '';
                cartItems.appendChild(cartEmpty);
                clearBtn.disabled = true;
                return;
            }
            
            clearBtn.disabled = false;
            cartItems.innerHTML = generateCartHTML();
        }

        function generateCartHTML() {
            return cart.map((item, index) => {
                const lineTotal = item.quantity * item.price;
                return `
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21,15 16,10 5,21"/>
                            </svg>
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">S/ ${item.price.toFixed(2)} c/u</div>
                        </div>
                        <div class="cart-item-controls">
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity - 1})">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                                <input type="number" class="quantity-input" value="${item.quantity}" min="1" onchange="updateQuantity(${index}, this.value)">
                                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.quantity + 1})">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="cart-item-total">S/ ${lineTotal.toFixed(2)}</div>
                            <button class="remove-item" onclick="removeFromCart(${index})">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateQuantity(index, newQuantity) {
            newQuantity = parseInt(newQuantity);
            
            if (newQuantity < 1) {
                removeFromCart(index);
                return;
            }
            
            cart[index].quantity = newQuantity;
            updateCartDisplay();
            updateTotals();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
            updateTotals();
            updateButtons();
        }

        function clearCart() {
            if (cart.length === 0) return;
            
            if (confirm('¿Estás seguro de que deseas limpiar el carrito?')) {
                cart = [];
                resetFormValues();
                updateCartDisplay();
                updateTotals();
                updateButtons();
            }
        }

        function resetFormValues() {
            document.getElementById('discountAmount').value = '';
            document.getElementById('cashReceived').value = '';
            document.getElementById('customerSelect').value = '';
            document.querySelector('input[name="payment_method"][value="cash"]').checked = true;
        }

        /**
         * ===== CÁLCULOS Y TOTALES =====
         */
        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
            const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const subtotalAfterDiscount = subtotal - discount;
            const tax = subtotalAfterDiscount * 0.18;
            const total = subtotalAfterDiscount + tax;
            
            // Actualizar DOM
            document.getElementById('subtotalAmount').textContent = `S/ ${subtotal.toFixed(2)}`;
            document.getElementById('taxAmount').textContent = `S/ ${tax.toFixed(2)}`;
            document.getElementById('totalAmount').textContent = `S/ ${total.toFixed(2)}`;
            
            // Calcular cambio si es efectivo
            if (currentPaymentMethod === 'cash') {
                calculateChange();
            }
        }

        function calculateChange() {
            if (currentPaymentMethod !== 'cash') return;
            
            const total = getTotal();
            const received = parseFloat(document.getElementById('cashReceived').value) || 0;
            const change = received - total;
            
            document.getElementById('changeAmount').textContent = `S/ ${Math.max(0, change).toFixed(2)}`;
            updateButtons();
        }

        function getTotal() {
            const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
            const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const subtotalAfterDiscount = subtotal - discount;
            const tax = subtotalAfterDiscount * 0.18;
            return subtotalAfterDiscount + tax;
        }

        /**
         * ===== MÉTODOS DE PAGO =====
         */
        function updatePaymentMethod() {
            const methods = document.querySelectorAll('input[name="payment_method"]');
            const cashDetails = document.getElementById('cashDetails');
            
            methods.forEach(method => {
                const label = method.closest('.payment-method');
                label.classList.remove('active');
                
                if (method.checked) {
                    label.classList.add('active');
                    currentPaymentMethod = method.value;
                }
            });
            
            // Mostrar/ocultar detalles de efectivo
            if (currentPaymentMethod === 'cash') {
                cashDetails.style.display = 'block';
                calculateChange();
            } else {
                cashDetails.style.display = 'none';
            }
            
            updateButtons();
        }

        /**
         * ===== VALIDACIONES Y BOTONES =====
         */
        function updateButtons() {
            const holdBtn = document.getElementById('holdSaleBtn');
            const processBtn = document.getElementById('processSaleBtn');
            
            const hasItems = cart.length > 0;
            let canProcess = hasItems;
            
            // Validar pago en efectivo
            if (hasItems && currentPaymentMethod === 'cash') {
                const total = getTotal();
                const received = parseFloat(document.getElementById('cashReceived').value) || 0;
                canProcess = received >= total;
            }
            
            holdBtn.disabled = !hasItems;
            processBtn.disabled = !canProcess;
        }

        function validateSale() {
            if (cart.length === 0) {
                alert('El carrito está vacío');
                return false;
            }
            
            if (currentPaymentMethod === 'cash') {
                const total = getTotal();
                const received = parseFloat(document.getElementById('cashReceived').value) || 0;
                if (received < total) {
                    alert('Monto recibido insuficiente');
                    return false;
                }
            }
            
            return true;
        }

        /**
         * ===== PROCESAMIENTO DE VENTAS =====
         */
        function processSale() {
            if (!validateSale()) return;
            
            // Simular procesamiento
            const total = getTotal();
            const customer = document.getElementById('customerSelect').selectedOptions[0]?.text || 'Cliente General';
            
            const confirmMessage = `
                Venta procesada exitosamente!
                
                Cliente: ${customer}
                Total: S/ ${total.toFixed(2)}
                Método de pago: ${getPaymentMethodName(currentPaymentMethod)}
                ${currentPaymentMethod === 'cash' ? `\nCambio: S/ ${(parseFloat(document.getElementById('cashReceived').value) - total).toFixed(2)}` : ''}
            `;
            
            alert(confirmMessage);
            resetSale();
        }

        function holdSale() {
            if (cart.length === 0) return;
            alert('Venta suspendida (funcionalidad en desarrollo)');
        }

        function resetSale() {
            cart = [];
            resetFormValues();
            updateCartDisplay();
            updateTotals();
            updateButtons();
            updatePaymentMethod();
            
            // Focus en búsqueda para nueva venta
            document.getElementById('productSearch').focus();
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

        /**
         * ===== GESTIÓN DE CLIENTES =====
         */
        function openCustomerModal() {
            document.getElementById('customerModal').classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focus en primer campo
            setTimeout(() => {
                document.querySelector('#customerModal input').focus();
            }, 100);
        }

        function closeCustomerModal() {
            document.getElementById('customerModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('customerForm').reset();
        }

        function handleCustomerSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const customerData = {
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                phone: formData.get('phone')
            };
            
            // Validar datos mínimos
            if (!customerData.first_name.trim()) {
                alert('El nombre es requerido');
                return;
            }
            
            // Simular agregado de cliente
            const fullName = `${customerData.first_name} ${customerData.last_name || ''}`.trim();
            alert(`Cliente "${fullName}" agregado exitosamente (funcionalidad en desarrollo)`);
            
            closeCustomerModal();
        }

        /**
         * ===== FUNCIONES AUXILIARES =====
         */
        function formatCurrency(amount) {
            return `S/ ${parseFloat(amount).toFixed(2)}`;
        }

        function logDebug(message, data = null) {
            if (window.location.hostname === 'localhost') {
                console.log(`[POS DEBUG] ${message}`, data);
            }
        }

        /**
         * ===== EXPOSICIÓN DE FUNCIONES GLOBALES =====
         */
        window.addToCart = addToCart;
        window.updateQuantity = updateQuantity;
        window.removeFromCart = removeFromCart;
        window.clearCart = clearCart;
        window.filterByCategory = filterByCategory;
        window.clearSearch = clearSearch;
        window.updateTotals = updateTotals;
        window.calculateChange = calculateChange;
        window.updatePaymentMethod = updatePaymentMethod;
        window.processSale = processSale;
        window.holdSale = holdSale;
        window.openCustomerModal = openCustomerModal;
        window.closeCustomerModal = closeCustomerModal;

        // Logging inicial
        logDebug('POS inicializado', {
            productos: products.length,
            categorias: <?php echo count($categories); ?>,
            clientes: <?php echo count($customers); ?>
        });
    </script>
</body>
</html>