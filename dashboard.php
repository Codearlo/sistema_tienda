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
    
    // Ventas del día
    $daily_sales = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $today]
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
    
    // Deudas pendientes
    $pending_debts = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total 
         FROM debts 
         WHERE business_id = ? AND status = 'pending'",
        [$business_id]
    );
    
    // Ventas de la semana (últimos 7 días)
    $weekly_sales = $db->fetchAll(
        "SELECT DATE(created_at) as date, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at) 
         ORDER BY date ASC",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error al cargar el dashboard: " . $e->getMessage();
    $business = [];
    $daily_sales = ['count' => 0, 'total' => 0];
    $low_stock_products = [];
    $pending_debts = ['count' => 0, 'total' => 0];
    $weekly_sales = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($business['business_name'] ?? 'Treinta'); ?></title>
    <?php includeCss('assets/css/style.css'); ?>
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,0 0,100 1000,80 1000,0"/></svg>');
            background-size: cover;
        }
        
        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }
        
        .welcome-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .welcome-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
        }
        
        .welcome-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            color: white;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .stat-description {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .dashboard-section {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }
        
        .quick-action:hover {
            border-color: var(--primary-color);
            background: #f8fafc;
            transform: translateY(-2px);
        }
        
        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            color: white;
        }
        
        .action-content h3 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .action-content p {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .welcome-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .welcome-btn {
                width: 100%;
                max-width: 300px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="dashboard-page">

    <?php include 'includes/slidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
        </header>

        <div class="dashboard-container">
            <?php if ($show_welcome): ?>
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2 class="welcome-title">¡Bienvenido a tu negocio!</h2>
                    <p class="welcome-subtitle">
                        <?php echo htmlspecialchars($business['business_name'] ?? 'Tu negocio'); ?> está listo para comenzar a operar
                    </p>
                    <div class="welcome-actions">
                        <a href="products.php" class="welcome-btn">
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
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Ventas Hoy</div>
                        <div class="stat-icon">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">S/ <?php echo number_format($daily_sales['total'], 2); ?></div>
                    <div class="stat-description"><?php echo $daily_sales['count']; ?> ventas realizadas</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Stock Bajo</div>
                        <div class="stat-icon">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9H21Z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo count($low_stock_products); ?></div>
                    <div class="stat-description">Productos necesitan restock</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Deudas Pendientes</div>
                        <div class="stat-icon">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">S/ <?php echo number_format($pending_debts['total'], 2); ?></div>
                    <div class="stat-description"><?php echo $pending_debts['count']; ?> deudas por cobrar</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Inventario</div>
                        <div class="stat-icon">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 7h-3V6a4 4 0 0 0-8 0v1H5a1 1 0 0 0-1 1v11a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V8a1 1 0 0 0-1-1zM10 6a2 2 0 0 1 4 0v1h-4V6zm8 15a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9h2v1a1 1 0 0 0 2 0V9h4v1a1 1 0 0 0 2 0V9h2v12z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">
                        <?php 
                        $total_products = $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1", [$business_id]);
                        echo $total_products['count'] ?? 0;
                        ?>
                    </div>
                    <div class="stat-description">Productos activos</div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                        </svg>
                        Acciones Rápidas
                    </h3>
                </div>
                <div class="quick-actions">
                    <a href="pos.php" class="quick-action">
                        <div class="action-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V19C19 20.1 18.1 21 17 21H7C5.9 21 5 20.1 5 19V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7Z"/>
                            </svg>
                        </div>
                        <div class="action-content">
                            <h3>Nueva Venta</h3>
                            <p>Registrar una venta rápida</p>
                        </div>
                    </a>

                    <a href="products.php?action=add" class="quick-action">
                        <div class="action-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                        </div>
                        <div class="action-content">
                            <h3>Agregar Producto</h3>
                            <p>Añadir nuevo producto al inventario</p>
                        </div>
                    </a>

                    <a href="expenses.php?action=add" class="quick-action">
                        <div class="action-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                            </svg>
                        </div>
                        <div class="action-content">
                            <h3>Registrar Gasto</h3>
                            <p>Anotar un gasto del negocio</p>
                        </div>
                    </a>

                    <a href="customers.php?action=add" class="quick-action">
                        <div class="action-icon">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <div class="action-content">
                            <h3>Nuevo Cliente</h3>
                            <p>Agregar cliente al sistema</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

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
    </script>
</body>
</html>