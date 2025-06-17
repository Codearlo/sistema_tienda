/**
 * PRODUCTS MANAGEMENT - JavaScript Completo
 * Gestión funcional de productos e inventario
 */

// Estado global de productos
const ProductsState = {
    products: [],
    categories: [],
    currentView: 'grid',
    filters: {
        search: '',
        category: '',
        stock: ''
    },
    editingProduct: null
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializeProducts();
});

function initializeProducts() {
    console.log('Inicializando productos...');
    
    // Usar datos del PHP
    if (typeof products !== 'undefined') {
        ProductsState.products = products;
    }
    if (typeof categories !== 'undefined') {
        ProductsState.categories = categories;
    }
    
    // Configurar eventos
    setupEventListeners();
    
    // Aplicar filtros iniciales
    applyFilters();
    
    console.log('Productos inicializados correctamente');
}

// ===== CONFIGURACIÓN DE EVENTOS =====
function setupEventListeners() {
    // Búsqueda
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Filtros
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', handleCategoryFilter);
    }
    
    const stockFilter = document.getElementById('stockFilter');
    if (stockFilter) {
        stockFilter.addEventListener('change', handleStockFilter);
    }
    
    // Formulario de producto
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', handleProductFormSubmit);
    }
    
    // Formulario de categoría
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', handleCategoryFormSubmit);
    }
    
    // Formulario de stock
    const stockForm = document.getElementById('stockForm');
    if (stockForm) {
        stockForm.addEventListener('submit', handleStockFormSubmit);
    }
    
    // Cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Cerrar modales clickeando fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
}

// ===== FUNCIONES DE FILTRADO =====
function handleSearch(e) {
    ProductsState.filters.search = e.target.value.toLowerCase();
    applyFilters();
}

function handleCategoryFilter(e) {
    ProductsState.filters.category = e.target.value;
    applyFilters();
}

function handleStockFilter(e) {
    ProductsState.filters.stock = e.target.value;
    applyFilters();
}

function clearFilters() {
    // Limpiar inputs
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('stockFilter').value = '';
    
    // Limpiar estado
    ProductsState.filters = {
        search: '',
        category: '',
        stock: ''
    };
    
    applyFilters();
}

function applyFilters() {
    let filteredProducts = [...ProductsState.products];
    
    // Filtro de búsqueda
    if (ProductsState.filters.search) {
        filteredProducts = filteredProducts.filter(product => 
            product.name.toLowerCase().includes(ProductsState.filters.search) ||
            (product.sku && product.sku.toLowerCase().includes(ProductsState.filters.search)) ||
            (product.barcode && product.barcode.toLowerCase().includes(ProductsState.filters.search))
        );
    }
    
    // Filtro de categoría
    if (ProductsState.filters.category) {
        filteredProducts = filteredProducts.filter(product => 
            product.category_id == ProductsState.filters.category
        );
    }
    
    // Filtro de stock
    if (ProductsState.filters.stock) {
        filteredProducts = filteredProducts.filter(product => {
            const stock = parseInt(product.stock_quantity) || 0;
            const minStock = parseInt(product.min_stock) || 0;
            
            switch (ProductsState.filters.stock) {
                case 'available':
                    return stock > 0;
                case 'low':
                    return stock <= minStock && stock > 0;
                case 'out':
                    return stock === 0;
                default:
                    return true;
            }
        });
    }
    
    renderProducts(filteredProducts);
    updateProductsCount(filteredProducts.length);
}

// ===== RENDERIZADO DE PRODUCTOS =====
function renderProducts(productsToShow) {
    if (ProductsState.currentView === 'grid') {
        renderProductsGrid(productsToShow);
    } else {
        renderProductsList(productsToShow);
    }
}

function renderProductsGrid(productsToShow) {
    const container = document.getElementById('productsGrid');
    if (!container) return;
    
    if (productsToShow.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No se encontraron productos</h3>
                <p>Intenta ajustar los filtros de búsqueda</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = productsToShow.map(product => `
        <div class="product-card" data-product-id="${product.id}">
            <div class="product-image">
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` : 
                    '<i class="fas fa-box"></i>'
                }
                ${(product.stock_quantity <= product.min_stock) ? 
                    '<span class="stock-badge low-stock">Stock Bajo</span>' : ''
                }
            </div>
            
            <div class="product-info">
                <h4 class="product-name">${product.name}</h4>
                <p class="product-category">${product.category_name || 'Sin categoría'}</p>
                
                <div class="product-price">
                    S/ ${parseFloat(product.selling_price).toFixed(2)}
                </div>
                
                <div class="product-stock ${(product.stock_quantity <= 5) ? 'low-stock' : ''}">
                    <i class="fas fa-cube"></i>
                    Stock: ${product.stock_quantity || 0}
                </div>
            </div>
            
            <div class="product-actions">
                <button class="btn btn-sm btn-primary" onclick="editProduct(${product.id})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-warning" onclick="adjustStock(${product.id})" title="Ajustar Stock">
                    <i class="fas fa-boxes"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteProduct(${product.id})" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function renderProductsList(productsToShow) {
    const tbody = document.querySelector('#productsList tbody');
    if (!tbody) return;
    
    if (productsToShow.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>No se encontraron productos</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = productsToShow.map(product => `
        <tr data-product-id="${product.id}">
            <td>
                <div class="product-info-table">
                    <div class="product-image-small">
                        ${product.image ? 
                            `<img src="${product.image}" alt="${product.name}">` : 
                            '<i class="fas fa-box"></i>'
                        }
                    </div>
                    <div>
                        <div class="product-name">${product.name}</div>
                        <div class="product-code">${product.sku || 'N/A'}</div>
                    </div>
                </div>
            </td>
            <td>${product.category_name || 'Sin categoría'}</td>
            <td>S/ ${parseFloat(product.selling_price).toFixed(2)}</td>
            <td>
                <span class="stock-display ${(product.stock_quantity <= 5) ? 'low-stock' : ''}">
                    ${product.stock_quantity || 0}
                </span>
            </td>
            <td>
                <span class="status-badge status-active">Activo</span>
            </td>
            <td>
                <div class="table-actions">
                    <button class="btn btn-sm btn-primary" onclick="editProduct(${product.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="adjustStock(${product.id})" title="Ajustar Stock">
                        <i class="fas fa-boxes"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function updateProductsCount(count) {
    const countElement = document.getElementById('productsCount');
    if (countElement) {
        countElement.textContent = `Total: ${count} productos`;
    }
}

// ===== GESTIÓN DE VISTAS =====
function setView(view) {
    ProductsState.currentView = view;
    
    const gridContainer = document.getElementById('productsGrid');
    const listContainer = document.getElementById('productsList');
    const gridBtn = document.querySelector('[data-view="grid"]');
    const listBtn = document.querySelector('[data-view="list"]');
    const viewToggleIcon = document.getElementById('viewToggleIcon');
    
    if (view === 'grid') {
        gridContainer.style.display = 'grid';
        listContainer.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        if (viewToggleIcon) {
            viewToggleIcon.className = 'fas fa-list';
        }
    } else {
        gridContainer.style.display = 'none';
        listContainer.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
        if (viewToggleIcon) {
            viewToggleIcon.className = 'fas fa-th';
        }
    }
    
    applyFilters();
}

function toggleView() {
    const newView = ProductsState.currentView === 'grid' ? 'list' : 'grid';
    setView(newView);
}

// ===== GESTIÓN DE PRODUCTOS =====
function openProductModal(productId = null) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');
    
    if (productId) {
        // Editar producto existente
        const product = ProductsState.products.find(p => p.id == productId);
        if (!product) {
            showMessage('Producto no encontrado', 'error');
            return;
        }
        
        modalTitle.textContent = 'Editar Producto';
        fillProductForm(product);
        ProductsState.editingProduct = productId;
    } else {
        // Nuevo producto
        modalTitle.textContent = 'Nuevo Producto';
        form.reset();
        ProductsState.editingProduct = null;
    }
    
    modal.style.display = 'flex';
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    modal.style.display = 'none';
    ProductsState.editingProduct = null;
}

function fillProductForm(product) {
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productCategory').value = product.category_id || '';
    document.getElementById('productSku').value = product.sku || '';
    document.getElementById('productBarcode').value = product.barcode || '';
    document.getElementById('productCost').value = product.cost_price || '';
    document.getElementById('productPrice').value = product.selling_price;
    document.getElementById('productStock').value = product.stock_quantity || '';
    document.getElementById('productMinStock').value = product.min_stock || '';
    document.getElementById('productDescription').value = product.description || '';
}

function editProduct(productId) {
    openProductModal(productId);
}

async function saveProduct(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const productData = Object.fromEntries(formData.entries());
    
    try {
        const url = ProductsState.editingProduct ? 
            `backend/api/productos.php?id=${ProductsState.editingProduct}` : 
            'backend/api/productos.php';
        
        const method = ProductsState.editingProduct ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(productData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(ProductsState.editingProduct ? 'Producto actualizado' : 'Producto creado', 'success');
            closeProductModal();
            location.reload(); // Recargar para mostrar cambios
        } else {
            showMessage(result.message || 'Error al guardar producto', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

function handleProductFormSubmit(event) {
    saveProduct(event);
}

async function deleteProduct(productId) {
    if (!confirm('¿Estás seguro de que deseas eliminar este producto?')) {
        return;
    }
    
    try {
        const response = await fetch(`backend/api/productos.php?id=${productId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Producto eliminado', 'success');
            location.reload();
        } else {
            showMessage(result.message || 'Error al eliminar producto', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

// ===== GESTIÓN DE CATEGORÍAS =====
function openCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.style.display = 'flex';
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.style.display = 'none';
}

async function saveCategory(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const categoryData = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('backend/api/index.php?endpoint=categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(categoryData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Categoría creada', 'success');
            document.getElementById('categoryForm').reset();
            location.reload();
        } else {
            showMessage(result.message || 'Error al crear categoría', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

function handleCategoryFormSubmit(event) {
    saveCategory(event);
}

async function deleteCategory(categoryId) {
    if (!confirm('¿Estás seguro de que deseas eliminar esta categoría?')) {
        return;
    }
    
    try {
        const response = await fetch(`backend/api/index.php?endpoint=categories&id=${categoryId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Categoría eliminada', 'success');
            location.reload();
        } else {
            showMessage(result.message || 'Error al eliminar categoría', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

// ===== GESTIÓN DE STOCK =====
function adjustStock(productId) {
    const product = ProductsState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    const modal = document.getElementById('stockModal');
    document.getElementById('stockProductId').value = productId;
    document.getElementById('stockProductName').textContent = product.name;
    document.getElementById('currentStock').textContent = product.stock_quantity || 0;
    document.getElementById('stockQuantity').value = '';
    document.getElementById('adjustmentReason').value = '';
    
    modal.style.display = 'flex';
}

function closeStockModal() {
    const modal = document.getElementById('stockModal');
    modal.style.display = 'none';
}

function updateAdjustmentType() {
    const type = document.getElementById('adjustmentType').value;
    const label = document.getElementById('quantityLabel');
    
    switch (type) {
        case 'add':
            label.textContent = 'Cantidad a agregar';
            break;
        case 'remove':
            label.textContent = 'Cantidad a reducir';
            break;
        case 'set':
            label.textContent = 'Nuevo stock total';
            break;
    }
}

async function adjustStockSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const stockData = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('backend/api/index.php?endpoint=stock', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(stockData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Stock ajustado correctamente', 'success');
            closeStockModal();
            location.reload();
        } else {
            showMessage(result.message || 'Error al ajustar stock', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

function handleStockFormSubmit(event) {
    adjustStockSubmit(event);
}

// ===== UTILIDADES =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showMessage(message, type = 'info') {
    // Crear elemento de mensaje
    const messageEl = document.createElement('div');
    messageEl.className = `alert alert-${type} message-toast`;
    messageEl.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
        ${message}
    `;
    
    // Agregar al documento
    document.body.appendChild(messageEl);
    
    // Animar entrada
    setTimeout(() => {
        messageEl.classList.add('show');
    }, 100);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        messageEl.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(messageEl);
        }, 300);
    }, 3000);
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    ProductsState.editingProduct = null;
}