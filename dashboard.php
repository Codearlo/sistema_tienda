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
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $last_month = date('Y-m', strtotime('-1 month'));
    $current_month = date('Y-m');
    $last_week = date('Y-m-d', strtotime('-7 days'));
    
    // ===== VENTAS DEL DÍA =====
    $daily_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $today]
    );
    
    // Ventas de ayer para comparación
    $yesterday_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $yesterday]
    );
    
    // Calcular porcentaje vs ayer
    $daily_change_percentage = 0;
    $daily_change_type = 'neutral';
    if ($yesterday_sales['total'] > 0) {
        $daily_change_percentage = (($daily_sales['total'] - $yesterday_sales['total']) / $yesterday_sales['total']) * 100;
        $daily_change_type = $daily_change_percentage >= 0 ? 'positive' : 'negative';
    } elseif ($daily_sales['total'] > 0) {
        $daily_change_percentage = 100; // Si ayer fue 0 y hoy hay ventas
        $daily_change_type = 'positive';
    }
    
    // ===== VENTAS DEL MES =====
    $monthly_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?",
        [$business_id, $current_month]
    );
    
    // Ventas del mes pasado para comparación
    $last_month_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?",
        [$business_id, $last_month]
    );
    
    // Calcular porcentaje vs mes anterior
    $monthly_change_percentage = 0;
    $monthly_change_type = 'neutral';
    if ($last_month_sales['total'] > 0) {
        $monthly_change_percentage = (($monthly_sales['total'] - $last_month_sales['total']) / $last_month_sales['total']) * 100;
        $monthly_change_type = $monthly_change_percentage >= 0 ? 'positive' : 'negative';
    } elseif ($monthly_sales['total'] > 0) {
        $monthly_change_percentage = 100; // Si el mes pasado fue 0 y este mes hay ventas
        $monthly_change_type = 'positive';
    }
    
    // ===== PRODUCTOS =====
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
    
    // ===== CLIENTES =====
    $total_customers = $db->single(
        "SELECT COUNT(*) as count FROM customers WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
    // Nuevos clientes esta semana
    $new_customers_week = $db->single(
        "SELECT COUNT(*) as count FROM customers 
         WHERE business_id = ? AND status = 1 AND created_at >= ?",
        [$business_id, $last_week]
    );
    
    // ===== DEUDAS PENDIENTES =====
    $pending_debts = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total 
         FROM debts 
         WHERE business_id = ? AND status = 'pending'",
        [$business_id]
    );
    
    // ===== VENTAS DE LA SEMANA =====
    $weekly_sales = $db->fetchAll(
        "SELECT DATE(sale_date) as date, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 1
         GROUP BY DATE(sale_date) 
         ORDER BY date ASC",
        [$business_id]
    );
    
    // ===== PRODUCTOS MÁS VENDIDOS =====
    $top_products = $db->fetchAll(
        "SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.line_total) as revenue
         FROM sale_items si 
         JOIN products p ON si.product_id = p.id 
         JOIN sales s ON si.sale_id = s.id
         WHERE s.business_id = ? AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND s.status = 1
         GROUP BY p.id, p.name 
         ORDER BY total_sold DESC 
         LIMIT 5",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error al cargar el dashboard: " . $e->getMessage();
    $business = ['name' => 'Tu Negocio'];
    $daily_sales = ['count' => 0, 'total' => 0];
    $yesterday_sales = ['count' => 0, 'total' => 0];
    $daily_change_percentage = 0;
    $daily_change_type = 'neutral';
    $total_products = ['count' => 0];
    $low_stock_products = [];
    $pending_debts = ['count' => 0, 'total' => 0];
    $weekly_sales = [];
    $monthly_sales = ['count' => 0, 'total' => 0];
    $last_month_sales = ['count' => 0, 'total' => 0];
    $monthly_change_percentage = 0;
    $monthly_change_type = 'neutral';
    $total_customers = ['count' => 0];
    $new_customers_week = ['count' => 0];
    $top_products = [];
}

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

function formatPercentage($percentage) {
    return number_format(abs($percentage), 1) . '%';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
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
                    <span class="user-name">Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                    <button class="btn btn-ghost btn-sm" onclick="window.location.href='settings.php'">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Banner de bienvenida -->
        <?php if ($show_welcome): ?>
        <div class="welcome-banner">
            <div class="welcome-content">
                <div class="welcome-icon">
                    <i class="fas fa-party-horn"></i>
                </div>
                <div class="welcome-text">
                    <h2>¡Bienvenido a Treinta!</h2>
                    <p>
                        <?php echo htmlspecialchars($business['name'] ?? 'Tu negocio'); ?> está listo para comenzar a operar
                    </p>
                    <div class="welcome-actions">
                        <a href="add-product.php" class="welcome-btn">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Agregar Productos
                        </a>
                        <a href="pos.php" class="welcome-btn">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                                <path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V19C19 20.1 18.1 21 17 21H7C5.9 21 5 20.1 5 19V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7Z"/>
                            </svg>
                            Primera Venta
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <!-- VENTAS HOY - Con cálculo dinámico -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Ventas Hoy</div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo formatCurrency($daily_sales['total']); ?></div>
                <div class="stat-description"><?php echo $daily_sales['count']; ?> ventas realizadas</div>
                <div class="stat-change <?php echo $daily_change_type; ?>">
                    <i class="fas fa-arrow-<?php echo $daily_change_type === 'positive' ? 'up' : ($daily_change_type === 'negative' ? 'down' : 'right'); ?>"></i>
                    <span>
                        <?php if ($daily_change_type === 'neutral'): ?>
                            Sin cambios vs ayer
                        <?php else: ?>
                            <?php echo $daily_change_type === 'positive' ? '+' : ''; ?><?php echo formatPercentage($daily_change_percentage); ?> vs ayer
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- VENTAS DEL MES - Con cálculo dinámico -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Ventas del Mes</div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo formatCurrency($monthly_sales['total']); ?></div>
                <div class="stat-description"><?php echo $monthly_sales['count']; ?> ventas este mes</div>
                <div class="stat-change <?php echo $monthly_change_type; ?>">
                    <i class="fas fa-arrow-<?php echo $monthly_change_type === 'positive' ? 'up' : ($monthly_change_type === 'negative' ? 'down' : 'right'); ?>"></i>
                    <span>
                        <?php if ($monthly_change_type === 'neutral'): ?>
                            Sin cambios vs mes anterior
                        <?php else: ?>
                            <?php echo $monthly_change_type === 'positive' ? '+' : ''; ?><?php echo formatPercentage($monthly_change_percentage); ?> vs mes anterior
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- PRODUCTOS - Con alerta de stock corregida -->
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
                <div class="stat-change neutral">
                    <i class="fas fa-check"></i>
                    <span>Stock normal</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- CLIENTES - Con cálculo dinámico -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Clientes</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $total_customers['count']; ?></div>
                <div class="stat-description">Clientes registrados</div>
                <div class="stat-change <?php echo $new_customers_week['count'] > 0 ? 'positive' : 'neutral'; ?>">
                    <i class="fas fa-arrow-<?php echo $new_customers_week['count'] > 0 ? 'up' : 'right'; ?>"></i>
                    <span>
                        <?php if ($new_customers_week['count'] > 0): ?>
                            +<?php echo $new_customers_week['count']; ?> nuevos esta semana
                        <?php else: ?>
                            Sin nuevos clientes esta semana
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid principal del dashboard -->
        <div class="dashboard-grid">
            <!-- Alertas de stock bajo -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        Alertas de Stock
                    </h3>
                    <a href="products.php" class="btn btn-sm btn-outline">Ver todos</a>
                </div>
                <div class="card-content">
                    <?php if (empty($low_stock_products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle text-success"></i>
                            <p>¡Excelente! No hay productos con stock bajo.</p>
                        </div>
                    <?php else: ?>
                        <div class="stock-alerts">
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="stock-item <?php echo $product['stock_quantity'] == 0 ? 'stock-item-out' : 'stock-item-low'; ?>">
                                    <div class="stock-info">
                                        <div class="stock-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="stock-quantity">
                                            Stock: <?php echo $product['stock_quantity']; ?> 
                                            (Mín: <?php echo $product['min_stock']; ?>)
                                        </div>
                                    </div>
                                    <div class="stock-actions">
                                        <button class="btn btn-sm btn-primary" onclick="window.location.href='products.php'">
                                            Reabastecer
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resumen de actividad -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-chart-area text-primary"></i>
                        Actividad Reciente
                    </h3>
                </div>
                <div class="card-content">
                    <div class="activity-stats">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="activity-info">
                                <div class="activity-label">Ventas hoy</div>
                                <div class="activity-value"><?php echo $daily_sales['count']; ?></div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="activity-info">
                                <div class="activity-label">Deudas pendientes</div>
                                <div class="activity-value"><?php echo $pending_debts['count']; ?></div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div class="activity-info">
                                <div class="activity-label">Productos activos</div>
                                <div class="activity-value"><?php echo $total_products['count']; ?></div>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="activity-info">
                                <div class="activity-label">Total clientes</div>
                                <div class="activity-value"><?php echo $total_customers['count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-bolt text-warning"></i>
                    Acciones Rápidas
                </h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <a href="pos.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="action-content">
                            <h3>Nueva Venta</h3>
                            <p>Registrar una venta en el POS</p>
                        </div>
                    </a>

                    <a href="add-product.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Agregar Producto</h3>
                            <p>Añadir nuevo producto al inventario</p>
                        </div>
                    </a>

                    <a href="customers.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Nuevo Cliente</h3>
                            <p>Registrar un nuevo cliente</p>
                        </div>
                    </a>

                    <a href="reports.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h3>Ver Reportes</h3>
                            <p>Analizar el rendimiento del negocio</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php includeJs('assets/js/dashboard.js'); ?>
    
    <script>
        // Auto-ocultar banner de bienvenida después de 10 segundos
        <?php if ($show_welcome): ?>
        setTimeout(() => {
            const banner = document.querySelector('.welcome-banner');
            if (banner) {
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    banner.style.display = 'none';
                }, 500);
            }
        }, 10000);
        <?php endif; ?>

        // Inicializar gráfico de ventas
        document.addEventListener('DOMContentLoaded', function() {
            const salesData = <?php echo json_encode($weekly_sales); ?>;
            if (typeof initializeSalesChart === 'function') {
                initializeSalesChart(salesData);
            }
        });
    </script>
</body>
</html>