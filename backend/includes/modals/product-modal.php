 <!-- Modal de Producto -->
<div class="modal-overlay" id="productModal">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3 class="modal-title" id="productModalTitle">Nuevo Producto</h3>
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="productForm" action="backend/api/products.php" method="POST">
                <input type="hidden" id="productId" name="product_id">
                
                <div class="form-grid">
                    <div class="form-section">
                        <h4 class="form-section-title">Información Básica</h4>
                        
                        <div class="form-group">
                            <label class="form-label required">Nombre del Producto</label>
                            <input type="text" id="productName" name="name" class="form-input" required placeholder="Ej: Coca Cola 500ml">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">SKU</label>
                                <input type="text" id="productSku" name="sku" class="form-input" placeholder="Ej: COC-500-001">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Código de Barras</label>
                                <input type="text" id="productBarcode" name="barcode" class="form-input" placeholder="Ej: 7501234567890">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descripción</label>
                            <textarea id="productDescription" name="description" class="form-input" rows="3" placeholder="Descripción detallada del producto..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select id="productCategory" name="category_id" class="form-input">
                                <option value="">Seleccionar categoría</option>
                                <?php if (isset($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4 class="form-section-title">Precios y Stock</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Precio de Costo</label>
                                <input type="number" id="productCostPrice" name="cost_price" class="form-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Precio de Venta</label>
                                <input type="number" id="productSellingPrice" name="selling_price" class="form-input" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Precio Mayorista</label>
                            <input type="number" id="productWholesalePrice" name="wholesale_price" class="form-input" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Stock Inicial</label>
                                <input type="number" id="productStock" name="stock_quantity" class="form-input" min="0" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" id="productMinStock" name="min_stock" class="form-input" min="0" placeholder="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Unidad</label>
                                <select id="productUnit" name="unit" class="form-input">
                                    <option value="unit">Unidad</option>
                                    <option value="kg">Kilogramo</option>
                                    <option value="g">Gramo</option>
                                    <option value="l">Litro</option>
                                    <option value="ml">Mililitro</option>
                                    <option value="m">Metro</option>
                                    <option value="cm">Centímetro</option>
                                    <option value="box">Caja</option>
                                    <option value="pack">Paquete</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="productTrackStock" name="track_stock" class="checkbox" checked>
                                    <span class="checkmark"></span>
                                    Controlar Stock
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section full-width">
                        <h4 class="form-section-title">Imagen del Producto</h4>
                        
                        <div class="image-upload-area" id="imageUploadArea">
                            <div class="image-preview" id="imagePreview">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21,15 16,10 5,21"/>
                                </svg>
                                <p>Clic para subir imagen</p>
                                <span class="upload-hint">JPG, PNG o GIF. Máximo 2MB</span>
                            </div>
                            <input type="file" id="productImage" name="image" accept="image/*" class="hidden">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-gray" onclick="closeProductModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="productSubmitBtn">
                        <span class="btn-text">Guardar Producto</span>
                        <div class="btn-loading hidden">
                            <svg class="loading-spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                    <animate attributeName="stroke-dashoffset" dur="2s" values="31.416;0" repeatCount="indefinite"/>
                                </circle>
                            </svg>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Manejo del formulario de producto
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    data.track_stock = document.getElementById('productTrackStock').checked;
    
    const productId = document.getElementById('productId').value;
    const method = productId ? 'PUT' : 'POST';
    const url = productId ? `backend/api/products.php?id=${productId}` : 'backend/api/products.php';
    
    try {
        showButtonLoading('productSubmitBtn', true);
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            Notifications.success(result.message);
            closeProductModal();
            loadProducts(); // Recargar lista
        } else {
            Notifications.error(result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Notifications.error('Error al guardar el producto');
    } finally {
        showButtonLoading('productSubmitBtn', false);
    }
});

function showButtonLoading(buttonId, show) {
    const btn = document.getElementById(buttonId);
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    btn.disabled = show;
    if (show) {
        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
    } else {
        btnText.classList.remove('hidden');
        btnLoading.classList.add('hidden');
    }
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('show');
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productModalTitle').textContent = 'Nuevo Producto';
    document.body.style.overflow = '';
}

function openProductModal(productId = null) {
    const modal = document.getElementById('productModal');
    const title = document.getElementById('productModalTitle');
    
    if (productId) {
        title.textContent = 'Editar Producto';
        loadProductData(productId);
    } else {
        title.textContent = 'Nuevo Producto';
        document.getElementById('productForm').reset();
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

async function loadProductData(productId) {
    try {
        const response = await fetch(`backend/api/products.php?id=${productId}`);
        const data = await response.json();
        
        if (data.success && data.product) {
            const product = data.product;
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productSku').value = product.sku || '';
            document.getElementById('productBarcode').value = product.barcode || '';
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productCostPrice').value = product.cost_price;
            document.getElementById('productSellingPrice').value = product.selling_price;
            document.getElementById('productWholesalePrice').value = product.wholesale_price || '';
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productMinStock').value = product.min_stock;
            document.getElementById('productUnit').value = product.unit;
            document.getElementById('productTrackStock').checked = product.track_stock == 1;
        }
    } catch (error) {
        console.error('Error cargando producto:', error);
        Notifications.error('Error al cargar datos del producto');
    }
}
</script>