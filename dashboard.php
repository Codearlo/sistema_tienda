<?php
// Habilitar errores para debug temporal
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/includes/auth.php';

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar estadísticas básicas
    $stats = [
        'total_products' => 0,
        'inventory_value' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
    
    // Intentar cargar estadísticas reales
    try {
        $stats['total_products'] = $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1", [$business_id])['count'] ?? 0;
        $stats['inventory_value'] = $db->single("SELECT COALESCE(SUM(cost_price * stock_quantity), 0) as total FROM products WHERE business_id = ? AND status = 1", [$business_id])['total'] ?? 0;
        $stats['low_stock'] = $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1 AND stock_quantity <= min_stock AND stock_quantity > 0", [$business_id])['count'] ?? 0;
        $stats['out_of_stock'] = $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1 AND stock_quantity = 0", [$business_id])['count'] ?? 0;
    } catch (Exception $e) {
        // Si las tablas no existen, usar valores por defecto
        error_log('Error cargando estadísticas: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log('Error en dashboard: ' . $e->getMessage());
    // Valores por defecto si hay error
    $stats = [
        'total_products' => 0,
        'inventory_value' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="dashboard-page">

    <!-- Sidebar temporal básico -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <svg class="sidebar-logo" viewBox="0 0 100 100" width="40" height="40">
                <circle cx="50" cy="50" r="45" fill="#2563eb"/>
                <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
            </svg>
            <div class="sidebar-title-section">
                <h2 class="sidebar-title">Treinta</h2>
                <p class="sidebar-business"><?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Mi Negocio'); ?></p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="dashboard.php" class="sidebar-nav-link active">
                        <svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9,22 9,12 15,12 15,22"/>
                        </svg>
                        <span class="sidebar-nav-label">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="pos.php" class="sidebar-nav-link">
                        <svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                        <span class="sidebar-nav-label">Punto de Venta</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="products.php" class="sidebar-nav-link">
                        <svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                            <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                            <path d="M3 21h18"/>
                        </svg>
                        <span class="sidebar-nav-label">Productos</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst($_SESSION['user_type']); ?></div>
                </div>
            </div>
            <div class="sidebar-actions">
                <a href="backend/auth/logout.php" class="sidebar-action-btn" title="Cerrar sesión">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16,17 21,12 16,7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="window.location.href='pos.php'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nueva Venta
                </button>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Ventas Hoy</div>
                        <div class="stat-value">S/ 0.00</div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-positive">0 transacciones</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Productos</div>
                        <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                            <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                            <path d="M3 21h18"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change">Total de productos</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Stock Bajo</div>
                        <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-negative">Requiere atención</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Valor Inventario</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['inventory_value']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change">Total en productos</div>
            </div>
        </section>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Bienvenido a Treinta</h3>
                </div>
                <div class="card-content">
                    <p>Sistema de gestión empresarial funcionando correctamente.</p>
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p><strong>Negocio:</strong> <?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Mi Negocio'); ?></p>
                </div>
            </div>
        </div>
    </main>

</body>
</html>