<?php
/**
 * DASHBOARD PRINCIPAL
 * Archivo: dashboard.php
 */

session_start();
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    header('Location: login.php');
    exit();
}

$business_id = $_SESSION['business_id'];
$user_name = $_SESSION['user_name'] ?? 'Usuario';

// Headers de cache
setupCacheEnvironment();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Inventario</title>
    
    <!-- Cache control -->
    <?php forceCssReload(); ?>
    
    <!-- Meta tags -->
    <meta name="description" content="Panel principal del sistema de inventario">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    
    <!-- CSS -->
    <?php
    $cssFiles = [
        'assets/css/variables.css',
        'assets/css/base.css',
        'assets/css/components/buttons.css',
        'assets/css/components/cards.css',
        'assets/css/components/forms.css',
        'assets/css/components/tables.css',
        'assets/css/components/modals.css',
        'assets/css/components/sidebar.css',
        'assets/css/layouts/dashboard.css'
    ];
    includeMultipleCss($cssFiles);
    ?>
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-page">
    <!-- Sidebar Container -->
    <div id="sidebar-container"></div>
    
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="mobile-overlay"></div>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <button id="mobileMenuBtn" class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <div class="user-menu">
                    <span class="user-name">Hola, <?php echo cleanInput($user_name); ?></span>
                    <button class="btn btn-ghost btn-sm">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Tarjetas de Estadísticas -->
        <section class="stats-grid">
            <!-- Ventas de Hoy -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cash-register text-success"></i>
                </div>
                <div class="stat-content">
                    <h3>Ventas Hoy</h3>
                    <div class="stat-value" id="salesTodayValue">S/ 0.00</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+5.2% vs ayer</span>
                    </div>
                </div>
            </div>

            <!-- Ventas del Mes -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line text-primary"></i>
                </div>
                <div class="stat-content">
                    <h3>Ventas del Mes</h3>
                    <div class="stat-value" id="salesMonthValue">S/ 0.00</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12.8% vs mes anterior</span>
                    </div>
                </div>
            </div>

            <!-- Productos -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes text-info"></i>
                </div>
                <div class="stat-content">
                    <h3>Productos</h3>
                    <div class="stat-value" id="productsValue">0</div>
                    <div class="stat-meta">Total en inventario</div>
                    <div class="stat-indicator normal">
                        <i class="fas fa-check-circle"></i>
                        <span>Stock normal</span>
                    </div>
                </div>
            </div>

            <!-- Clientes -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users text-warning"></i>
                </div>
                <div class="stat-content">
                    <h3>Clientes</h3>
                    <div class="stat-value" id="customersValue">0</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+3 nuevos esta semana</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Grid Principal -->
        <div class="dashboard-grid">
            <!-- Alertas de Stock -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        Alertas de Stock
                    </h3>
                    <a href="productos.php" class="btn btn-sm btn-ghost">Ver todos</a>
                </div>
                <div class="card-content">
                    <div id="stockAlerts" class="stock-alerts">
                        <div class="loading-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            Cargando...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos Populares -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-star text-warning"></i>
                        Productos Populares
                    </h3>
                    <a href="reportes.php" class="btn btn-sm btn-ghost">Ver reporte</a>
                </div>
                <div class="card-content">
                    <div class="popular-products">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <p>No hay datos de ventas aún. ¡Realiza tu primera venta!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deudas por Cobrar -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-credit-card text-error"></i>
                        Deudas por Cobrar
                    </h3>
                    <a href="deudas.php" class="btn btn-sm btn-ghost">Gestionar</a>
                </div>
                <div class="card-content">
                    <div class="debt-summary">
                        <div class="debt-total">
                            <div class="debt-amount" id="pendingDebtsValue">S/ 0.00</div>
                            <div class="debt-count">0 deudas pendientes</div>
                        </div>
                    </div>
                    <div id="debtsList" class="debts-list">
                        <div class="loading-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            Cargando...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de ventas semanales -->
        <div class="dashboard-card chart-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-chart-area text-primary"></i>
                    Ventas de la Semana
                </h3>
                <div class="chart-controls">
                    <button class="btn btn-sm btn-ghost" onclick="updateChart('week')">7 días</button>
                    <button class="btn btn-sm btn-ghost" onclick="updateChart('month')">30 días</button>
                </div>
            </div>
            <div class="card-content">
                <div class="chart-container">
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Ventas Recientes -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-history text-info"></i>
                    Ventas Recientes
                </h3>
                <a href="ventas.php" class="btn btn-sm btn-ghost">Ver todas</a>
            </div>
            <div class="card-content">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="recentSalesTable">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="loading-state">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Cargando ventas...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="dashboard-card quick-actions-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-lightning-bolt text-warning"></i>
                    Acciones Rápidas
                </h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <a href="venta-nueva.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span>Nueva Venta</span>
                    </a>
                    <a href="productos.php?action=new" class="quick-action-btn">
                        <i class="fas fa-box"></i>
                        <span>Agregar Producto</span>
                    </a>
                    <a href="clientes.php?action=new" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Nuevo Cliente</span>
                    </a>
                    <a href="stock.php" class="quick-action-btn">
                        <i class="fas fa-warehouse"></i>
                        <span>Gestionar Stock</span>
                    </a>
                    <a href="reportes.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>Ver Reportes</span>
                    </a>
                    <a href="configuracion.php" class="quick-action-btn">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <?php
    $jsFiles = [
        'assets/js/api.js',
        'assets/js/dashboard.js'
    ];
    includeMultipleJs($jsFiles);
    ?>
    
    <!-- Script de inicialización -->
    <script>
        // Configuración global
        window.APP_CONFIG = {
            baseUrl: '<?php echo rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/'); ?>',
            apiUrl: '<?php echo rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/backend/api',
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            userId: <?php echo $_SESSION['user_id']; ?>,
            businessId: <?php echo $_SESSION['business_id']; ?>
        };

        // Configurar token CSRF en API
        if (typeof API !== 'undefined') {
            API.setCSRFToken(window.APP_CONFIG.csrfToken);
        }

        // Log de inicialización
        console.log('🚀 Dashboard inicializado');
        console.log('📊 Configuración:', window.APP_CONFIG);
    </script>

    <!-- Error Handler -->
    <script>
        window.addEventListener('error', function(e) {
            console.error('Error global:', e.error);
            if (typeof Notifications !== 'undefined') {
                Notifications.error('Ha ocurrido un error inesperado');
            }
        });

        window.addEventListener('unhandledrejection', function(e) {
            console.error('Promise rejection:', e.reason);
            if (typeof Notifications !== 'undefined') {
                Notifications.error('Error de conexión');
            }
        });
    </script>
</body>
</html>