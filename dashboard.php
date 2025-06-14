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
                <div class="user-menu">
                    <span class="user-name">Hola, <strong>Administrador</strong></span>
                    <button class="btn btn-logout" onclick="logout()">Salir</button>
                </div>
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
                <div class="stat-change stat-change-positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23,6 13.5,15.5 8.5,10.5 1,18"/>
                        <polyline points="17,6 23,6 23,12"/>
                    </svg>
                    +12.5% vs ayer
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
                <div class="stat-change stat-change-positive">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23,6 13.5,15.5 8.5,10.5 1,18"/>
                        <polyline points="17,6 23,6 23,12"/>
                    </svg>
                    +8.2% vs ayer
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23,18 13.5,8.5 8.5,13.5 1,6"/>
                        <polyline points="17,18 23,18 23,12"/>
                    </svg>
                    Requiere atención
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
                <div class="stat-change stat-change-negative">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23,18 13.5,8.5 8.5,13.5 1,6"/>
                        <polyline points="17,18 23,18 23,12"/>
                    </svg>
                    2 vencidas
                </div>
            </div>
        </section>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Ventas Recientes</h3>
                    <a href="sales.html" class="card-link">Ver todas</a>
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
                    <a href="products.html" class="card-link">Gestionar</a>
                </div>
                <div class="card-content">
                    <div class="stock-alerts" id="stockAlerts">
                         </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Deudas por Cobrar</h3>
                    <a href="debts.html" class="card-link">Ver todas</a>
                </div>
                <div class="card-content">
                    <div class="debt-list" id="debtsList">
                        </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Resumen del Mes</h3>
                    <a href="reports.html" class="card-link">Ver reporte</a>
                </div>
                <div class="card-content">
                    <div class="financial-summary">
                        <div class="summary-item">
                            <span class="summary-label">Ingresos Totales</span>
                            <span class="summary-value summary-positive">+ S/ 15,240.50</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Gastos Totales</span>
                            <span class="summary-value summary-negative">- S/ 4,850.00</span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-item summary-total">
                            <span class="summary-label">Utilidad Neta</span>
                            <span class="summary-value summary-profit">S/ 10,390.50</span>
                        </div>
                        <div class="summary-note">
                            <strong>¡Excelente mes!</strong> Incremento del 18.5% respecto al mes anterior.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card quick-actions-card">
            <div class="card-header">
                <h3 class="card-title">Acciones Rápidas</h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <button class="btn btn-primary" onclick="newSale()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Nueva Venta
                    </button>
                    <button class="btn btn-success" onclick="addProduct()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                            <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                            <path d="M3 21h18"/>
                        </svg>
                        Agregar Producto
                    </button>
                    <button class="btn btn-warning" onclick="addExpense()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Registrar Gasto
                    </button>
                    <button class="btn btn-gray" onclick="viewReports()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        Ver Reportes
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>