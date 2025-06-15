<?php
session_start();

// Verificar autenticación básica
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Incluir sistema de control de cache
require_once __DIR__ . 'includes/cache_control.php';


// Función simple para formatear moneda
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Treinta</title>
    
    <?php 
    // Forzar recarga de cache en el head
    forceCssReload(); 
    
    // Incluir CSS con cache buster automático
    includeCss('assets/css/style.css');
    ?>
    
    <!-- CSS directo para sidebar oscuro (emergencia) -->
    <style>
    .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        width: 70px !important;
        background-color: #2a2a2a !important;
        border-right: 1px solid #3a3a3a !important;
        z-index: 30 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        transition: width 0.3s ease, box-shadow 0.3s ease !important;
    }

    .sidebar:hover {
        width: 280px !important;
        z-index: 50 !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3) !important;
    }

    .sidebar-header {
        display: flex !important;
        align-items: center !important;
        padding: 1rem !important;
        border-bottom: 1px solid #3a3a3a !important;
        background-color: #2a2a2a !important;
        color: white !important;
        min-height: 80px !important;
        justify-content: center !important;
        transition: all 0.3s ease !important;
    }

    .sidebar:hover .sidebar-header {
        justify-content: flex-start !important;
        padding: 1rem 1.5rem !important;
    }

    .sidebar-nav-link {
        display: flex !important;
        align-items: center !important;
        padding: 12px !important;
        color: rgba(255, 255, 255, 0.7) !important;
        text-decoration: none !important;
        transition: all 0.2s ease !important;
        font-weight: 500 !important;
        font-size: 0.875rem !important;
        position: relative !important;
        justify-content: center !important;
        border-radius: 8px !important;
        margin: 0 8px !important;
    }

    .sidebar:hover .sidebar-nav-link {
        justify-content: flex-start !important;
        padding: 12px 16px !important;
    }

    .sidebar-nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: white !important;
    }

    .sidebar-nav-link.active {
        background-color: #3b82f6 !important;
        color: white !important;
        font-weight: 600 !important;
    }

    .sidebar-nav-label {
        opacity: 0 !important;
        visibility: hidden !important;
        width: 0 !important;
        overflow: hidden !important;
        transition: all 0.3s ease !important;
    }

    .sidebar:hover .sidebar-nav-label {
        opacity: 1 !important;
        visibility: visible !important;
        width: auto !important;
        overflow: visible !important;
        transition-delay: 0.1s !important;
    }

    .sidebar-title-section {
        opacity: 0 !important;
        visibility: hidden !important;
        width: 0 !important;
        overflow: hidden !important;
        transition: all 0.3s ease !important;
    }

    .sidebar:hover .sidebar-title-section {
        opacity: 1 !important;
        visibility: visible !important;
        width: auto !important;
        overflow: visible !important;
        transition-delay: 0.1s !important;
    }

    .main-content {
        margin-left: 70px !important;
        transition: margin-left 0.3s ease !important;
    }

    .mobile-menu-btn {
        display: none !important;
    }

    @media (max-width: 1024px) {
        .mobile-menu-btn {
            display: block !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .sidebar {
            transform: translateX(-100%) !important;
            width: 280px !important;
        }
        .sidebar.open {
            transform: translateX(0) !important;
        }
    }
    </style>
</head>
<body class="dashboard-page">

    <?php include 'includes/slidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
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
                        <div class="stat-value">0</div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change">Total de productos</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Stock Bajo</div>
                        <div class="stat-value">0</div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-negative">Requiere atención</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Valor Inventario</div>
                        <div class="stat-value">S/ 0.00</div>
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
                    <h3 class="card-title">¡Bienvenido a Treinta!</h3>
                </div>
                <div class="card-content">
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p><strong>Negocio:</strong> <?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Mi Negocio'); ?></p>
                    <p><strong>Tipo:</strong> <?php echo ucfirst($_SESSION['user_type']); ?></p>
                    <p>Sistema funcionando correctamente. Usa el menú lateral para navegar.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Acciones Rápidas</h3>
                </div>
                <div class="card-content">
                    <div style="display: grid; gap: 1rem;">
                        <button class="btn btn-primary" onclick="window.location.href='pos.php'">
                            📊 Punto de Venta
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='products.php'">
                            📦 Gestionar Productos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>