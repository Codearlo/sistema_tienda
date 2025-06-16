<?php
session_start();

require_once 'includes/onboarding_middleware.php';

// Verificar que el usuario haya completado el onboarding
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// ===== VERIFICACIÓN DE AUTENTICACIÓN =====
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ===== CONFIGURACIÓN Y CARGA DE DATOS =====
$error_message = null;
$categories = [];
$customers = [];
$products = [];

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar categorías
    $categories = $db->fetchAll("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name
    ", [$business_id]);
    
    // Cargar clientes
    $customers = $db->fetchAll("
        SELECT id, CONCAT(first_name, ' ', last_name) as name 
        FROM customers 
        WHERE business_id = ? AND status = 1 
        ORDER BY first_name
    ", [$business_id]);
    
    // Cargar productos
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.business_id = ? AND p.status = 1 
        ORDER BY p.name ASC
    ", [$business_id]);
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
}

// Función para formatear moneda
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Treinta</title>
    <?php 
    forceCssReload();
    includeCss('assets/css/layouts/dashboard.css');
    includeCss('assets/css/layouts/pos.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-cash-register"></i>
                    <span>Treinta</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="pos.php" class="nav-item active">
                    <i class="fas fa-cash-register"></i>
                    <span>Punto de Venta</span>
                </a>
                <a href="products.php" class="nav-item">
                    <i class="fas fa-box"></i>
                    <span>Productos</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="sales.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Ventas</span>
                </a>
                <a href="expenses.php" class="nav-item">
                    <i class="fas fa-receipt"></i>
                    <span>Gastos</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-cash-register"></i> Punto de Venta</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="clearSale()">
                        <i class="fas fa-trash"></i> Limpiar
                    </button>
                    <button class="btn btn-success" onclick="completeSale()">
                        <i class="fas fa-check"></i> Completar Venta
                    </button>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="pos-container">
                <!-- Productos Panel -->
                <div class="products-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-box"></i> Productos</h3>
                        <div class="search-filters">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="product-search" placeholder="Buscar productos...">
                            </div>
                            <select id="category-filter">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="products-grid" id="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" data-product-id="<?php echo $product['id']; ?>" 
                                 data-category="<?php echo $product['category_id']; ?>">
                                <div class="product-image">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p class="product-price"><?php echo formatCurrency($product['sale_price']); ?></p>
                                    <p class="product-stock">Stock: <?php echo $product['stock_quantity']; ?></p>
                                    <button class="btn btn-primary btn-add-product" 
                                            onclick="addToCart(<?php echo $product['id']; ?>)"
                                            <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Carrito Panel -->
                <div class="cart-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-shopping-cart"></i> Carrito de Compras</h3>
                        <span class="cart-count" id="cart-count">0 productos</span>
                    </div>

                    <div class="customer-selection">
                        <label for="customer-select">Cliente:</label>
                        <select id="customer-select">
                            <option value="">Cliente general</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cart-items" id="cart-items">
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <p>El carrito está vacío</p>
                            <p>Agregue productos para comenzar</p>
                        </div>
                    </div>

                    <div class="cart-totals" id="cart-totals" style="display: none;">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">S/ 0.00</span>
                        </div>
                        <div class="total-row">
                            <span>IGV (18%):</span>
                            <span id="tax">S/ 0.00</span>
                        </div>
                        <div class="total-row total-final">
                            <span>Total:</span>
                            <span id="total">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="payment-section" id="payment-section" style="display: none;">
                        <h4>Método de Pago</h4>
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <span><i class="fas fa-money-bill"></i> Efectivo</span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="card">
                                <span><i class="fas fa-credit-card"></i> Tarjeta</span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="transfer">
                                <span><i class="fas fa-exchange-alt"></i> Transferencia</span>
                            </label>
                        </div>

                        <div class="payment-amount">
                            <label for="amount-received">Monto recibido:</label>
                            <input type="number" id="amount-received" step="0.01" placeholder="0.00">
                            <div class="change-amount" id="change-amount" style="display: none;">
                                <span>Cambio: <strong id="change-value">S/ 0.00</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="cart-actions" id="cart-actions" style="display: none;">
                        <button class="btn btn-secondary btn-block" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Limpiar Carrito
                        </button>
                        <button class="btn btn-success btn-block" onclick="processPayment()">
                            <i class="fas fa-check"></i> Procesar Pago
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Confirmación -->
    <div id="confirmation-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Venta Procesada</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>La venta se ha procesado exitosamente.</p>
                <div class="sale-summary" id="sale-summary"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>
                <button class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Imprimir Recibo
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let cart = [];
        let productsData = <?php echo json_encode($products); ?>;
        
        // Inicializar la aplicación
        document.addEventListener('DOMContentLoaded', function() {
            initializeProductSearch();
            initializeCategoryFilter();
            initializePaymentCalculation();
        });

        // Funciones del carrito
        function addToCart(productId) {
            const product = productsData.find(p => p.id == productId);
            if (!product || product.stock_quantity <= 0) return;

            const existingItem = cart.find(item => item.id == productId);
            if (existingItem) {
                if (existingItem.quantity < product.stock_quantity) {
                    existingItem.quantity++;
                }
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.sale_price),
                    quantity: 1,
                    stock: product.stock_quantity
                });
            }
            updateCartDisplay();
        }

        function removeFromCart(productId) {
            cart = cart.filter(item => item.id != productId);
            updateCartDisplay();
        }

        function updateQuantity(productId, newQuantity) {
            const item = cart.find(item => item.id == productId);
            if (item) {
                if (newQuantity <= 0) {
                    removeFromCart(productId);
                } else if (newQuantity <= item.stock) {
                    item.quantity = newQuantity;
                    updateCartDisplay();
                }
            }
        }

        function clearCart() {
            cart = [];
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const cartCount = document.getElementById('cart-count');
            const cartTotals = document.getElementById('cart-totals');
            const paymentSection = document.getElementById('payment-section');
            const cartActions = document.getElementById('cart-actions');

            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>El carrito está vacío</p>
                        <p>Agregue productos para comenzar</p>
                    </div>
                `;
                cartCount.textContent = '0 productos';
                cartTotals.style.display = 'none';
                paymentSection.style.display = 'none';
                cartActions.style.display = 'none';
                return;
            }

            let html = '';
            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="item-info">
                            <h5>${item.name}</h5>
                            <p>S/ ${item.price.toFixed(2)} c/u</p>
                        </div>
                        <div class="item-controls">
                            <div class="quantity-controls">
                                <button onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
                                <span>${item.quantity}</span>
                                <button onclick="updateQuantity(${item.id}, ${item.quantity + 1})" 
                                        ${item.quantity >= item.stock ? 'disabled' : ''}>+</button>
                            </div>
                            <div class="item-total">S/ ${itemTotal.toFixed(2)}</div>
                            <button class="btn-remove" onclick="removeFromCart(${item.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            cartItems.innerHTML = html;
            cartCount.textContent = `${cart.length} producto${cart.length !== 1 ? 's' : ''}`;

            // Calcular totales
            const tax = subtotal * 0.18;
            const total = subtotal + tax;

            document.getElementById('subtotal').textContent = `S/ ${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `S/ ${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `S/ ${total.toFixed(2)}`;

            cartTotals.style.display = 'block';
            paymentSection.style.display = 'block';
            cartActions.style.display = 'block';
        }

        // Funciones de búsqueda y filtrado
        function initializeProductSearch() {
            const searchInput = document.getElementById('product-search');
            searchInput.addEventListener('input', function() {
                filterProducts();
            });
        }

        function initializeCategoryFilter() {
            const categoryFilter = document.getElementById('category-filter');
            categoryFilter.addEventListener('change', function() {
                filterProducts();
            });
        }

        function filterProducts() {
            const searchTerm = document.getElementById('product-search').value.toLowerCase();
            const categoryId = document.getElementById('category-filter').value;
            const productCards = document.querySelectorAll('.product-card');

            productCards.forEach(card => {
                const productName = card.querySelector('h4').textContent.toLowerCase();
                const productCategory = card.dataset.category;
                
                const matchesSearch = productName.includes(searchTerm);
                const matchesCategory = !categoryId || productCategory === categoryId;
                
                card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
            });
        }

        // Funciones de pago
        function initializePaymentCalculation() {
            const amountReceived = document.getElementById('amount-received');
            amountReceived.addEventListener('input', calculateChange);
        }

        function calculateChange() {
            const total = parseFloat(document.getElementById('total').textContent.replace('S/ ', ''));
            const received = parseFloat(document.getElementById('amount-received').value) || 0;
            const change = received - total;
            
            const changeElement = document.getElementById('change-amount');
            const changeValue = document.getElementById('change-value');
            
            if (received > 0 && change >= 0) {
                changeValue.textContent = `S/ ${change.toFixed(2)}`;
                changeElement.style.display = 'block';
            } else {
                changeElement.style.display = 'none';
            }
        }

        function processPayment() {
            if (cart.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            const total = parseFloat(document.getElementById('total').textContent.replace('S/ ', ''));
            const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;

            if (paymentMethod === 'cash' && amountReceived < total) {
                alert('El monto recibido es insuficiente');
                return;
            }

            // Procesar la venta
            const saleData = {
                customer_id: document.getElementById('customer-select').value || null,
                payment_method: paymentMethod,
                amount_received: amountReceived,
                items: cart
            };

            fetch('api/sales/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSaleConfirmation(data.sale);
                    clearCart();
                } else {
                    alert('Error al procesar la venta: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la venta');
            });
        }

        function showSaleConfirmation(sale) {
            const modal = document.getElementById('confirmation-modal');
            const summary = document.getElementById('sale-summary');
            
            summary.innerHTML = `
                <p><strong>Venta #${sale.id}</strong></p>
                <p>Total: S/ ${sale.total}</p>
                <p>Método de pago: ${sale.payment_method}</p>
                <p>Fecha: ${new Date().toLocaleString()}</p>
            `;
            
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmation-modal').style.display = 'none';
        }

        function printReceipt() {
            // Implementar impresión de recibo
            window.print();
        }

        // Funciones de utilidad
        function clearSale() {
            clearCart();
            document.getElementById('customer-select').value = '';
            document.getElementById('amount-received').value = '';
            document.getElementById('change-amount').style.display = 'none';
        }

        function completeSale() {
            processPayment();
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('confirmation-modal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>

    <?php includeJs('assets/js/common.js'); ?>
</body>
</html>