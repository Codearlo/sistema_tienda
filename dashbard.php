<?php
require_once 'includes/auth.php';
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components/slidebar.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="dashboard-page">

    <?php include 'includes/slidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="newSale()">
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
                        <div class="stat-value" id="salesTodayValue">S/ 0.00</div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-positive" id="salesTodayChange">
                    Cargando...
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Productos Vendidos</div>
                        <div class="stat-value" id="productsSoldValue">0</div>
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
                <div class="stat-change" id="productsSoldChange">
                    Cargando...
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Stock Bajo</div>
                        <div class="stat-value" id="lowStockValue">0</div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-negative">
                    <a href="products.php" class="card-link">Requiere atención</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Deudas Pendientes</div>
                        <div class="stat-value" id="pendingDebtsValue">S/ 0.00</div>
                    </div>
                    <div class="stat-icon stat-icon-error">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    </div>
                </div>
                 <div class="stat-change stat-change-negative" id="pendingDebtsCount">
                    0 vencidas
                </div>
            </div>
        </section>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Ventas Recientes</h3>
                    <a href="sales.php" class="card-link">Ver todas</a>
                </div>
                <div class="card-content">
                    <div class="table-container">
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
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Productos con Stock Bajo</h3>
                    <a href="products.php" class="card-link">Gestionar</a>
                </div>
                <div class="card-content">
                    <div class="stock-alerts" id="stockAlerts">
                        </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        // Dashboard específico JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar datos del dashboard al iniciar
            loadDashboardData();

            // Actualizar cada 60 segundos
            setInterval(loadDashboardData, 60000);
        });

        async function loadDashboardData() {
            try {
                const response = await fetch('api/index.php/dashboard');
                if (!response.ok) {
                    throw new Error('Error de red al cargar datos del dashboard');
                }
                const result = await response.json();

                if (result.success) {
                    updateDashboardUI(result.data);
                } else {
                    Notifications.error(result.message || 'No se pudieron cargar los datos del dashboard.');
                }
            } catch (error) {
                console.error('Error cargando datos del dashboard:', error);
                Notifications.error('Error de conexión al cargar datos del dashboard.');
            }
        }

        function updateDashboardUI(data) {
            // Actualizar estadísticas
            document.getElementById('salesTodayValue').textContent = Utils.formatCurrency(data.stats.sales_today.total);
            document.getElementById('salesTodayChange').textContent = `${data.stats.sales_today.count} transacciones`;
            
            document.getElementById('productsSoldValue').textContent = data.stats.products_sold_today.total || 0;
            
            document.getElementById('lowStockValue').textContent = data.stats.low_stock_products.length;
            
            document.getElementById('pendingDebtsValue').textContent = Utils.formatCurrency(data.stats.pending_debts.total);
            document.getElementById('pendingDebtsCount').textContent = `${data.stats.pending_debts.count} deudas`;

            // Actualizar tabla de ventas recientes
            const salesTableBody = document.getElementById('recentSalesTable');
            salesTableBody.innerHTML = '';
            if (data.recent_sales.length > 0) {
                data.recent_sales.forEach(sale => {
                    const row = `
                        <tr>
                            <td>${Utils.formatDate(sale.sale_date, 'DD/MM/YYYY HH:mm')}</td>
                            <td>${sale.first_name ? sale.first_name + ' ' + sale.last_name : 'Cliente General'}</td>
                            <td>${sale.item_count}</td>
                            <td>${Utils.formatCurrency(sale.total_amount)}</td>
                            <td><span class="badge badge-${sale.payment_status === 'paid' ? 'success' : 'warning'}">${sale.payment_status}</span></td>
                        </tr>
                    `;
                    salesTableBody.innerHTML += row;
                });
            } else {
                salesTableBody.innerHTML = '<tr><td colspan="5" class="text-center">No hay ventas recientes.</td></tr>';
            }
            
            // Actualizar alertas de stock
            const stockAlertsContainer = document.getElementById('stockAlerts');
            stockAlertsContainer.innerHTML = '';
            if (data.stats.low_stock_products.length > 0) {
                data.stats.low_stock_products.forEach(product => {
                     const stockClass = product.stock_quantity <= 0 ? 'out' : (product.stock_quantity < product.min_stock / 2 ? 'critical' : 'low');
                     const stockBadge = product.stock_quantity <= 0 ? 'Agotado' : (stockClass === 'critical' ? 'Crítico' : 'Bajo');

                    const item = `
                         <div class="stock-item stock-item-${stockClass}">
                            <div class="stock-info">
                                <div class="stock-name">${product.name}</div>
                                <div class="stock-quantity">Stock: ${product.stock_quantity} unidades</div>
                            </div>
                            <span class="badge badge-${stockClass === 'critical' || stockClass === 'out' ? 'error' : 'warning'}">${stockBadge}</span>
                        </div>
                    `;
                    stockAlertsContainer.innerHTML += item;
                });
            } else {
                 stockAlertsContainer.innerHTML = '<p class="text-center">No hay productos con stock bajo.</p>';
            }
        }

        function newSale() {
            window.location.href = 'pos.php';
        }
    </script>
</body>
</html>