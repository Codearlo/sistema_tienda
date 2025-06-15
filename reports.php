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
    
    // Obtener fechas para filtros
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // Primer día del mes
    $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Hoy
    
    // Reporte de ventas
    $salesReport = $db->single(
        "SELECT 
         COUNT(*) as total_sales,
         COALESCE(SUM(total_amount), 0) as total_revenue,
         COALESCE(AVG(total_amount), 0) as avg_sale,
         COALESCE(SUM(tax_amount), 0) as total_tax
         FROM sales 
         WHERE business_id = ? AND DATE(sale_date) BETWEEN ? AND ? AND status = 1",
        [$business_id, $startDate, $endDate]
    );
    
    // Productos más vendidos
    $topProducts = $db->fetchAll(
        "SELECT p.name, SUM(si.quantity) as total_quantity, SUM(si.line_total) as total_sales
         FROM sale_items si
         JOIN sales s ON si.sale_id = s.id
         JOIN products p ON si.product_id = p.id
         WHERE s.business_id = ? AND DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 1
         GROUP BY p.id, p.name
         ORDER BY total_quantity DESC
         LIMIT 10",
        [$business_id, $startDate, $endDate]
    );
    
    // Ventas por día
    $dailySales = $db->fetchAll(
        "SELECT DATE(sale_date) as date, COUNT(*) as sales_count, SUM(total_amount) as total
         FROM sales 
         WHERE business_id = ? AND DATE(sale_date) BETWEEN ? AND ? AND status = 1
         GROUP BY DATE(sale_date)
         ORDER BY date ASC",
        [$business_id, $startDate, $endDate]
    );
    
    // Gastos del período
    $expensesReport = $db->single(
        "SELECT 
         COUNT(*) as total_expenses,
         COALESCE(SUM(amount), 0) as total_amount
         FROM expenses 
         WHERE business_id = ? AND expense_date BETWEEN ? AND ? AND status = 1",
        [$business_id, $startDate, $endDate]
    );
    
    // Gastos por categoría
    $expensesByCategory = $db->fetchAll(
        "SELECT category, COUNT(*) as count, SUM(amount) as total
         FROM expenses 
         WHERE business_id = ? AND expense_date BETWEEN ? AND ? AND status = 1
         GROUP BY category
         ORDER BY total DESC",
        [$business_id, $startDate, $endDate]
    );
    
    // Inventario con valor
    $inventoryReport = $db->fetchAll(
        "SELECT p.name, p.stock_quantity, p.cost_price, p.selling_price,
         (p.stock_quantity * p.cost_price) as inventory_value,
         c.name as category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.business_id = ? AND p.status = 1 AND p.stock_quantity > 0
         ORDER BY inventory_value DESC
         LIMIT 20",
        [$business_id]
    );
    
    $totalInventoryValue = $db->single(
        "SELECT COALESCE(SUM(stock_quantity * cost_price), 0) as total
         FROM products 
         WHERE business_id = ? AND status = 1",
        [$business_id]
    )['total'];
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $salesReport = ['total_sales' => 0, 'total_revenue' => 0, 'avg_sale' => 0, 'total_tax' => 0];
    $topProducts = [];
    $dailySales = [];
    $expensesReport = ['total_expenses' => 0, 'total_amount' => 0];
    $expensesByCategory = [];
    $inventoryReport = [];
    $totalInventoryValue = 0;
}

// Calcular utilidad
$profit = $salesReport['total_revenue'] - $expensesReport['total_amount'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Treinta</title>
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
                <h1 class="page-title">Reportes</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="exportReport()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7,10 12,15 17,10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Exportar
                </button>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Filtros de fecha -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filtros de Reporte</h3>
            </div>
            <div class="card-content">
                <form method="GET" class="form-row" style="align-items: end;">
                    <div class="form-group">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Actualizar Reporte</button>
                        <button type="button" class="btn btn-gray" onclick="resetDates()">Este Mes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumen Ejecutivo -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Ingresos</div>
                        <div class="stat-value"><?php echo formatCurrency($salesReport['total_revenue']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change"><?php echo $salesReport['total_sales']; ?> ventas</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Gastos</div>
                        <div class="stat-value"><?php echo formatCurrency($expensesReport['total_amount']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-error">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change"><?php echo $expensesReport['total_expenses']; ?> gastos</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Utilidad</div>
                        <div class="stat-value"><?php echo formatCurrency($profit); ?></div>
                    </div>
                    <div class="stat-icon <?php echo $profit >= 0 ? 'stat-icon-success' : 'stat-icon-error'; ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change <?php echo $profit >= 0 ? 'stat-change-positive' : 'stat-change-negative'; ?>">
                    <?php echo $profit >= 0 ? 'Ganancia' : 'Pérdida'; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Inventario</div>
                        <div class="stat-value"><?php echo formatCurrency($totalInventoryValue); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change">Valor total</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Productos más vendidos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Productos Más Vendidos</h3>
                    <span class="badge badge-gray"><?php echo count($topProducts); ?> productos</span>
                </div>
                <div class="card-content">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-center text-gray-500">No hay datos de ventas en este período</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach (array_slice($topProducts, 0, 5) as $index => $product): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background-color: var(--gray-50); border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 24px; height: 24px; background-color: var(--primary-500); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600;">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div style="font-size: 0.875rem; color: var(--gray-500);">
                                                <?php echo $product['total_quantity']; ?> unidades vendidas
                                            </div>
                                        </div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-weight: 700;">
                                        <?php echo formatCurrency($product['total_sales']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gastos por categoría -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Gastos por Categoría</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($expensesByCategory)): ?>
                        <p class="text-center text-gray-500">No hay gastos en este período</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($expensesByCategory as $category): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background-color: var(--gray-50); border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($category['category']); ?></div>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);">
                                            <?php echo $category['count']; ?> gastos
                                        </div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-weight: 700; color: var(--error-600);">
                                        <?php echo formatCurrency($category['total']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ventas por día (Gráfico simple) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ventas por Día</h3>
                <span class="badge badge-gray"><?php echo count($dailySales); ?> días</span>
            </div>
            <div class="card-content">
                <?php if (empty($dailySales)): ?>
                    <p class="text-center text-gray-500">No hay ventas en este período</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 300px; overflow-y: auto;">
                        <?php foreach ($dailySales as $day): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid var(--gray-200);">
                                <div>
                                    <div style="font-weight: 600;"><?php echo date('d/m/Y', strtotime($day['date'])); ?></div>
                                    <div style="font-size: 0.875rem; color: var(--gray-500);">
                                        <?php echo $day['sales_count']; ?> ventas
                                    </div>
                                </div>
                                <div style="font-family: var(--font-mono); font-weight: 700;">
                                    <?php echo formatCurrency($day['total']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reporte de Inventario -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Inventario Valorizado</h3>
                <span class="badge badge-gray"><?php echo count($inventoryReport); ?> productos</span>
            </div>
            <div class="card-content">
                <?php if (empty($inventoryReport)): ?>
                    <p class="text-center text-gray-500">No hay productos en inventario</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Costo Unit.</th>
                                    <th>Precio Venta</th>
                                    <th>Valor Inventario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventoryReport as $item): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>
                                        <?php if ($item['category_name']): ?>
                                            <span class="badge badge-gray"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-mono"><?php echo $item['stock_quantity']; ?></td>
                                    <td class="font-mono"><?php echo formatCurrency($item['cost_price']); ?></td>
                                    <td class="font-mono"><?php echo formatCurrency($item['selling_price']); ?></td>
                                    <td class="font-mono font-bold"><?php echo formatCurrency($item['inventory_value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="font-weight: 700; background-color: var(--gray-50);">
                                    <td colspan="5">TOTAL INVENTARIO</td>
                                    <td class="font-mono"><?php echo formatCurrency($totalInventoryValue); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function resetDates() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.querySelector('[name="start_date"]').value = firstDay.toISOString().split('T')[0];
            document.querySelector('[name="end_date"]').value = today.toISOString().split('T')[0];
        }

        function exportReport() {
            // Simular exportación
            const startDate = document.querySelector('[name="start_date"]').value;
            const endDate = document.querySelector('[name="end_date"]').value;
            
            alert(`Exportando reporte del ${startDate} al ${endDate}\n(Funcionalidad en desarrollo)`);
            
            // Aquí implementarías la lógica real de exportación
            // window.open(`backend/export/report.php?start=${startDate}&end=${endDate}&format=pdf`);
        }

        // Imprimir reporte
        function printReport() {
            window.print();
        }

        // Auto-actualizar cada 5 minutos
        setInterval(() => {
            const form = document.querySelector('form');
            if (form) {
                form.submit();
            }
        }, 5 * 60 * 1000);
    </script>

    <style>
        @media print {
            .main-header,
            .btn,
            .sidebar {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                page-break-inside: avoid;
                margin-bottom: 1rem !important;
            }
            
            .dashboard-grid {
                display: block !important;
            }
            
            .dashboard-grid .card {
                margin-bottom: 2rem !important;
            }
        }
    </style>

</body>
</html>