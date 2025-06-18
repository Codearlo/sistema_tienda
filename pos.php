<?php
session_start();
require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    header('Location: login.php');
    exit();
}

$business_id = $_SESSION['business_id'];

try {
    $db = getDB();
    
    // CORREGIDO: Cargar productos con stock actualizado desde tabla 'products'
    $products = $db->fetchAll(
        "SELECT p.id, p.name, p.sku, p.barcode, p.selling_price, 
                p.stock_quantity as current_stock, p.min_stock, p.track_stock,
                c.name as category_name, p.category_id,
                p.image, p.unit
         FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.business_id = ? AND p.status = 1 
         ORDER BY p.name ASC",
        [$business_id]
    );
    
    // Cargar categorías
    $categories = $db->fetchAll(
        "SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name ASC",
        [$business_id]
    );
    
    // Cargar clientes
    $customers = $db->fetchAll(
        "SELECT id, first_name, last_name, email, phone FROM customers WHERE business_id = ? AND status = 1 ORDER BY first_name ASC",
        [$business_id]
    );
    
} catch (Exception $e) {
    error_log("Error en POS: " . $e->getMessage());
    $products = [];
    $categories = [];
    $customers = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Sistema de Inventario</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layouts/pos.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/cards.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/modals.css">
    <link rel="stylesheet" href="assets/css/components/notifications.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="pos-page">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- POS Container -->
    <div class="pos-container">
        <!-- Header -->
        <header class="pos-header">
            <div class="pos-title">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="pos-logo">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h1>Punto de Venta</h1>
            </div>
            
            <div class="pos-header-actions">
                <div class="user-info">
                    <div>Usuario: <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </div>
        </header>
        
        <!-- Main POS Content -->
        <main class="pos-main">
            <!-- Left Panel - Products -->
            <div class="pos-left">
                <!-- Search and filters -->
                <div class="pos-filters">
                    <div class="search-box">
                        <input type="text" id="productSearch" placeholder="Buscar productos, SKU o código...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-actions">
                        <button class="btn btn-outline btn-sm" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                            Limpiar
                        </button>
                    </div>
                </div>
                
                <!-- Categories -->
                <div class="categories-tabs" id="categoriesTabs">
                    <!-- Categories will be loaded by JavaScript -->
                </div>
                
                <!-- Products Grid -->
                <div class="products-section">
                    <div class="products-grid-pos" id="productsGrid">
                        <!-- Products will be loaded by JavaScript -->
                    </div>
                    
                    <div class="empty-state" id="emptyProducts" style="display: none;">
                        <div class="empty-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No se encontraron productos</h3>
                        <p>Intenta ajustar los filtros de búsqueda</p>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Cart -->
            <div class="pos-right">
                <!-- Cart Header -->
                <div class="cart-header">
                    <h3>
                        <i class="fas fa-shopping-cart"></i>
                        Carrito de Compras
                    </h3>
                    <div class="cart-actions">
                        <button class="btn btn-outline btn-sm" onclick="clearCart()" id="clearCartBtn" disabled>
                            <i class="fas fa-trash"></i>
                            Limpiar
                        </button>
                    </div>
                </div>
                
                <!-- Customer Selection -->
                <div class="customer-section">
                    <label>Cliente:</label>
                    <select id="customerSelect" class="form-control">
                        <option value="">Cliente Genérico</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>">
                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Cart Items -->
                <div class="cart-items" id="cartItems">
                    <div class="empty-cart">
                        <div class="empty-cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <p>El carrito está vacío</p>
                        <small>Selecciona productos para comenzar una venta</small>
                    </div>
                </div>
                
                <!-- Cart Summary -->
                <div class="cart-summary" id="cartSummary" style="display: none;">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">S/ 0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>IGV (18%):</span>
                        <span id="tax">S/ 0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total">S/ 0.00</span>
                    </div>
                </div>
                
                <!-- Payment Section -->
                <div class="payment-section" id="paymentSection" style="display: none;">
                    <div class="payment-methods">
                        <label>Método de Pago:</label>
                        <select id="paymentMethod" class="form-control">
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="transfer">Transferencia</option>
                            <option value="mixed">Mixto</option>
                        </select>
                    </div>
                    
                    <div class="payment-amount" id="cashPayment">
                        <label>Monto Recibido:</label>
                        <input type="number" id="amountReceived" class="form-control" step="0.01" placeholder="0.00">
                        <div class="change-amount" id="changeAmount" style="display: none;">
                            <strong>Cambio: <span id="change">S/ 0.00</span></strong>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-success btn-block" onclick="processPayment()" id="processPaymentBtn">
                            <i class="fas fa-check"></i>
                            Procesar Venta
                        </button>
                        
                        <button class="btn btn-outline btn-block" onclick="holdTransaction()">
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
        </main>
    </div>

    <!-- Modals -->
    <div class="modal" id="transactionModal">
        <div class="modal-content">
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

    <!-- Scripts -->
    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/api.js"></script>
    <script src="assets/js/pos.js"></script>
    
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