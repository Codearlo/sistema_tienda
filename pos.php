<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

$error_message = '';

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
        SELECT id, first_name, last_name, email, phone 
        FROM customers 
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

            <div class="pos-main">
                <div class="pos-left">
                    <div class="product-search-section">
                        <div class="search-input-group">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Buscar productos..." 
                                   id="productSearch" autocomplete="off">
                            <button class="search-clear-btn" onclick="clearSearch()" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="categories-quick">
                        <h3>Categorías</h3>
                        <div class="categories-grid" id="categoriesGrid">
                            </div>
                    </div>

                    <div class="products-section">
                        <div class="products-grid-pos" id="productsGrid">
                            </div>
                        
                        <div class="empty-state" id="emptyProducts" style="display: none;">
                            <i class="fas fa-box-open fa-3x"></i>
                            <h3>No hay productos</h3>
                            <p>No se encontraron productos con los criterios de búsqueda.</p>
                            <button class="btn btn-primary" onclick="clearFilters()">
                                Ver todos los productos
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pos-right">
                    <div class="cart-header">
                        <h2><i class="fas fa-shopping-cart"></i> Carrito de Compras</h2>
                        <span class="cart-count">0 productos</span>
                        <button class="btn btn-sm btn-outline igv-toggle-btn" id="igvToggleBtn" onclick="toggleIgv()">
                            <i class="fas fa-check"></i> IGV (18%) Incluido
                        </button>
                    </div>

                    <div class="customer-section">
                        <label for="customerSelect">Cliente:</label>
                        <select id="customerSelect" class="form-select">
                            <option value="">Cliente general</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cart-items" id="cartItems">
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart fa-3x"></i>
                            <h3>El carrito está vacío</h3>
                            <p>Agregue productos para comenzar</p>
                        </div>
                    </div>

                    <div class="cart-totals">
                        <div class="total-row">
                            <span class="total-label">Subtotal:</span>
                            <span class="total-amount" id="subtotalAmount">S/ 0.00</span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">IGV (18%):</span>
                            <span class="total-amount" id="taxAmount">S/ 0.00</span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Total:</span>
                            <span class="total-amount" id="totalAmount">S/ 0.00</span>
                        </div>
                    </div>

                    <div class="payment-section">
                        <h3>Método de Pago</h3>
                        <div class="payment-methods">
                            <button class="payment-method active" data-method="cash">
                                <i class="fas fa-money-bill"></i>
                                Efectivo
                            </button>
                            <button class="payment-method" data-method="card">
                                <i class="fas fa-credit-card"></i>
                                Tarjeta
                            </button>
                            <button class="payment-method" data-method="transfer">
                                <i class="fas fa-exchange-alt"></i>
                                Transferencia
                            </button>
                            <button class="payment-method" data-method="other">
                                <i class="fas fa-ellipsis-h"></i>
                                Otro
                            </button>
                        </div>

                        <div class="cash-input-group" id="cashSection">
                            <label for="cashReceived">Monto recibido:</label>
                            <input type="number" id="cashReceived" class="cash-input" 
                                   placeholder="0.00" step="0.01" min="0">
                        </div>

                        <div class="change-display" id="changeAmount">S/ 0.00</div>
                    </div>

                    <div class="cart-actions">
                        <button class="btn-complete-sale" onclick="completeTransaction()" id="completeBtn" disabled>
                            <i class="fas fa-check"></i>
                            Completar Venta
                        </button>
                        
                        <div class="cart-secondary-actions">
                            <button class="btn-secondary-action" onclick="clearCart()">
                                <i class="fas fa-trash"></i>
                                Limpiar
                            </button>
                        </div>
                        
                        <button class="btn btn-ghost" onclick="printReceipt()">
                            <i class="fas fa-print"></i>
                            Imprimir Último Recibo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL OCULTO POR DEFECTO -->
    <div class="modal" id="transactionModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Venta Completada</h3>
                <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
            </div>
            <div class="modal-body" id="transactionDetails">
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

    <script src="assets/js/api.js"></script>
    <script src="assets/js/pos.js"></script>
    
    <script>
        // Initialize POS data
        const categories = <?php echo json_encode($categories); ?>;
        const customers = <?php echo json_encode($customers); ?>;
        const products = <?php
            $formatted_products = [];
            foreach ($products as $product) {
                $product['current_stock'] = $product['stock_quantity']; // Asignar stock_quantity a current_stock
                $formatted_products[] = $product;
            }
            echo json_encode($formatted_products);
        ?>;
        
        // Initialize POS when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            initializePOS();
        });
    </script>
</body>
</html>