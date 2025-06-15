<?php
require_once 'backend/includes/auth.php';
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';

$db = getDB();
$business_id = $_SESSION['business_id'];

// Cargar categorías
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name",
    [$business_id]
);

// Cargar clientes
$customers = $db->fetchAll(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM customers WHERE business_id = ? AND status = 1 ORDER BY first_name",
    [$business_id]
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="pos-page">
    <div class="pos-container">
        <header class="pos-header">
            <div class="pos-title">
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
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    Dashboard
                </button>
                <div class="user-info">
                    <span>Cajero: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </div>
        </header>

        <div class="pos-main">
            <div class="pos-left">
                <div class="product-search-section">
                    <div class="search-input-group">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" id="productSearch" class="search-input" placeholder="Buscar producto por nombre, SKU o código de barras..." autofocus>
                        <button class="search-clear-btn hidden" id="searchClearBtn" onclick="clearProductSearch()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="categories-quick">
                    <h3>Categorías</h3>
                    <div class="categories-grid" id="categoriesGrid">
                        <button class="category-quick active" onclick="selectCategory(null)">Todas</button>
                        <?php foreach ($categories as $category): ?>
                            <button class="category-quick" onclick="selectCategory(<?php echo $category['id']; ?>)" 
                                    style="border-color: <?php echo htmlspecialchars($category['color']); ?>;">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="products-list-pos">
                    <div class="products-grid-pos" id="productsGridPos">
                        <!-- Productos cargados dinámicamente -->
                    </div>
                    
                    <div class="loading-state hidden" id="productsLoading">
                        <div class="loading-spinner-large">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                    <animate attributeName="stroke-dashoffset" dur="2s" values="31.416;0" repeatCount="indefinite"/>
                                </circle>
                            </svg>
                        </div>
                        <p>Buscando productos...</p>
                    </div>

                    <div class="empty-state hidden" id="emptyState">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <h3>No se encontraron productos</h3>
                        <p>Intenta con otro término de búsqueda</p>
                    </div>
                </div>
            </div>

            <div class="pos-right">
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

                <div class="cart-totals" id="cartTotals">
                    <div class="totals-row">
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">S/ 0.00</span>
                    </div>
                    <div class="totals-row">
                        <span>Descuento:</span>
                        <div class="discount-input">
                            <input type="number" id="discountAmount" class="discount-field" placeholder="0.00" min="0" step="0.01">
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

                <div class="payment-section">
                    <h3>Método de Pago</h3>
                    <div class="payment-methods">
                        <label class="payment-method active">
                            <input type="radio" name="payment_method" value="cash" checked>
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <span>Efectivo</span>
                            </div>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="card">
                            <div class="payment-option">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                <span>Tarjeta</span>
                            </div>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="transfer">
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
                            <input type="radio" name="payment_method" value="credit">
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
                    
                    <div class="cash-details" id="cashDetails">
                        <div class="form-group">
                            <label class="form-label">Monto Recibido</label>
                            <input type="number" id="cashReceived" class="form-input" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="change-amount">
                            <span>Cambio: <strong id="changeAmount">S/ 0.00</strong></span>
                        </div>
                    </div>
                </div>

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

    <!-- Modales -->
    <?php include 'backend/includes/modals/customer-modal.php'; ?>
    <?php include 'backend/includes/modals/receipt-modal.php'; ?>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/pos.js"></script>
    <script>
        // Variables de PHP a JavaScript
        const POSConfig = {
            businessId: <?php echo $business_id; ?>,
            userId: <?php echo $_SESSION['user_id']; ?>,
            categories: <?php echo json_encode($categories); ?>
        };
    </script>
</body>
</html>