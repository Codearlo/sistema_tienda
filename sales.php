<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar ventas
    $sales = $db->fetchAll(
        "SELECT s.*, c.first_name, c.last_name, u.first_name as cashier_name,
         (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
         FROM sales s
         LEFT JOIN customers c ON s.customer_id = c.id
         LEFT JOIN users u ON s.user_id = u.id
         WHERE s.business_id = ? AND s.status = 1
         ORDER BY s.sale_date DESC
         LIMIT 50",
        [$business_id]
    );
    
    // Estadísticas del día
    $today = date('Y-m-d');
    $stats = $db->single(
        "SELECT 
         COUNT(*) as total_sales,
         COALESCE(SUM(total_amount), 0) as total_revenue,
         COALESCE(AVG(total_amount), 0) as avg_sale
         FROM sales 
         WHERE business_id = ? AND DATE(sale_date) = ? AND status = 1",
        [$business_id, $today]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $sales = [];
    $stats = ['total_sales' => 0, 'total_revenue' => 0, 'avg_sale' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Treinta</title>
    <?php includeCss('assets/css/style.css'); ?>
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
                <h1 class="page-title">Ventas</h1>
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

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Estadísticas del día -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Ventas Hoy</div>
                        <div class="stat-value"><?php echo $stats['total_sales']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M16 8l-4 4-4-4"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Ingresos Hoy</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Ticket Promedio</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['avg_sale']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de ventas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Historial de Ventas</h3>
                <span class="badge badge-gray"><?php echo count($sales); ?> ventas</span>
            </div>
            <div class="card-content">
                <?php if (empty($sales)): ?>
                    <div class="empty-state">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M16 8l-4 4-4-4"/>
                        </svg>
                        <h3>No hay ventas registradas</h3>
                        <p>Comienza realizando tu primera venta</p>
                        <button class="btn btn-primary" onclick="window.location.href='pos.php'">Ir al POS</button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Método Pago</th>
                                    <th>Estado</th>
                                    <th>Cajero</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                                    <td>
                                        <?php 
                                        if ($sale['first_name']) {
                                            echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']);
                                        } else {
                                            echo 'Cliente General';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $sale['items_count']; ?> items</td>
                                    <td class="font-mono font-bold"><?php echo formatCurrency($sale['total_amount']); ?></td>
                                    <td>
                                        <span class="badge badge-gray">
                                            <?php echo ucfirst($sale['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sale['payment_status'] === 'paid'): ?>
                                            <span class="badge badge-success">Pagado</span>
                                        <?php elseif ($sale['payment_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">Cancelado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>