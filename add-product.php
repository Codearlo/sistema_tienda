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
$success_message = null;
$categories = [];
$editing_product = null;
$is_edit_mode = false;

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Verificar si estamos editando un producto
    if (isset($_GET['edit']) && !empty($_GET['edit'])) {
        $product_id = intval($_GET['edit']);
        $editing_product = $db->single("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.business_id = ? AND p.status = 1
        ", [$product_id, $business_id]);
        
        if ($editing_product) {
            $is_edit_mode = true;
        } else {
            $error_message = "Producto no encontrado.";
        }
    }
    
    // Cargar categorías
    $categories = $db->fetchAll("
        SELECT * FROM categories 
        WHERE business_id = ? AND status = 1 
        ORDER BY name
    ", [$business_id]);
    
    // Manejar envío del formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $sku = trim($_POST['sku'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $min_stock = intval($_POST['min_stock'] ?? 0);
        
        // Validaciones básicas
        if (empty($name)) {
            $error_message = "El nombre del producto es requerido.";
        } elseif ($selling_price <= 0) {
            $error_message = "El precio de venta debe ser mayor a 0.";
        } else {
            try {
                if ($is_edit_mode) {
                    // Actualizar producto existente
                    $updateData = [
                        'name' => $name,
                        'description' => $description,
                        'category_id' => $category_id,
                        'sku' => $sku ?: $editing_product['sku'],
                        'barcode' => $barcode,
                        'cost_price' => $cost_price,
                        'selling_price' => $selling_price,
                        'min_stock' => $min_stock,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Solo actualizar stock si cambió y hay diferencia
                    if ($stock_quantity != $editing_product['stock_quantity']) {
                        $updateData['stock_quantity'] = $stock_quantity;
                        
                        // Registrar movimiento de inventario
                        $difference = $stock_quantity - $editing_product['stock_quantity'];
                        $movement_type = $difference > 0 ? 'in' : 'out';
                        $reason = $difference > 0 ? 'Ajuste de inventario (aumento)' : 'Ajuste de inventario (reducción)';
                        
                        $db->insert('inventory_movements', [
                            'business_id' => $business_id,
                            'product_id' => $editing_product['id'],
                            'movement_type' => $movement_type,
                            'quantity' => abs($difference),
                            'reason' => $reason,
                            'user_id' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $db->update('products', $updateData, "id = ? AND business_id = ?", [$editing_product['id'], $business_id]);
                    
                    $success_message = "Producto actualizado exitosamente.";
                } else {
                    // Crear nuevo producto
                    // Generar SKU automático si no se proporciona
                    if (empty($sku)) {
                        $sku = 'PROD' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    }
                    
                    // Verificar SKU único
                    $existing = $db->single(
                        "SELECT id FROM products WHERE sku = ? AND business_id = ?",
                        [$sku, $business_id]
                    );
                    
                    if ($existing) {
                        $sku = 'PROD' . time(); // Usar timestamp si existe
                    }
                    
                    // Insertar producto
                    $productData = [
                        'business_id' => $business_id,
                        'name' => $name,
                        'description' => $description,
                        'category_id' => $category_id,
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'cost_price' => $cost_price,
                        'selling_price' => $selling_price,
                        'stock_quantity' => $stock_quantity,
                        'min_stock' => $min_stock,
                        'track_stock' => 1,
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $productId = $db->insert('products', $productData);
                    
                    // Registrar movimiento de inventario inicial
                    if ($stock_quantity > 0) {
                        $db->insert('inventory_movements', [
                            'business_id' => $business_id,
                            'product_id' => $productId,
                            'movement_type' => 'in',
                            'quantity' => $stock_quantity,
                            'reason' => 'Stock inicial',
                            'user_id' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    $success_message = "Producto agregado exitosamente.";
                }
                
                // Limpiar formulario después del éxito (solo para nuevo producto)
                if (!$is_edit_mode) {
                    $_POST = [];
                }
                
            } catch (Exception $e) {
                $error_message = "Error al guardar el producto: " . $e->getMessage();
            }
        }
    }
    
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
    <title>Agregar Producto - Treinta</title>
    <?php 
    forceCssReload();
    includeCss('assets/css/style.css');
    includeCss('assets/css/layouts/add-product.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <nav class="breadcrumb">
            <a href="dashboard.php" class="breadcrumb-link">
                <i class="fas fa-home"></i>
            </a>
            <span class="breadcrumb-separator">/</span>
            <a href="products.php" class="breadcrumb-link">Productos</a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">Agregar Producto</span>
        </nav>

        <header class="page-header">
            <div class="header-content">
                <h1 class="page-title"><?php echo $is_edit_mode ? 'Editar Producto' : 'Agregar Producto'; ?></h1>
                <p class="page-subtitle"><?php echo $is_edit_mode ? 'Modifica la información de tu producto' : 'Agrega tu producto para tus clientes'; ?></p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-outline" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </button>
                <?php if (!$is_edit_mode): ?>
                <button type="button" class="btn btn-primary" onclick="bulkUpload()">
                    <i class="fas fa-upload"></i>
                    Carga Masiva
                </button>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="product-form" id="productForm">
            <?php if ($is_edit_mode): ?>
                <input type="hidden" name="product_id" value="<?php echo $editing_product['id']; ?>">
            <?php endif; ?>
            <div class="form-grid">
                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Información Básica
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label for="name" class="form-label">Nombre del Producto *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input" 
                                   placeholder="Ingresa el nombre del producto"
                                   value="<?php echo htmlspecialchars($editing_product['name'] ?? $_POST['name'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-textarea" 
                                      rows="4"
                                      placeholder="Describe las características de tu producto"><?php echo htmlspecialchars($editing_product['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-images"></i>
                            Imagen del Producto
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="image-upload-area">
                            <div class="image-previews" id="imagePreviews">
                                </div>
                            <div class="upload-placeholder" onclick="triggerImageUpload()">
                                <i class="fas fa-plus"></i>
                                <span>Agregar Imagen</span>
                            </div>
                            <input type="file" 
                                   id="imageUpload" 
                                   name="images[]" 
                                   multiple 
                                   accept="image/*" 
                                   style="display: none;" 
                                   onchange="handleImageUpload(this)">
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-tags"></i>
                            Categoría
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label for="category_id" class="form-label">Categoría del Producto</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                            <?php 
                                            $selected_category = $editing_product['category_id'] ?? $_POST['category_id'] ?? '';
                                            echo ($selected_category == $category['id']) ? 'selected' : ''; 
                                            ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openCategoryModal()">
                                <i class="fas fa-plus"></i>
                                Nueva Categoría
                            </button>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-dollar-sign"></i>
                            Precios
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label for="cost_price" class="form-label">Precio de Costo</label>
                            <div class="input-group">
                                <span class="input-prefix">S/</span>
                                <input type="number" 
                                       id="cost_price" 
                                       name="cost_price" 
                                       class="form-input" 
                                       placeholder="0.00"
                                       step="0.01"
                                       min="0"
                                       value="<?php echo $editing_product['cost_price'] ?? $_POST['cost_price'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="selling_price" class="form-label">Precio de Venta *</label>
                            <div class="input-group">
                                <span class="input-prefix">S/</span>
                                <input type="number" 
                                       id="selling_price" 
                                       name="selling_price" 
                                       class="form-input" 
                                       placeholder="0.00"
                                       step="0.01"
                                       min="0"
                                       value="<?php echo $editing_product['selling_price'] ?? $_POST['selling_price'] ?? ''; ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="profit-margin" id="profitMargin" style="display: none;">
                            <span class="margin-label">Margen de ganancia:</span>
                            <span class="margin-value" id="marginValue">0%</span>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-boxes"></i>
                            Inventario
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" 
                                   id="sku" 
                                   name="sku" 
                                   class="form-input" 
                                   placeholder="Se generará automáticamente"
                                   value="<?php echo htmlspecialchars($editing_product['sku'] ?? $_POST['sku'] ?? ''); ?>">
                            <small class="form-help">Código único del producto (se genera automáticamente si se deja vacío)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="barcode" class="form-label">Código de Barras</label>
                            <input type="text" 
                                   id="barcode" 
                                   name="barcode" 
                                   class="form-input" 
                                   placeholder="Escanea o ingresa el código"
                                   value="<?php echo htmlspecialchars($editing_product['barcode'] ?? $_POST['barcode'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="stock_quantity" class="form-label">Stock Inicial</label>
                                <input type="number" 
                                       id="stock_quantity" 
                                       name="stock_quantity" 
                                       class="form-input" 
                                       placeholder="0"
                                       min="0"
                                       value="<?php echo $editing_product['stock_quantity'] ?? $_POST['stock_quantity'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="min_stock" class="form-label">Stock Mínimo</label>
                                <input type="number" 
                                       id="min_stock" 
                                       name="min_stock" 
                                       class="form-input" 
                                       placeholder="0"
                                       min="0"
                                       value="<?php echo $editing_product['min_stock'] ?? $_POST['min_stock'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-cog"></i>
                            Opciones Adicionales
                        </h2>
                        <button type="button" class="section-menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    
                    <div class="section-content">
                        <div class="form-group">
                            <label class="form-label">Configuración</label>
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="track_stock" value="1" checked>
                                    <span class="checkmark"></span>
                                    Rastrear inventario
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    <span class="checkmark"></span>
                                    Producto activo
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tags" class="form-label">Etiquetas</label>
                            <input type="text" 
                                   id="tags" 
                                   name="tags" 
                                   class="form-input" 
                                   placeholder="Ej: nuevo, promoción, popular"
                                   value="<?php echo htmlspecialchars($editing_product['tags'] ?? $_POST['tags'] ?? ''); ?>">
                            <small class="form-help">Separa las etiquetas con comas</small>
                        </div>
                    </div>
                </section>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="window.history.back()">
                    Cancelar
                </button>
                <button type="button" class="btn btn-outline" onclick="saveDraft()">
                    <i class="fas fa-save"></i>
                    Guardar Borrador
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas <?php echo $is_edit_mode ? 'fa-save' : 'fa-plus'; ?>"></i>
                    <?php echo $is_edit_mode ? 'Actualizar Producto' : 'Agregar Producto'; ?>
                </button>
            </div>
        </form>    Agregar Producto
                </button>
            </div>
        </form>
    </main>

    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nueva Categoría</h3>
                <button class="modal-close" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="categoryForm" onsubmit="saveCategory(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="categoryName">Nombre de la Categoría</label>
                        <input type="text" id="categoryName" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="categoryDescription">Descripción</label>
                        <textarea id="categoryDescription" name="description" class="form-input" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeCategoryModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Crear Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php includeJs('assets/js/add-product.js'); ?>
    
    <script>
        // Calcular margen de ganancia
        function calculateProfitMargin() {
            const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
            const sellingPrice = parseFloat(document.getElementById('selling_price').value) || 0;
            
            if (costPrice > 0 && sellingPrice > 0) {
                const margin = ((sellingPrice - costPrice) / costPrice * 100).toFixed(1);
                document.getElementById('marginValue').textContent = margin + '%';
                document.getElementById('profitMargin').style.display = 'block';
            } else {
                document.getElementById('profitMargin').style.display = 'none';
            }
        }
        
        // Event listeners para cálculo de margen
        document.getElementById('cost_price').addEventListener('input', calculateProfitMargin);
        document.getElementById('selling_price').addEventListener('input', calculateProfitMargin);
        
        // Calcular al cargar la página
        document.addEventListener('DOMContentLoaded', calculateProfitMargin);
    </script>
</body>
</html>