<?php
session_start();

// Verificar autenticación básica
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">

    <?php include 'backend/includes/slidebar.php'; ?>

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