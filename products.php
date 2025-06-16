<?php
session_start();

require_once 'includes/onboarding_middleware.php';
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = null;
$products = [];
$categories = [];

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.business_id = ? AND p.status = 1 
        ORDER BY p.name ASC
    ", [$business_id]);
    
    $categories = $db->fetchAll("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name
    ", [$business_id]);
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Treinta</title>
    <?php 
    forceCssReload();
    includeCss('assets/css/style.css');
    includeCss('assets/css/layouts/products.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Productos</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openProductModal()">
                    <i class="fas fa-plus"></i>
                    Nuevo Producto
                </button>
                <button class="btn btn-outline" onclick="toggleView()">
                    <i class="fas fa-th" id="viewToggleIcon"></i>
                </button>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters Card -->
        <div class="card filters-card">
            <div class="filters-header">
                <h3><i class="fas fa-filter"></i> Filtros</h3>
                <button class="btn btn-sm btn-outline" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Limpiar
                </button>
            </div>
            
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput">Buscar:</label>
                    <input type="text" id="searchInput" class="form-input" 
                           placeholder="Nombre o código del producto...">
                </div>
                
                <div class="filter-group">
                    <label for="categoryFilter">Categoría:</label>
                    <select id="categoryFilter" class="form-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="stockFilter">Stock:</label>
                    <select id="stockFilter" class="form-select">
                        <option value="">Todos</option>
                        <option value="low">Stock bajo</option>
                        <option value="out">Sin stock</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Container -->
        <div class="products-container">
            <div class="products-header">
                <div class="products-stats">
                    <span>Total: <strong id="totalProducts"><?php echo count($products); ?></strong> productos</span>
                </div>
                
                <div class="view-controls">
                    <button class="view-btn active" data-view="grid" onclick="setView('grid')">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list" onclick="setView('list')">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>

            <!-- Products Grid View -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open fa-4x"></i>
                        <h3>No hay productos</h3>
                        <p>Comience agregando su primer producto al inventario.</p>
                        <button class="btn btn-primary" onclick="openProductModal()">
                            <i class="fas fa-plus"></i>
                            Agregar Producto
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="product-placeholder">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <button class="action-btn edit" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <h4 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></p>
                                
                                <div class="product-price">
                                    S/ <?php echo number_format($product['selling_price'], 2); ?>
                                </div>
                                
                                <div class="product-stock <?php echo $product['stock'] <= 5 ? 'low-stock' : ''; ?>">
                                    <i class="fas fa-cube"></i>
                                    Stock: <?php echo $product['stock']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Products List View -->
            <div class="products-list" id="productsList" style="display: none;">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr data-product-id="<?php echo $product['id']; ?>">
                                    <td>
                                        <div class="product-info-table">
                                            <div class="product-image-small">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-box"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                <div class="product-code"><?php echo htmlspecialchars($product['barcode'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                                    <td>S/ <?php echo number_format($product['selling_price'], 2); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $product['stock'] <= 5 ? 'low' : 'normal'; ?>">
                                            <?php echo $product['stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $product['stock'] > 0 ? 'active' : 'inactive'; ?>">
                                            <?php echo $product['stock'] > 0 ? 'Disponible' : 'Agotado'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-error" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Producto</h3>
                <button class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <form id="productForm">
                <div class="modal-body">
                    <input type="hidden" id="productId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="productName" class="required">Nombre del producto:</label>
                            <input type="text" id="productName" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="productCategory">Categoría:</label>
                            <select id="productCategory" class="form-select">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="productBarcode">Código de barras:</label>
                            <input type="text" id="productBarcode" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="productCost" class="required">Precio de costo:</label>
                            <input type="number" id="productCost" class="form-input" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="productPrice" class="required">Precio de venta:</label>
                            <input type="number" id="productPrice" class="form-input" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="productStock" class="required">Stock inicial:</label>
                            <input type="number" id="productStock" class="form-input" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="productDescription">Descripción:</label>
                        <textarea id="productDescription" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeProductModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/api.js"></script>
    <script src="assets/js/products.js"></script>
</body>
</html>