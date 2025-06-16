<?php
session_start();

// Incluir middleware de onboarding
require_once 'includes/onboarding_middleware.php';

// Verificar que el usuario haya completado el onboarding
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Función para formatear moneda
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

$error_message = null;
$success_message = null;

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Procesar formulario de nuevo producto
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_product'])) {
        $name = trim($_POST['name']);
        $sku = trim($_POST['sku']);
        $category_id = $_POST['category_id'];
        $cost_price = floatval($_POST['cost_price']);
        $sale_price = floatval($_POST['sale_price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $min_stock = intval($_POST['min_stock']);
        $description = trim($_POST['description']);
        
        if (empty($name) || empty($sale_price)) {
            $error_message = "El nombre y precio de venta son requeridos";
        } else {
            // Verificar SKU único
            if (!empty($sku)) {
                $existing = $db->single("SELECT id FROM products WHERE sku = ? AND business_id = ? AND status = 1", [$sku, $business_id]);
                if ($existing) {
                    $error_message = "El SKU ya existe";
                }
            }
            
            if (!$error_message) {
                $product_data = [
                    'business_id' => $business_id,
                    'name' => $name,
                    'sku' => $sku,
                    'category_id' => $category_id ?: null,
                    'description' => $description,
                    'cost_price' => $cost_price,
                    'sale_price' => $sale_price,
                    'stock_quantity' => $stock_quantity,
                    'min_stock' => $min_stock,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $product_id = $db->insert('products', $product_data);
                
                if ($product_id) {
                    // Registrar movimiento de inventario inicial
                    if ($stock_quantity > 0) {
                        $db->insert('inventory_movements', [
                            'business_id' => $business_id,
                            'product_id' => $product_id,
                            'movement_type' => 'in',
                            'quantity' => $stock_quantity,
                            'unit_cost' => $cost_price,
                            'total_cost' => $cost_price * $stock_quantity,
                            'reason' => 'Stock inicial',
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $success_message = "Producto creado exitosamente";
                } else {
                    $error_message = "Error al crear el producto";
                }
            }
        }
    }
    
    // Procesar actualización de producto
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
        $product_id = intval($_POST['product_id']);
        $name = trim($_POST['name']);
        $sku = trim($_POST['sku']);
        $category_id = $_POST['category_id'];
        $cost_price = floatval($_POST['cost_price']);
        $sale_price = floatval($_POST['sale_price']);
        $min_stock = intval($_POST['min_stock']);
        $description = trim($_POST['description']);
        
        if (empty($name) || empty($sale_price)) {
            $error_message = "El nombre y precio de venta son requeridos";
        } else {
            // Verificar que el producto pertenezca al negocio
            $product = $db->single("SELECT * FROM products WHERE id = ? AND business_id = ?", [$product_id, $business_id]);
            if (!$product) {
                $error_message = "Producto no encontrado";
            } else {
                // Verificar SKU único (excluyendo el producto actual)
                if (!empty($sku)) {
                    $existing = $db->single("SELECT id FROM products WHERE sku = ? AND business_id = ? AND id != ? AND status = 1", [$sku, $business_id, $product_id]);
                    if ($existing) {
                        $error_message = "El SKU ya existe";
                    }
                }
                
                if (!$error_message) {
                    $update_data = [
                        'name' => $name,
                        'sku' => $sku,
                        'category_id' => $category_id ?: null,
                        'description' => $description,
                        'cost_price' => $cost_price,
                        'sale_price' => $sale_price,
                        'min_stock' => $min_stock,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $result = $db->update('products', $update_data, 'id = ?', [$product_id]);
                    
                    if ($result) {
                        $success_message = "Producto actualizado exitosamente";
                    } else {
                        $error_message = "Error al actualizar el producto";
                    }
                }
            }
        }
    }
    
    // Procesar eliminación de producto
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
        $product_id = intval($_POST['product_id']);
        
        $product = $db->single("SELECT * FROM products WHERE id = ? AND business_id = ?", [$product_id, $business_id]);
        if ($product) {
            // Soft delete
            $result = $db->update('products', ['status' => 0], 'id = ?', [$product_id]);
            
            if ($result) {
                $success_message = "Producto eliminado exitosamente";
            } else {
                $error_message = "Error al eliminar el producto";
            }
        } else {
            $error_message = "Producto no encontrado";
        }
    }
    
    // Cargar categorías
    $categories = $db->fetchAll("SELECT * FROM categories WHERE business_id = ? AND status = 1 ORDER BY name", [$business_id]);
    
    // Cargar productos con filtros
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $stock_filter = $_GET['stock'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $per_page = 20;
    
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
    
    // Obtener productos con paginación
    $offset = ($page - 1) * $per_page;
    $products_sql = "
        SELECT p.*, c.name as category_name, c.color as category_color
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $where
        ORDER BY p.name ASC
        LIMIT $offset, $per_page
    ";
    
    $products = $db->fetchAll($products_sql, $params);
    
    // Contar total para paginación
    $total_sql = "
        SELECT COUNT(*) as total
        FROM products p
        WHERE $where
    ";
    $total_result = $db->single($total_sql, $params);
    $total_products = $total_result['total'];
    $total_pages = ceil($total_products / $per_page);
    
    // Estadísticas
    $stats = $db->single("
        SELECT 
            COUNT(*) as total_products,
            COALESCE(SUM(cost_price * stock_quantity), 0) as inventory_value,
            SUM(CASE WHEN stock_quantity <= min_stock AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM products 
        WHERE business_id = ? AND status = 1
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
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-cash-register"></i>
                    <span>Treinta</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="pos.php" class="nav-item">
                    <i class="fas fa-cash-register"></i>
                    <span>Punto de Venta</span>
                </a>
                <a href="products.php" class="nav-item active">
                    <i class="fas fa-box"></i>
                    <span>Productos</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="sales.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Ventas</span>
                </a>
                <a href="expenses.php" class="nav-item">
                    <i class="fas fa-receipt"></i>
                    <span>Gastos</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-box"></i> Gestión de Productos</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i> Nuevo Producto
                    </button>
                    <button class="btn btn-secondary" onclick="exportProducts()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_products']); ?></h3>
                        <p>Total Productos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatCurrency($stats['inventory_value']); ?></h3>
                        <p>Valor del Inventario</p>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Stock Bajo</p>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['out_of_stock']; ?></h3>
                        <p>Sin Stock</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="search">Buscar:</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nombre o SKU del producto">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Categoría:</label>
                        <select id="category" name="category">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="stock">Stock:</label>
                        <select id="stock" name="stock">
                            <option value="">Todos</option>
                            <option value="normal" <?php echo $stock_filter === 'normal' ? 'selected' : ''; ?>>Stock Normal</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Stock Bajo</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Sin Stock</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Productos -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Categoría</th>
                            <th>Precio Costo</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Estado Stock</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-box-open"></i>
                                    <p>No se encontraron productos</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="product-image">
                                        <?php if ($product['image_url']): ?>
                                            <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-box"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <?php if ($product['description']): ?>
                                            <br><small><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo strlen($product['description']) > 50 ? '...' : ''; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                            <span class="category-badge" style="background-color: <?php echo $product['category_color'] ?: '#6c757d'; ?>">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin categoría</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatCurrency($product['cost_price']); ?></td>
                                    <td><strong><?php echo formatCurrency($product['sale_price']); ?></strong></td>
                                    <td>
                                        <span class="stock-quantity"><?php echo $product['stock_quantity']; ?></span>
                                        <small class="text-muted">/ Min: <?php echo $product['min_stock']; ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $stock_status = 'normal';
                                        $stock_text = 'Normal';
                                        $stock_icon = 'check-circle';
                                        
                                        if ($product['stock_quantity'] == 0) {
                                            $stock_status = 'danger';
                                            $stock_text = 'Sin Stock';
                                            $stock_icon = 'times-circle';
                                        } elseif ($product['stock_quantity'] <= $product['min_stock']) {
                                            $stock_status = 'warning';
                                            $stock_text = 'Stock Bajo';
                                            $stock_icon = 'exclamation-triangle';
                                        }
                                        ?>
                                        <span class="status-badge status-<?php echo $stock_status; ?>">
                                            <i class="fas fa-<?php echo $stock_icon; ?>"></i>
                                            <?php echo $stock_text; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-sm btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="openStockModal(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-boxes"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = 'products.php?' . http_build_query($query_params);
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                        (<?php echo $total_products; ?> productos)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal Crear/Editar Producto -->
    <div id="product-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </h3>
                <span class="close" onclick="closeModal('product-modal')">&times;</span>
            </div>
            
            <form id="product-form" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="product_id" name="product_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="product_name">Nombre del Producto *</label>
                            <input type="text" id="product_name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_sku">SKU (Código)</label>
                            <input type="text" id="product_sku" name="sku" placeholder="Opcional">
                        </div>
                        
                        <div class="form-group">
                            <label for="product_category">Categoría</label>
                            <select id="product_category" name="category_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_cost_price">Precio de Costo</label>
                            <input type="number" id="product_cost_price" name="cost_price" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="product_sale_price">Precio de Venta *</label>
                            <input type="number" id="product_sale_price" name="sale_price" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group" id="stock_group">
                            <label for="product_stock">Stock Inicial</label>
                            <input type="number" id="product_stock" name="stock_quantity" min="0" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="product_min_stock">Stock Mínimo</label>
                            <input type="number" id="product_min_stock" name="min_stock" min="0" value="5">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="product_description">Descripción</label>
                            <textarea id="product_description" name="description" rows="3" placeholder="Descripción del producto (opcional)"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="product_image">Imagen del Producto</label>
                            <input type="file" id="product_image" name="image" accept="image/*">
                            <small>Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('product-modal')">
                        Cancelar
                    </button>
                    <button type="submit" id="submit-btn" name="create_product" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gestión de Stock -->
    <div id="stock-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-boxes"></i> Gestión de Stock</h3>
                <span class="close" onclick="closeModal('stock-modal')">&times;</span>
            </div>
            
            <div class="modal-body">
                <div id="stock-product-info"></div>
                
                <form id="stock-form" method="POST">
                    <input type="hidden" id="stock_product_id" name="product_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="movement_type">Tipo de Movimiento</label>
                            <select id="movement_type" name="movement_type" required>
                                <option value="in">Entrada (Agregar Stock)</option>
                                <option value="out">Salida (Quitar Stock)</option>
                                <option value="adjustment">Ajuste de Inventario</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Cantidad</label>
                            <input type="number" id="quantity" name="quantity" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="unit_cost">Costo Unitario</label>
                            <input type="number" id="unit_cost" name="unit_cost" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="reason">Motivo</label>
                            <input type="text" id="reason" name="reason" placeholder="Motivo del movimiento" required>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('stock-modal')">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="processStockMovement()">
                    <i class="fas fa-save"></i> Procesar Movimiento
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div id="delete-modal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
                <span class="close" onclick="closeModal('delete-modal')">&times;</span>
            </div>
            
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar el producto <strong id="delete-product-name"></strong>?</p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
                
                <form id="delete-form" method="POST">
                    <input type="hidden" id="delete_product_id" name="product_id">
                    <input type="hidden" name="delete_product" value="1">
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-modal')">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('delete-form').submit()">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let currentProductId = null;
        
        // Abrir modal de creación
        function openCreateModal() {
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus"></i> Nuevo Producto';
            document.getElementById('product-form').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('submit-btn').name = 'create_product';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Guardar Producto';
            document.getElementById('stock_group').style.display = 'block';
            document.getElementById('product-modal').style.display = 'block';
        }
        
        // Abrir modal de edición
        function openEditModal(product) {
            document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit"></i> Editar Producto';
            document.getElementById('product_id').value = product.id;
            document.getElementById('product_name').value = product.name;
            document.getElementById('product_sku').value = product.sku || '';
            document.getElementById('product_category').value = product.category_id || '';
            document.getElementById('product_cost_price').value = product.cost_price;
            document.getElementById('product_sale_price').value = product.sale_price;
            document.getElementById('product_min_stock').value = product.min_stock;
            document.getElementById('product_description').value = product.description || '';
            document.getElementById('submit-btn').name = 'update_product';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Actualizar Producto';
            document.getElementById('stock_group').style.display = 'none';
            document.getElementById('product-modal').style.display = 'block';
        }
        
        // Abrir modal de stock
        function openStockModal(productId) {
            currentProductId = productId;
            
            // Cargar información del producto
            fetch(`api/products/get.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        document.getElementById('stock-product-info').innerHTML = `
                            <div class="product-info">
                                <h4>${product.name}</h4>
                                <p>Stock actual: <strong>${product.stock_quantity}</strong> unidades</p>
                                <p>Stock mínimo: ${product.min_stock} unidades</p>
                            </div>
                        `;
                        document.getElementById('stock_product_id').value = productId;
                        document.getElementById('unit_cost').value = product.cost_price;
                        document.getElementById('stock-modal').style.display = 'block';
                    }
                });
        }
        
        // Procesar movimiento de stock
        function processStockMovement() {
            const form = document.getElementById('stock-form');
            const formData = new FormData(form);
            
            fetch('api/inventory/movement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Movimiento de stock procesado exitosamente');
                    closeModal('stock-modal');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error de conexión');
            });
        }
        
        // Confirmar eliminación
        function confirmDelete(productId, productName) {
            document.getElementById('delete-product-name').textContent = productName;
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('delete-modal').style.display = 'block';
        }
        
        // Cerrar modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Exportar productos
        function exportProducts() {
            const params = new URLSearchParams(window.location.search);
            const exportUrl = 'api/products/export.php?' + params.toString();
            window.open(exportUrl, '_blank');
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modals = ['product-modal', 'stock-modal', 'delete-modal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
        
        // Auto-submit en filtros
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const categorySelect = document.getElementById('category');
            const stockSelect = document.getElementById('stock');
            
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
            
            categorySelect.addEventListener('change', function() {
                this.form.submit();
            });
            
            stockSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Validación de formulario
        document.getElementById('product-form').addEventListener('submit', function(e) {
            const name = document.getElementById('product_name').value.trim();
            const salePrice = parseFloat(document.getElementById('product_sale_price').value);
            
            if (!name) {
                e.preventDefault();
                alert('El nombre del producto es requerido');
                return;
            }
            
            if (!salePrice || salePrice <= 0) {
                e.preventDefault();
                alert('El precio de venta debe ser mayor a 0');
                return;
            }
        });
    </script>

    <?php includeJs('assets/js/common.js'); ?>
</body>
</html>