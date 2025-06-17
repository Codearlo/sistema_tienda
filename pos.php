<?php
session_start();
require_once 'includes/auth.php';
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar categorías
    $categories = $db->fetchAll("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name ASC
    ", [$business_id]);
    
    // Cargar clientes
    $customers = $db->fetchAll("
        SELECT * FROM customers 
        WHERE business_id = ? 
        AND status = 1 
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
    includeCss('assets/css/style.css');
    includeCss('assets/css/layouts/pos.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <div class="pos-container">
            <!-- Header -->
            <header class="pos-header">
                <div class="pos-title">
                    <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="pos-logo">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <h1>Punto de Venta</h1>
                </div>
                
                <div class="pos-header-actions">
                    <button class="btn btn-outline" onclick="clearCart()">
                        <i class="fas fa-trash"></i>
                        Limpiar
                    </button>
                    <button class="btn btn-success" onclick="completeTransaction()" id="completeBtn" disabled>
                        <i class="fas fa-check"></i>
                        Completar Venta
                    </button>
                    
                    <div class="user-info">
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="current-time" id="currentTime"></div>
                    </div>
                </div>
            </header>

            <!-- Main POS Layout -->
            <div class="pos-main">
                <!-- Left Panel - Productos -->
                <div class="pos-left">
                    <!-- Búsqueda de productos -->
                    <div class="product-search-section">
                        <div class="search-input-group">
                            <i class="fas fa-search search-icon"></i>
                            <input 
                                type="text" 
                                id="productSearch" 
                                class="search-input" 
                                placeholder="Buscar productos..."
                                autocomplete="off"
                            >
                            <button class="search-clear-btn" id="searchClearBtn" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Categorías -->
                    <div class="categories-section">
                        <h3>Categorías</h3>
                        <div class="categories-grid" id="categoriesGrid">
                            <button class="category-btn active" data-category="all">
                                <i class="fas fa-th-large"></i>
                                <span>Todas</span>
                            </button>
                            <?php foreach ($categories as $category): ?>
                            <button class="category-btn" data-category="<?php echo $category['id']; ?>">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Lista de productos -->
                    <div class="products-section">
                        <div class="products-header">
                            <h3>Productos</h3>
                            <button class="btn btn-sm btn-primary" onclick="openQuickProduct()">
                                <i class="fas fa-plus"></i>
                                Agregar
                            </button>
                        </div>
                        <div class="products-grid" id="productsGrid">
                            <!-- Los productos se cargan dinámicamente -->
                        </div>
                    </div>
                </div>

                <!-- Right Panel - Carrito -->
                <div class="pos-right">
                    <div class="cart-header">
                        <h2>
                            <i class="fas fa-shopping-cart"></i>
                            Carrito de Compras
                        </h2>
                        <span class="cart-count" id="cartCount">0 productos</span>
                    </div>

                    <!-- Selección de cliente -->
                    <div class="customer-section">
                        <label>Cliente:</label>
                        <div class="customer-input-group">
                            <select id="customerSelect" class="form-input">
                                <option value="">Cliente general</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline" onclick="openCustomerModal()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Items del carrito -->
                    <div class="cart-items" id="cartItems">
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <p>El carrito está vacío</p>
                            <small>Agregue productos para comenzar</small>
                        </div>
                    </div>

                    <!-- Totales -->
                    <div class="cart-totals">
                        <div class="discount-section">
                            <label>Descuento:</label>
                            <div class="discount-input-group">
                                <input type="number" id="discountAmount" class="form-input" min="0" step="0.01" placeholder="0.00">
                                <span class="input-addon">S/</span>
                            </div>
                        </div>

                        <div class="totals-breakdown">
                            <div class="total-line">
                                <span>Subtotal:</span>
                                <span id="subtotal">S/ 0.00</span>
                            </div>
                            <div class="total-line">
                                <span>IGV (18%):</span>
                                <span id="taxAmount">S/ 0.00</span>
                            </div>
                            <div class="total-line discount-line">
                                <span>Descuento:</span>
                                <span id="discountTotal">- S/ 0.00</span>
                            </div>
                            <div class="total-line final-total">
                                <span>TOTAL:</span>
                                <span id="finalTotal">S/ 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Método de pago -->
                    <div class="payment-section">
                        <h4>Método de Pago</h4>
                        <div class="payment-methods">
                            <button class="payment-btn active" data-method="cash">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Efectivo</span>
                            </button>
                            <button class="payment-btn" data-method="card">
                                <i class="fas fa-credit-card"></i>
                                <span>Tarjeta</span>
                            </button>
                            <button class="payment-btn" data-method="transfer">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Transferencia</span>
                            </button>
                        </div>

                        <div class="cash-payment" id="cashPayment">
                            <label>Monto recibido:</label>
                            <input type="number" id="cashReceived" class="form-input" min="0" step="0.01" placeholder="0.00">
                            <div class="change-amount">
                                <span>Cambio: </span>
                                <span id="changeAmount">S/ 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="cart-actions">
                        <button class="btn btn-outline btn-block" onclick="holdSale()" id="holdBtn" disabled>
                            <i class="fas fa-pause"></i>
                            Suspender Venta
                        </button>
                        <button class="btn btn-primary btn-block" onclick="printReceipt()">
                            <i class="fas fa-print"></i>
                            Imprimir Último Recibo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal: Venta Completada -->
    <div class="modal-overlay" id="transactionModal">
        <div class="modal modal-receipt">
            <div class="modal-header">
                <h3>Venta Completada</h3>
                <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <!-- Transaction details will be shown here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn btn-primary" onclick="newTransaction()">
                    <i class="fas fa-plus"></i> Nueva Venta
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Cliente Rápido -->
    <div class="modal-overlay" id="customerModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Nuevo Cliente</h3>
                <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="quickCustomerForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nombre *</label>
                            <input type="text" id="customerName" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeCustomerModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Producto Rápido -->
    <div class="modal-overlay" id="quickProductModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3>Nuevo Producto</h3>
                <button class="modal-close" onclick="closeQuickProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="quickProductForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nombre del producto: *</label>
                            <input type="text" id="quickProductName" name="name" class="form-input" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Categoría:</label>
                                <select name="category_id" class="form-input">
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Código de barras:</label>
                                <input type="text" name="barcode" class="form-input">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Precio de costo: *</label>
                                <input type="number" name="cost" class="form-input" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Precio de venta: *</label>
                                <input type="number" name="price" class="form-input" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock inicial: *</label>
                            <input type="number" name="stock" class="form-input" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descripción:</label>
                            <textarea name="description" class="form-input" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeQuickProductModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php includeJs('assets/js/app.js'); ?>
    <?php includeJs('assets/js/pos.js'); ?>
    
    <script>
        // Initialize POS data
        const categories = <?php echo json_encode($categories); ?>;
        const customers = <?php echo json_encode($customers); ?>;
        const products = <?php echo json_encode($products); ?>;
        
        // Initialize POS when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            initializePOS();
        });
    </script>
</body>
</html>