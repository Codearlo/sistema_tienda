<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Función para formatear moneda
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

// Conexión a BD
require_once 'backend/config/database.php';

try {
    $db = getDB();
    
    $business_id = $_SESSION['business_id'];
    
    // Cargar categorías
    $categories = $pdo->prepare("SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name");
    $categories->execute([$business_id]);
    $categories = $categories->fetchAll();
    
    // Cargar productos con filtros
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $stock_filter = $_GET['stock'] ?? '';
    
    $where = "p.business_id = ? AND p.status = 1";
    $params = [$business_id];
    
    if ($search) {
        $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param);
    }
    
    if ($category_filter) {
        $where .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($stock_filter === 'low') {
        $where .= " AND p.stock_quantity <= p.min_stock AND p.stock_quantity > 0";
    } elseif ($stock_filter === 'out') {
        $where .= " AND p.stock_quantity = 0";
    } elseif ($stock_filter === 'normal') {
        $where .= " AND p.stock_quantity > p.min_stock";
    }
    
    $products_query = $pdo->prepare(
        "SELECT p.*, c.name as category_name, c.color as category_color
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE $where
         ORDER BY p.name ASC"
    );
    $products_query->execute($params);
    $products = $products_query->fetchAll();
    
    // Estadísticas
    $stats_query = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            COALESCE(SUM(cost_price * stock_quantity), 0) as inventory_value,
            SUM(CASE WHEN stock_quantity <= min_stock AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM products 
        WHERE business_id = ? AND status = 1
    ");
    $stats_query->execute([$business_id]);
    $stats = $stats_query->fetch();
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
    $products = [];
    $categories = [];
    $stats = ['total_products' => 0, 'inventory_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card filters-card">
            <div class="card-content">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Buscar productos</label>
                        <div class="search-input-group">
                            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <input type="text" name="search" class="search-input" placeholder="Buscar por nombre o SKU..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select name="category" class="filter-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Estado de stock</label>
                        <select name="stock" class="filter-select">
                            <option value="">Todos</option>
                            <option value="normal" <?php echo $stock_filter === 'normal' ? 'selected' : ''; ?>>Stock normal</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Stock bajo</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Sin stock</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="products.php" class="btn btn-gray">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="products-stats">
            <div class="stat-card small">
                <div class="stat-info">
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
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
                    </svg>
                </div>
            </div>
        </div>

        <!-- Lista de Productos -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lista de Productos</h3>
                <span class="badge badge-gray"><?php echo count($products); ?> productos</span>
            </div>
            <div class="card-content">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21,15 16,10 5,21"/>
                        </svg>
                        <h3>No hay productos</h3>
                        <p>Comienza agregando tu primer producto</p>
                        <button class="btn btn-primary" onclick="openProductModal()">Agregar Producto</button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Producto</th>
                                    <th>SKU</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background-color: var(--gray-100); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                                    <polyline points="21,15 16,10 5,21"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <?php if ($product['description']): ?>
                                            <div style="font-size: 0.875rem; color: var(--gray-500);"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo strlen($product['description']) > 50 ? '...' : ''; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                            <span class="badge" style="background-color: <?php echo htmlspecialchars($product['category_color'] ?? '#gray'); ?>20; color: <?php echo htmlspecialchars($product['category_color'] ?? '#gray'); ?>;">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">Sin categoría</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: var(--font-mono); font-weight: 600;"><?php echo formatCurrency($product['selling_price']); ?></td>
                                    <td style="font-family: var(--font-mono);">
                                        <span class="<?php echo $product['stock_quantity'] == 0 ? 'stock-out' : ($product['stock_quantity'] <= $product['min_stock'] ? 'stock-low' : ''); ?>">
                                            <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['stock_quantity'] == 0): ?>
                                            <span class="badge badge-error">Sin Stock</span>
                                        <?php elseif ($product['stock_quantity'] <= $product['min_stock']): ?>
                                            <span class="badge badge-warning">Stock Bajo</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">En Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn-icon edit" onclick="editProduct(<?php echo $product['id']; ?>)" title="Editar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon stock" onclick="adjustStock(<?php echo $product['id']; ?>)" title="Ajustar Stock">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                                                    <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon delete" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Eliminar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3,6 5,6 21,6"/>
                                                    <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de Producto (Simple) -->
    <div class="modal-overlay" id="productModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title" id="productModalTitle">Nuevo Producto</h3>
                <button class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="productForm" method="POST" action="backend/api/products.php">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nombre del Producto</label>
                            <input type="text" name="name" class="form-input" required placeholder="Ej: Coca Cola 500ml">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-input" placeholder="COC-500-001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Precio de Venta</label>
                            <input type="number" name="selling_price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Inicial</label>
                            <input type="number" name="stock_quantity" class="form-input" min="0" placeholder="0">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeProductModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openProductModal() {
            document.getElementById('productModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('productForm').reset();
        }

        function editProduct(id) {
            alert('Editar producto ID: ' + id + ' (funcionalidad en desarrollo)');
        }

        function adjustStock(id) {
            const newStock = prompt('Nuevo stock:');
            if (newStock !== null && !isNaN(newStock)) {
                alert('Ajustar stock a ' + newStock + ' para producto ID: ' + id + ' (funcionalidad en desarrollo)');
            }
        }

        function deleteProduct(id) {
            if (confirm('¿Estás seguro de eliminar este producto?')) {
                alert('Eliminar producto ID: ' + id + ' (funcionalidad en desarrollo)');
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });
    </script>

</body>
</html>