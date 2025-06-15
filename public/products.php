<?php
require_once 'backend/includes/auth.php';
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';

$db = getDB();
$business_id = $_SESSION['business_id'];

// Cargar categorías
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name",
    [$business_id]
);

// Cargar estadísticas
$stats = [
    'total_products' => $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1", [$business_id])['count'],
    'inventory_value' => $db->single("SELECT COALESCE(SUM(cost_price * stock_quantity), 0) as total FROM products WHERE business_id = ? AND status = 1", [$business_id])['total'],
    'low_stock' => $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1 AND stock_quantity <= min_stock AND stock_quantity > 0", [$business_id])['count'],
    'out_of_stock' => $db->single("SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1 AND stock_quantity = 0", [$business_id])['count']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="dashboard-page">
    <div id="sidebar-container"></div>

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
                <h1 class="page-title">Productos</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openProductModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Producto
                </button>
                <button class="btn btn-warning" onclick="openCategoryModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    Categorías
                </button>
                <div class="user-menu">
                    <span class="user-name">Hola, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    <button class="btn btn-logout" onclick="logout()">Salir</button>
                </div>
            </div>
        </header>

        <!-- Filters Section -->
        <div class="card filters-card">
            <div class="card-content">
                <form id="filterForm" method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Buscar productos</label>
                        <div class="search-input-group">
                            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <input type="text" name="search" id="searchProducts" class="search-input" 
                                   placeholder="Buscar por nombre, SKU o código de barras..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select name="category" id="categoryFilter" class="filter-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($_GET['category'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Estado de stock</label>
                        <select name="stock" id="stockFilter" class="filter-select">
                            <option value="">Todos</option>
                            <option value="low" <?php echo ($_GET['stock'] ?? '') == 'low' ? 'selected' : ''; ?>>Stock bajo</option>
                            <option value="out" <?php echo ($_GET['stock'] ?? '') == 'out' ? 'selected' : ''; ?>>Sin stock</option>
                            <option value="normal" <?php echo ($_GET['stock'] ?? '') == 'normal' ? 'selected' : ''; ?>>Stock normal</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/>
                            </svg>
                            Filtrar
                        </button>
                        <a href="products.php" class="btn btn-gray">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Stats -->
        <div class="products-stats">
            <div class="stat-card small">
                <div class="stat-info">
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                        <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                        <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                        <path d="M3 21h18"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card small">
                <div class="stat-info">
                    <div class="stat-label">Valor Inventario</div>
                    <div class="stat-value"><?php echo formatCurrency($stats['inventory_value']); ?></div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card small">
                <div class="stat-info">
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card small">
                <div class="stat-info">
                    <div class="stat-label">Sin Stock</div>
                    <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                </div>
                <div class="stat-icon stat-icon-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lista de Productos</h3>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid" onclick="toggleView('grid')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </button>
                    <button class="view-btn" data-view="list" onclick="toggleView('list')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="card-content">
                <div id="productsContainer">
                    <!-- Los productos se cargan dinámicamente aquí -->
                </div>
            </div>
        </div>
    </main>

    <!-- Modales -->
    <?php include 'backend/includes/modals/product-modal.php'; ?>
    <?php include 'backend/includes/modals/category-modal.php'; ?>

    <script src="assets/js/app.js"></script>
    <script>
        // Cargar productos al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadSidebar();
            loadProducts();
        });

        async function loadSidebar() {
            try {
                const response = await fetch('backend/includes/sidebar.php');
                const html = await response.text();
                document.getElementById('sidebar-container').innerHTML = html;
                setActivePage('products');
            } catch (error) {
                console.error('Error cargando sidebar:', error);
            }
        }

        async function loadProducts() {
            const params = new URLSearchParams(window.location.search);
            try {
                const response = await fetch(`backend/api/products.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    renderProducts(data.products);
                } else {
                    Notifications.error('Error al cargar productos');
                }
            } catch (error) {
                console.error('Error:', error);
                Notifications.error('Error de conexión');
            }
        }

        function renderProducts(products) {
            const container = document.getElementById('productsContainer');
            if (products.length === 0) {
                container.innerHTML = '<div class="empty-state">No se encontraron productos</div>';
                return;
            }
            
            container.innerHTML = '<div class="products-grid">' + 
                products.map(product => createProductCard(product)).join('') + 
                '</div>';
        }

        function createProductCard(product) {
            // ... código de la tarjeta de producto
        }

        function setActivePage(page) {
            document.querySelectorAll('.sidebar-nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.dataset.page === page) {
                    link.classList.add('active');
                }
            });
        }

        function logout() {
            if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
                window.location.href = 'backend/auth/logout.php';
            }
        }
    </script>
</body>
</html>