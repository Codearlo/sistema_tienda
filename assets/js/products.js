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
        productForm.addEventListener('submit', handleProductSubmit);
    }
}

// ===== MANEJO DE FILTROS =====
function handleSearch() {
    const searchInput = document.getElementById('searchInput');
    ProductsState.filters.search = searchInput.value.toLowerCase();
    applyFilters();
}

function handleCategoryFilter() {
    const categoryFilter = document.getElementById('categoryFilter');
    ProductsState.filters.category = categoryFilter.value;
    applyFilters();
}

function handleStockFilter() {
    const stockFilter = document.getElementById('stockFilter');
    ProductsState.filters.stock = stockFilter.value;
    applyFilters();
}

function clearFilters() {
    // Limpiar valores
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('stockFilter').value = '';
    
    // Resetear filtros
    ProductsState.filters = {
        search: '',
        category: '',
        stock: ''
    };
    
    applyFilters();
    showMessage('Filtros limpiados', 'info');
}

function applyFilters() {
    let filteredProducts = [...ProductsState.products];
    
    // Filtro de búsqueda
    if (ProductsState.filters.search) {
        filteredProducts = filteredProducts.filter(product => 
            product.name.toLowerCase().includes(ProductsState.filters.search) ||
            (product.barcode && product.barcode.toLowerCase().includes(ProductsState.filters.search)) ||
            (product.sku && product.sku.toLowerCase().includes(ProductsState.filters.search))
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
        if (ProductsState.filters.stock === 'low') {
            filteredProducts = filteredProducts.filter(product => 
                product.current_stock <= 5 && product.current_stock > 0
            );
        } else if (ProductsState.filters.stock === 'out') {
            filteredProducts = filteredProducts.filter(product => 
                product.current_stock <= 0
            );
        }
    }
    
    displayProducts(filteredProducts);
    updateStats(filteredProducts.length);
}

// ===== VISUALIZACIÓN DE PRODUCTOS =====
function displayProducts(products) {
    const grid = document.getElementById('productsGrid');
    const list = document.getElementById('productsList');
    
    if (!grid) return;
    
    if (products.length === 0) {
        showEmptyState();
        return;
    }
    
    if (ProductsState.currentView === 'grid') {
        displayProductsGrid(products);
        grid.style.display = 'grid';
        if (list) list.style.display = 'none';
    } else {
        displayProductsList(products);
        if (list) list.style.display = 'block';
        grid.style.display = 'none';
    }
}

function displayProductsGrid(products) {
    const grid = document.getElementById('productsGrid');
    
    const html = products.map(product => `
        <div class="product-card" data-product-id="${product.id}">
            <div class="product-image">
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` :
                    '<div class="product-placeholder"><i class="fas fa-box"></i></div>'
                }
                <div class="product-actions">
                    <button class="action-btn edit" onclick="editProduct(${product.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="product-info">
                <h4 class="product-name">${product.name}</h4>
                <p class="product-category">${product.category_name || 'Sin categoría'}</p>
                <div class="product-price">S/ ${parseFloat(product.selling_price).toFixed(2)}</div>
                <div class="product-stock ${product.current_stock <= 5 ? 'low-stock' : ''}">
                    <i class="fas fa-cube"></i>
                    Stock: ${product.current_stock || 0}
                </div>
            </div>
        </div>
    `).join('');
    
    grid.innerHTML = html;
}

function displayProductsList(products) {
    const list = document.getElementById('productsList');
    if (!list) return;
    
    const tbody = list.querySelector('tbody');
    if (!tbody) return;
    
    const html = products.map(product => `
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
                        <div class="product-code">${product.barcode || 'N/A'}</div>
                    </div>
                </div>
            </td>
            <td>${product.category_name || 'Sin categoría'}</td>
            <td>S/ ${parseFloat(product.selling_price).toFixed(2)}</td>
            <td>
                <span class="stock-badge ${product.current_stock <= 5 ? 'low' : 'normal'}">
                    ${product.current_stock || 0}
                </span>
            </td>
            <td>
                <span class="status-badge ${product.current_stock > 0 ? 'active' : 'inactive'}">
                    ${product.current_stock > 0 ? 'Disponible' : 'Agotado'}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="editProduct(${product.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-error" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    tbody.innerHTML = html;
}

function showEmptyState() {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-box-open fa-4x"></i>
            <h3>No se encontraron productos</h3>
            <p>No hay productos que coincidan con los filtros seleccionados.</p>
            <button class="btn btn-primary" onclick="clearFilters()">
                <i class="fas fa-filter"></i>
                Limpiar Filtros
            </button>
        </div>
    `;
}

// ===== CAMBIO DE VISTA =====
function setView(view) {
    ProductsState.currentView = view;
    
    // Actualizar botones
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-view="${view}"]`).classList.add('active');
    
    // Actualizar icono del toggle
    const toggleIcon = document.getElementById('viewToggleIcon');
    if (toggleIcon) {
        toggleIcon.className = view === 'grid' ? 'fas fa-list' : 'fas fa-th';
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
    document.getElementById('productBarcode').value = product.barcode || '';
    document.getElementById('productCost').value = product.cost_price || '';
    document.getElementById('productPrice').value = product.selling_price;
    document.getElementById('productStock').value = product.current_stock || '';
    document.getElementById('productDescription').value = product.description || '';
}

async function handleProductSubmit(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('productName').value,
        category_id: document.getElementById('productCategory').value || null,
        barcode: document.getElementById('productBarcode').value,
        cost_price: parseFloat(document.getElementById('productCost').value) || 0,
        selling_price: parseFloat(document.getElementById('productPrice').value),
        current_stock: parseInt(document.getElementById('productStock').value) || 0,
        description: document.getElementById('productDescription').value
    };
    
    // Validaciones básicas
    if (!formData.name.trim()) {
        showMessage('El nombre del producto es requerido', 'warning');
        return;
    }
    
    if (formData.selling_price <= 0) {
        showMessage('El precio de venta debe ser mayor a 0', 'warning');
        return;
    }
    
    try {
        let response;
        
        if (ProductsState.editingProduct) {
            // Actualizar producto existente
            response = await API.put(`/productos.php?id=${ProductsState.editingProduct}`, formData);
        } else {
            // Crear nuevo producto
            response = await API.post('/productos.php', formData);
        }
        
        if (response.success) {
            showMessage(response.message || 'Producto guardado exitosamente', 'success');
            closeProductModal();
            await refreshProducts();
        } else {
            showMessage(response.message || 'Error al guardar el producto', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

function editProduct(productId) {
    openProductModal(productId);
}

async function deleteProduct(productId) {
    const product = ProductsState.products.find(p => p.id == productId);
    if (!product) {
        showMessage('Producto no encontrado', 'error');
        return;
    }
    
    if (!confirm(`¿Está seguro que desea eliminar "${product.name}"?`)) {
        return;
    }
    
    try {
        const response = await API.delete(`/productos.php?id=${productId}`);
        
        if (response.success) {
            showMessage(response.message || 'Producto eliminado exitosamente', 'success');
            await refreshProducts();
        } else {
            showMessage(response.message || 'Error al eliminar el producto', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

// ===== ACTUALIZACIÓN DE DATOS =====
async function refreshProducts() {
    try {
        const response = await API.get('/productos.php');
        
        if (response.success) {
            ProductsState.products = response.data;
            applyFilters();
        } else {
            showMessage('Error al cargar productos', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('Error de conexión', 'error');
    }
}

// ===== ESTADÍSTICAS =====
function updateStats(filteredCount = null) {
    const totalProducts = document.getElementById('totalProducts');
    if (totalProducts) {
        const count = filteredCount !== null ? filteredCount : ProductsState.products.length;
        totalProducts.textContent = count;
    }
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
    if (typeof Messages !== 'undefined') {
        Messages.show(message, type);
    } else {
        alert(message);
    }
}

// ===== MOBILE MENU =====
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    }
}

// ===== FUNCIONES GLOBALES =====
window.openProductModal = openProductModal;
window.closeProductModal = closeProductModal;
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.setView = setView;
window.toggleView = toggleView;
window.clearFilters = clearFilters;
window.toggleMobileSidebar = toggleMobileSidebar;