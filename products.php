<?php
session_start();
require_once 'includes/auth.php';
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Obtener filtros
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $stock_filter = $_GET['stock'] ?? '';
    
    // Construir query con filtros
    $where_conditions = ["p.business_id = ?"];
    $params = [$business_id];
    
    if ($search) {
        $where_conditions[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($category_filter && $category_filter !== 'all') {
        $where_conditions[] = "p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($stock_filter) {
        switch ($stock_filter) {
            case 'low':
                $where_conditions[] = "p.stock_quantity <= p.min_stock AND p.stock_quantity > 0";
                break;
            case 'out':
                $where_conditions[] = "p.stock_quantity = 0";
                break;
            case 'available':
                $where_conditions[] = "p.stock_quantity > 0";
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Obtener productos
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name, 
               CASE 
                   WHEN p.stock_quantity = 0 THEN 'out'
                   WHEN p.stock_quantity <= p.min_stock THEN 'low'
                   ELSE 'normal'
               END as stock_status
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where_clause AND p.status = 1
        ORDER BY p.name ASC
    ", $params);
    
    // Obtener categorías para el filtro
    $categories = $db->fetchAll("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name ASC
    ", [$business_id]);
    
    // Estadísticas rápidas
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock_quantity <= min_stock AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(stock_quantity * cost_price) as total_inventory_value
        FROM products 
        WHERE business_id = ? AND status = 1
    ", [$business_id]);
    
} catch (Exception $e) {
    $error_message = "Error de conexión: " . $e->getMessage();
}

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

function getStockBadgeClass($status) {
    switch ($status) {
        case 'out': return 'badge-error';
        case 'low': return 'badge-warning';
        default: return 'badge-success';
    }
}

function getStockBadgeText($status, $stock) {
    switch ($status) {
        case 'out': return 'Agotado';
        case 'low': return 'Stock Bajo';
        default: return $stock . ' unidades';
    }
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
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-box"></i> Productos</h1>
                <p>Gestiona tu inventario y productos</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-success" onclick="openProductModal()">
                    <i class="fas fa-plus"></i>
                    Nuevo Producto
                </button>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <?php if (isset($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_products']); ?></h3>
                    <p>Total Productos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo formatCurrency($stats['total_inventory_value']); ?></h3>
                    <p>Valor Inventario</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['low_stock']); ?></h3>
                    <p>Stock Bajo</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['out_of_stock']); ?></h3>
                    <p>Agotados</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtros y búsqueda -->
        <div class="content-card">
            <div class="card-header">
                <h3>Filtros</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Buscar:</label>
                            <input type="text" name="search" class="form-input" 
                                   placeholder="Nombre, código de barras, SKU..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label>Categoría:</label>
                            <select name="category" class="form-input">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Stock:</label>
                            <select name="stock" class="form-input">
                                <option value="">Todos</option>
                                <option value="available" <?php echo ($stock_filter === 'available') ? 'selected' : ''; ?>>Disponible</option>
                                <option value="low" <?php echo ($stock_filter === 'low') ? 'selected' : ''; ?>>Stock Bajo</option>
                                <option value="out" <?php echo ($stock_filter === 'out') ? 'selected' : ''; ?>>Agotado</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="products.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de productos -->
        <div class="content-card">
            <div class="card-header">
                <h3>Lista de Productos</h3>
                <div class="view-options">
                    <button class="view-btn active" data-view="grid" title="Vista de cuadrícula">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list" title="Vista de lista">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No hay productos</h3>
                        <p>No se encontraron productos con los filtros aplicados.</p>
                        <button class="btn btn-primary" onclick="openProductModal()">
                            <i class="fas fa-plus"></i>
                            Agregar Primer Producto
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Vista de cuadrícula -->
                    <div class="products-grid" id="productsGrid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="product-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-overlay">
                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-error" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="product-info">
                                <h4 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <?php if ($product['category_name']): ?>
                                    <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                <?php endif; ?>
                                <div class="product-price">
                                    <?php echo formatCurrency($product['selling_price']); ?>
                                </div>
                                <div class="product-stock">
                                    <span class="badge <?php echo getStockBadgeClass($product['stock_status']); ?>">
                                        <?php echo getStockBadgeText($product['stock_status'], $product['stock_quantity']); ?>
                                    </span>
                                </div>
                                <?php if ($product['barcode']): ?>
                                    <div class="product-barcode">
                                        <small><i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['barcode']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Vista de lista -->
                    <div class="products-table" id="productsTable" style="display: none;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Precio</th>
                                    <th>Costo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <div class="product-avatar">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="">
                                                <?php else: ?>
                                                    <i class="fas fa-box"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-details">
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <?php if ($product['barcode']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-barcode"></i> 
                                                        <?php echo htmlspecialchars($product['barcode']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                            <span class="badge badge-light">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin categoría</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStockBadgeClass($product['stock_status']); ?>">
                                            <?php echo $product['stock_quantity']; ?> unidades
                                        </span>
                                        <?php if ($product['stock_status'] === 'low'): ?>
                                            <small class="text-warning d-block">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Mín: <?php echo $product['min_stock']; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-weight-bold">
                                        <?php echo formatCurrency($product['selling_price']); ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo formatCurrency($product['cost_price']); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" onclick="editProduct(<?php echo $product['id']; ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline" onclick="duplicateProduct(<?php echo $product['id']; ?>)" title="Duplicar">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button class="btn btn-sm btn-error" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="Eliminar">
                                                <i class="fas fa-trash"></i>
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

    <!-- Modal de Producto -->
    <div class="modal-overlay" id="productModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title" id="productModalTitle">Nuevo Producto</h3>
                <button class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" id="productId" name="product_id">
                    
                    <div class="form-grid">
                        <div class="form-section">
                            <h4 class="form-section-title">Información Básica</h4>
                            
                            <div class="form-group">
                                <label class="form-label required">Nombre del Producto</label>
                                <input type="text" id="productName" name="name" class="form-input" required 
                                       placeholder="Ej: Coca Cola 500ml">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">SKU</label>
                                    <input type="text" id="productSku" name="sku" class="form-input" 
                                           placeholder="Ej: COC-500-001">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Código de Barras</label>
                                    <input type="text" id="productBarcode" name="barcode" class="form-input" 
                                           placeholder="Ej: 7501234567890">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Categoría</label>
                                <select id="productCategory" name="category_id" class="form-input">
                                    <option value="">Seleccionar categoría</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Descripción</label>
                                <textarea id="productDescription" name="description" class="form-input" rows="3"
                                          placeholder="Descripción del producto (opcional)"></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="form-section-title">Precios e Inventario</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Precio de Costo</label>
                                    <div class="input-group">
                                        <span class="input-addon">S/</span>
                                        <input type="number" id="productCost" name="cost_price" class="form-input" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required">Precio de Venta</label>
                                    <div class="input-group">
                                        <span class="input-addon">S/</span>
                                        <input type="number" id="productPrice" name="selling_price" class="form-input" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>

                            <div class="margin-calculation">
                                <div class="margin-info">
                                    <span class="margin-label">Margen de ganancia:</span>
                                    <span class="margin-value" id="marginValue">0%</span>
                                </div>
                                <div class="margin-info">
                                    <span class="margin-label">Ganancia por unidad:</span>
                                    <span class="margin-value" id="profitValue">S/ 0.00</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required">Stock Inicial</label>
                                    <input type="number" id="productStock" name="stock_quantity" class="form-input" 
                                           min="0" value="0" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock Mínimo</label>
                                    <input type="number" id="productMinStock" name="min_stock" class="form-input" 
                                           min="0" value="0">
                                    <small class="form-help">Se enviará alerta cuando el stock esté por debajo de este número</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeProductModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <span id="saveButtonText">Guardar Producto</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php includeJs('assets/js/app.js'); ?>
    
    <script>
        // Funciones para gestión de productos
        function openProductModal(productId = null) {
            const modal = document.getElementById('productModal');
            const title = document.getElementById('productModalTitle');
            const saveButtonText = document.getElementById('saveButtonText');
            
            if (productId) {
                title.textContent = 'Editar Producto';
                saveButtonText.textContent = 'Actualizar Producto';
                loadProductData(productId);
            } else {
                title.textContent = 'Nuevo Producto';
                saveButtonText.textContent = 'Guardar Producto';
                document.getElementById('productForm').reset();
                document.getElementById('productId').value = '';
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Calcular margen al cambiar precios
            setupMarginCalculation();
        }

        function closeProductModal() {
            const modal = document.getElementById('productModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function setupMarginCalculation() {
            const costInput = document.getElementById('productCost');
            const priceInput = document.getElementById('productPrice');
            
            function calculateMargin() {
                const cost = parseFloat(costInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                
                if (cost > 0 && price > 0) {
                    const profit = price - cost;
                    const margin = ((profit / price) * 100).toFixed(1);
                    
                    document.getElementById('marginValue').textContent = margin + '%';
                    document.getElementById('profitValue').textContent = 'S/ ' + profit.toFixed(2);
                } else {
                    document.getElementById('marginValue').textContent = '0%';
                    document.getElementById('profitValue').textContent = 'S/ 0.00';
                }
            }
            
            costInput.addEventListener('input', calculateMargin);
            priceInput.addEventListener('input', calculateMargin);
            
            // Calcular al cargar
            calculateMargin();
        }

        async function loadProductData(productId) {
            try {
                Messages.info('Cargando datos del producto...');
                
                // Simular datos del producto
                setTimeout(() => {
                    document.getElementById('productId').value = productId;
                    document.getElementById('productName').value = 'Producto de ejemplo';
                    document.getElementById('productCost').value = '10.00';
                    document.getElementById('productPrice').value = '15.00';
                    document.getElementById('productStock').value = '50';
                    
                    setupMarginCalculation();
                }, 500);
                
            } catch (error) {
                Messages.error('Error cargando datos del producto');
            }
        }

        function editProduct(productId) {
            openProductModal(productId);
        }

        function duplicateProduct(productId) {
            if (confirm('¿Duplicar este producto?')) {
                Messages.info('Funcionalidad en desarrollo');
            }
        }

        function deleteProduct(productId) {
            if (confirm('¿Estás seguro de eliminar este producto? Esta acción no se puede deshacer.')) {
                Messages.info('Funcionalidad en desarrollo');
            }
        }

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Cambiar vista entre grid y lista
            const viewButtons = document.querySelectorAll('.view-btn');
            const productsGrid = document.getElementById('productsGrid');
            const productsTable = document.getElementById('productsTable');
            
            viewButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.dataset.view;
                    
                    // Actualizar botones activos
                    viewButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Mostrar vista correspondiente
                    if (view === 'grid') {
                        productsGrid.style.display = 'grid';
                        productsTable.style.display = 'none';
                    } else {
                        productsGrid.style.display = 'none';
                        productsTable.style.display = 'block';
                    }
                });
            });

            // Manejo del formulario de producto
            document.getElementById('productForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = Forms.serialize(this);
                const isEdit = formData.product_id;
                
                try {
                    Messages.info(isEdit ? 'Actualizando producto...' : 'Guardando producto...');
                    
                    // Simular guardado
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    
                    Messages.success(isEdit ? 'Producto actualizado exitosamente' : 'Producto guardado exitosamente');
                    closeProductModal();
                    
                    // Recargar página para mostrar cambios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                    
                } catch (error) {
                    Messages.error('Error al guardar el producto');
                }
            });

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('productModal');
                    if (modal.classList.contains('show')) {
                        closeProductModal();
                    }
                }
            });

            // Cerrar modal clickeando fuera
            document.getElementById('productModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProductModal();
                }
            });
        });
    </script>
</body>
</html>