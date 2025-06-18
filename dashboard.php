<?php
session_start();

// Incluir middleware de onboarding
require_once 'includes/onboarding_middleware.php';

// Verificar que el usuario haya completado el onboarding
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar si es la primera vez que accede después del onboarding
$show_welcome = isset($_GET['welcome']) && $_GET['welcome'] == '1';

$error_message = null;

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Obtener datos del negocio
    $business = $db->single(
        "SELECT * FROM businesses WHERE id = ?",
        [$business_id]
    );
    
    // Estadísticas del dashboard
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    
    // Ventas del día - ORIGINAL
    $daily_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $today]
    );
    
    // Ventas del mes - ORIGINAL
    $monthly_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
        [$business_id]
    );
    
    // Total de productos activos
    $total_products = $db->single(
        "SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
    // Productos con stock bajo
    $low_stock_products = $db->fetchAll(
        "SELECT name, stock_quantity, min_stock 
         FROM products 
         WHERE business_id = ? AND stock_quantity <= min_stock AND status = 1 
         ORDER BY (stock_quantity - min_stock) ASC 
         LIMIT 5",
        [$business_id]
    );
    
    // Total de clientes activos
    $total_customers = $db->single(
        "SELECT COUNT(*) as count FROM customers WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
    // Deudas pendientes
    $pending_debts = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total 
         FROM debts 
         WHERE business_id = ? AND status = 'pending'",
        [$business_id]
    );
    
    // Ventas de la semana (últimos 7 días) - ORIGINAL
    $weekly_sales = $db->fetchAll(
        "SELECT DATE(created_at) as date, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at) 
         ORDER BY date ASC",
        [$business_id]
    );
    
    // Productos más vendidos del mes - ORIGINAL
    $top_products = $db->fetchAll(
        "SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.line_total) as revenue
         FROM sale_items si 
         JOIN products p ON si.product_id = p.id 
         JOIN sales s ON si.sale_id = s.id
         WHERE s.business_id = ? AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY p.id, p.name 
         ORDER BY total_sold DESC 
         LIMIT 5",
        [$business_id]
    );
    
    // Calcular comparaciones - ORIGINAL
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $yesterday_sales = $db->single(
        "SELECT COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $yesterday]
    );
    
    // Porcentaje vs ayer
    $daily_change = 0;
    if ($yesterday_sales['total'] > 0) {
        $daily_change = (($daily_sales['total'] - $yesterday_sales['total']) / $yesterday_sales['total']) * 100;
    } elseif ($daily_sales['total'] > 0) {
        $daily_change = 100;
    }
    
    // Mes anterior - ORIGINAL
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    $last_month_end = date('Y-m-t', strtotime('-1 month'));
    $last_month_sales = $db->single(
        "SELECT COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) BETWEEN ? AND ?",
        [$business_id, $last_month_start, $last_month_end]
    );
    
    // Porcentaje vs mes anterior
    $monthly_change = 0;
    if ($last_month_sales['total'] > 0) {
        $monthly_change = (($monthly_sales['total'] - $last_month_sales['total']) / $last_month_sales['total']) * 100;
    } elseif ($monthly_sales['total'] > 0) {
        $monthly_change = 100;
    }
    
    // Nuevos clientes esta semana
    $new_customers_week = $db->single(
        "SELECT COUNT(*) as count 
         FROM customers 
         WHERE business_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 1",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error al cargar el dashboard: " . $e->getMessage();
    $business = ['name' => 'Tu Negocio'];
    $daily_sales = ['count' => 0, 'total' => 0];
    $monthly_sales = ['count' => 0, 'total' => 0];
    $total_products = ['count' => 0];
    $total_customers = ['count' => 0];
    $low_stock_products = [];
    $pending_debts = ['count' => 0, 'total' => 0];
    $weekly_sales = [];
    $top_products = [];
    $daily_change = 0;
    $monthly_change = 0;
    $new_customers_week = ['count' => 0];
}

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatPercentage($percentage) {
    $sign = $percentage >= 0 ? '+' : '';
    return $sign . number_format($percentage, 1) . '%';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($business['name'] ?? 'Treinta'); ?></title>
    <?php 
    forceCssReload();
    includeCss('assets/css/style.css');
    includeCss('assets/css/layouts/dashboard.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header principal -->
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <div class="user-menu">
                    <span class="user-name">Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['first_name'] ?? 'Usuario'); ?></span>
                </div>
            </div>
        </header>

        <!-- Mensaje de bienvenida si es primera vez -->
        <?php if ($show_welcome): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
            <div class="alert-content">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-text">
                    <strong>¡Bienvenido a <?php echo htmlspecialchars($business['name']); ?>!</strong>
                    <p>Tu negocio está configurado y listo para usar. Comienza agregando productos o realiza tu primera venta.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mensaje de error -->
        <?php if ($error_message): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <div class="alert-content">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-text">
                    <strong>Error</strong>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grid de estadísticas principales -->
        <div class="stats-grid">
            <!-- Ventas Hoy -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Ventas Hoy</div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo formatCurrency($daily_sales['total']); ?></div>
                <div class="stat-description"><?php echo $daily_sales['count']; ?> ventas realizadas</div>
                <div class="stat-change <?php echo $daily_change >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $daily_change >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo formatPercentage($daily_change); ?> vs ayer</span>
                </div>
            </div>

            <!-- Ventas del Mes -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Ventas del Mes</div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo formatCurrency($monthly_sales['total']); ?></div>
                <div class="stat-description"><?php echo $monthly_sales['count']; ?> ventas este mes</div>
                <div class="stat-change <?php echo $monthly_change >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $monthly_change >= 0 ? 'up' : 'down'; ?>"></i>
                    <span><?php echo formatPercentage($monthly_change); ?> vs mes anterior</span>
                </div>
            </div>

            <!-- Productos -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Productos</div>
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_products['count']; ?></div>
                <div class="stat-description">Total en inventario</div>
                <?php if (count($low_stock_products) > 0): ?>
                <div class="stat-change negative">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo count($low_stock_products); ?> con stock bajo</span>
                </div>
                <?php else: ?>
                <div class="stat-change positive">
                    <i class="fas fa-check-circle"></i>
                    <span>Stock bajo control</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Clientes -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Clientes</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_customers['count']; ?></div>
                <div class="stat-description">Clientes registrados</div>
                <?php if ($new_customers_week['count'] > 0): ?>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+<?php echo $new_customers_week['count']; ?> nuevos esta semana</span>
                </div>
                <?php else: ?>
                <div class="stat-change neutral">
                    <i class="fas fa-minus"></i>
                    <span>Sin nuevos registros</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grid del dashboard -->
        <div class="dashboard-grid">
            <!-- Productos con stock bajo -->
            <?php if (count($low_stock_products) > 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        Alertas de Stock
                    </h3>
                </div>
                <div class="card-content">
                    <div class="stock-alerts">
                        <?php foreach ($low_stock_products as $product): ?>
                        <div class="stock-item <?php echo $product['stock_quantity'] <= 0 ? 'stock-item-out' : 'stock-item-low'; ?>">
                            <div class="stock-info">
                                <div class="stock-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="stock-quantity <?php echo $product['stock_quantity'] <= 0 ? 'text-danger' : 'text-warning'; ?>">
                                    <?php echo $product['stock_quantity']; ?> / <?php echo $product['min_stock']; ?> mín.
                                </div>
                            </div>
                            <?php if ($product['stock_quantity'] <= 0): ?>
                            <div class="stock-status text-danger">
                                <i class="fas fa-times-circle"></i>
                                <span>Agotado</span>
                            </div>
                            <?php else: ?>
                            <div class="stock-status text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Stock Bajo</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer">
                        <a href="products.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-boxes"></i>
                            Ver todos los productos
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Productos más vendidos -->
            <?php if (count($top_products) > 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-fire text-primary"></i>
                        Productos Más Vendidos
                    </h3>
                    <small class="text-muted">Este mes</small>
                </div>
                <div class="card-content">
                    <div class="top-products">
                        <?php foreach ($top_products as $index => $product): ?>
                        <div class="product-item">
                            <div class="product-rank"><?php echo $index + 1; ?></div>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-stats">
                                    <span class="quantity"><?php echo number_format($product['total_sold']); ?> vendidos</span>
                                    <span class="revenue"><?php echo formatCurrency($product['revenue']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer">
                        <a href="reports.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-chart-bar"></i>
                            Ver reportes completos
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Acciones rápidas si no hay otros datos -->
            <?php if (count($low_stock_products) == 0 && count($top_products) == 0): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-rocket text-primary"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                <div class="card-content">
                    <div class="quick-actions">
                        <a href="pos.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <div class="action-text">
                                <div class="action-title">Nueva Venta</div>
                                <div class="action-description">Procesar venta</div>
                            </div>
                        </a>
                        
                        <a href="products.php?action=add" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="action-text">
                                <div class="action-title">Agregar Producto</div>
                                <div class="action-description">Nuevo producto</div>
                            </div>
                        </a>
                        
                        <a href="customers.php?action=add" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="action-text">
                                <div class="action-title">Nuevo Cliente</div>
                                <div class="action-description">Registrar cliente</div>
                            </div>
                        </a>
                        
                        <a href="reports.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="action-text">
                                <div class="action-title">Ver Reportes</div>
                                <div class="action-description">Análisis de ventas</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>