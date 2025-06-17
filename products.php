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
        SELECT p.*, c.name as category_name,
               CASE 
                   WHEN p.stock_quantity <= p.min_stock THEN 1 
                   ELSE 0 
               END as low_stock
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

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
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
                <button class="btn btn-outline" onclick="openCategoryModal()">
                    <i class="fas fa-tags"></i>
                    Categorías
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
                <h3>
                    <i class="fas fa-filter"></i>
                    Filtros
                </h3>
                <button class="btn btn-ghost btn-sm" onclick="clearFilters()">
                    <i class="fas fa-times"></i>
                    Limpiar
                </button>
            </div>
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput">Buscar:</label>
                    <input type="text" 
                           id="searchInput" 
                           class="form-input" 
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
                        <option value="available">Con stock</option>
                        <option value="low">Stock bajo</option>
                        <option value="out">Sin stock</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Container -->
        <div class="products-container">
            <div class="products-header">
                <div class="products-count">
                    <span id="productsCount">Total: <?php echo count($products); ?> productos</span>
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
                        <i class="fas fa-box-open"></i>
                        <h3>No hay productos</h3>
                        <p>Comienza agregando tu primer producto</p>
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
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                                
                                <?php if ($product['low_stock']): ?>
                                    <span class="stock-badge low-stock">Stock Bajo</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <h4 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></p>
                                
                                <div class="product-price">
                                    <?php echo formatCurrency($product['selling_price']); ?>
                                </div>
                                
                                <div class="product-stock <?php echo $product['stock_quantity'] <= 5 ? 'low-stock' : ''; ?>">
                                    <i class="fas fa-cube"></i>
                                    Stock: <?php echo $product['stock_quantity']; ?>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="adjustStock(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-boxes"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                                                <div class="product-code"><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                                    <td><?php echo formatCurrency($product['selling_price']); ?></td>
                                    <td>
                                        <span class="stock-display <?php echo $product['stock_quantity'] <= 5 ? 'low-stock' : ''; ?>">
                                            <?php echo $product['stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-active">Activo</span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="adjustStock(<?php echo $product['id']; ?>)" title="Ajustar Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Eliminar">
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
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Producto</h3>
                <button class="modal-close" onclick="closeProductModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="productForm" onsubmit="saveProduct(event)">
                <input type="hidden" id="productId" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="productName">Nombre del Producto *</label>
                        <input type="text" id="productName" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="productCategory">Categoría</label>
                        <select id="productCategory" name="category_id" class="form-select">
                            <option value="">Sin categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="productSku">SKU</label>
                        <input type="text" id="productSku" name="sku" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="productBarcode">Código de Barras</label>
                        <input type="text" id="productBarcode" name="barcode" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="productCost">Precio de Costo</label>
                        <input type="number" id="productCost" name="cost_price" class="form-input" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="productPrice">Precio de Venta *</label>
                        <input type="number" id="productPrice" name="selling_price" class="form-input" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="productStock">Stock Inicial</label>
                        <input type="number" id="productStock" name="stock_quantity" class="form-input" min="0">
                    </div>
                    <div class="form-group">
                        <label for="productMinStock">Stock Mínimo</label>
                        <input type="number" id="productMinStock" name="min_stock" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="productDescription">Descripción</label>
                    <textarea id="productDescription" name="description" class="form-input" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeProductModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Gestionar Categorías</h3>
                <button class="modal-close" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="categoryForm" onsubmit="saveCategory(event)">
                    <div class="form-group">
                        <label for="categoryName">Nombre de la Categoría</label>
                        <input type="text" id="categoryName" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="categoryDescription">Descripción</label>
                        <textarea id="categoryDescription" name="description" class="form-input" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Agregar Categoría
                    </button>
                </form>
                
                <div class="categories-list">
                    <h4>Categorías Existentes</h4>
                    <div id="categoriesList">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item" data-category-id="<?php echo $category['id']; ?>">
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajustar Stock</h3>
                <button class="modal-close" onclick="closeStockModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="stockForm" onsubmit="adjustStockSubmit(event)">
                <input type="hidden" id="stockProductId" name="product_id">
                <div class="stock-info">
                    <h4 id="stockProductName"></h4>
                    <p>Stock actual: <span id="currentStock"></span></p>
                </div>
                <div class="form-group">
                    <label for="stockAdjustment">Tipo de Ajuste</label>
                    <select id="adjustmentType" name="type" class="form-select" required onchange="updateAdjustmentType()">
                        <option value="add">Agregar Stock</option>
                        <option value="remove">Reducir Stock</option>
                        <option value="set">Establecer Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stockQuantity" id="quantityLabel">Cantidad</label>
                    <input type="number" id="stockQuantity" name="quantity" class="form-input" min="0" required>
                </div>
                <div class="form-group">
                    <label for="adjustmentReason">Motivo</label>
                    <textarea id="adjustmentReason" name="reason" class="form-input" rows="2" placeholder="Motivo del ajuste..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeStockModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Ajustar Stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Pasar datos PHP a JavaScript
        const products = <?php echo json_encode($products); ?>;
        const categories = <?php echo json_encode($categories); ?>;
    </script>
    <?php includeJs('assets/js/products.js'); ?>
    <?php includeJs('assets/js/modals.js'); ?>
    
    <script>
        // Inicializar productos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            initializeProducts();
        });
    </script>
</body>
</html>