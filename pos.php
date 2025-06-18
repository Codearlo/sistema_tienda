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
    
    // Cargar productos con información de stock actualizada
    $products = $db->fetchAll("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.barcode,
            p.selling_price,
            p.purchase_price,
            p.stock_quantity,
            p.min_stock,
            p.unit,
            p.category_id,
            p.image_url,
            p.status,
            c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.business_id = ? AND p.status = 1 
        ORDER BY p.name ASC
    ", [$business_id]);
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
    error_log("Error en pos.php: " . $e->getMessage());
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
                <!-- Products Section -->
                <div class="pos-products">
                    <!-- Search Bar -->
                    <div class="products-header">
                        <div class="search-container">
                            <div class="search-input-wrapper">
                                <i class="fas fa-search"></i>
                                <input type="text" id="productSearch" placeholder="Buscar productos o escanear código...">
                                <button class="search-clear-btn" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="categories-section">
                        <div class="categories-grid" id="categoriesGrid">
                            <!-- Las categorías se cargan dinámicamente -->
                        </div>
                    </div>
                    
                    <!-- Products Grid -->
                    <div class="products-section">
                        <div class="products-grid" id="productsGrid">
                            <!-- Los productos se cargan dinámicamente -->
                        </div>
                    </div>
                </div>
                
                <!-- Cart Section -->
                <div class="pos-cart">
                    <div class="cart-header">
                        <h3>
                            <i class="fas fa-shopping-cart"></i>
                            Carrito (<span id="cartCount">0</span>)
                        </h3>
                    </div>
                    
                    <div class="cart-items" id="cartItems">
                        <!-- Los items del carrito se cargan dinámicamente -->
                    </div>
                    
                    <!-- Cart Totals -->
                    <div class="cart-totals">
                        <div class="total-line">
                            <span>Subtotal:</span>
                            <span id="subtotalAmount">S/ 0.00</span>
                        </div>
                        <div class="total-line">
                            <span>IGV (18%):</span>
                            <span id="taxAmount">S/ 0.00</span>
                        </div>
                        <div class="total-line total">
                            <span>TOTAL:</span>
                            <span id="totalAmount">S/ 0.00</span>
                        </div>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="payment-section">
                        <h4>Método de Pago</h4>
                        <div class="payment-methods">
                            <button class="payment-method" data-method="cash">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Efectivo</span>
                            </button>
                            <button class="payment-method" data-method="card">
                                <i class="fas fa-credit-card"></i>
                                <span>Tarjeta</span>
                            </button>
                            <button class="payment-method" data-method="transfer">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Transferencia</span>
                            </button>
                            <button class="payment-method" data-method="credit">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Crédito</span>
                            </button>
                        </div>
                        
                        <!-- Cash Payment Fields -->
                        <div id="cashFields" class="cash-fields" style="display: none;">
                            <div class="form-group">
                                <label for="cashReceived">Monto Recibido</label>
                                <div class="input-group">
                                    <span class="input-prefix">S/</span>
                                    <input type="number" id="cashReceived" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="change-display">
                                <span>Cambio:</span>
                                <span id="changeAmount">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="toggleMobileSidebar()"></div>

    <!-- Error Message -->
    <?php if ($error_message): ?>
    <div class="notification notification-error show">
        <div class="notification-content">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script>
        // Pasar datos PHP a JavaScript con validación
        const products = <?php echo json_encode($products ?: []); ?>;
        const categories = <?php echo json_encode($categories ?: []); ?>;
        const customers = <?php echo json_encode($customers ?: []); ?>;
        
        // Debug: mostrar datos cargados
        console.log('Datos cargados desde PHP:');
        console.log('Productos:', products);
        console.log('Categorías:', categories);
        console.log('Clientes:', customers);
    </script>
    
    <?php includeJs('assets/js/pos.js'); ?>
    
    <style>
        /* Estilos específicos para notificaciones */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            max-width: 300px;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-success {
            border-left: 4px solid #10b981;
        }
        
        .notification-error {
            border-left: 4px solid #ef4444;
        }
        
        .notification-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .notification-info {
            border-left: 4px solid #3b82f6;
        }
        
        /* Estilos para campos de efectivo */
        .cash-fields {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .input-group {
            display: flex;
            align-items: center;
        }
        
        .input-prefix {
            background: #e2e8f0;
            padding: 0.5rem;
            border: 1px solid #cbd5e1;
            border-right: none;
            border-radius: 6px 0 0 6px;
            font-weight: 500;
        }
        
        .input-group input {
            border-radius: 0 6px 6px 0;
            border-left: none;
            flex: 1;
        }
        
        .change-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .change-display span:last-child {
            color: #10b981;
            font-size: 1.1em;
        }
        
        /* Estilos para productos sin stock */
        .product-card.out-of-stock {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .product-card.out-of-stock:hover {
            transform: none;
            box-shadow: none;
        }
        
        .stock-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stock-badge.out {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .stock-badge.low {
            background: #fef3c7;
            color: #d97706;
        }
        
        .product-stock.out-of-stock {
            color: #dc2626;
            font-weight: 600;
        }
        
        .product-stock.low-stock {
            color: #d97706;
            font-weight: 600;
        }
    </style>
</body>
</html>