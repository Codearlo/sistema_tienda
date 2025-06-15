/**
 * PRODUCTS MANAGEMENT - JavaScript
 * Gestión completa de productos e inventario
 */

// Estado global de productos
const ProductsState = {
    products: [],
    categories: [],
    currentPage: 1,
    totalPages: 1,
    currentView: 'grid',
    filters: {
        search: '',
        category: '',
        stock: ''
    },
    isLoading: false,
    editingProduct: null
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    initializeProducts();
});

function initializeProducts() {
    // Mobile menu
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        mobileOverlay.classList.toggle('show');
    });

    mobileOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        mobileOverlay.classList.remove('show');
    });

    // Inicializar eventos
    setupEventListeners();
    
    // Cargar datos iniciales
    loadCategories();
    loadProducts();
    updateStats();
}

// ===== EVENT LISTENERS =====
function setupEventListeners() {
    // Búsqueda con debounce
    const searchInput = document.getElementById('searchProducts');
    searchInput.addEventListener('input', Utils.debounce(handleSearch, 300));

    // Filtros
    document.getElementById('categoryFilter').addEventListener('change', handleCategoryFilter);
    document.getElementById('stockFilter').addEventListener('change', handleStockFilter);

    // Upload de imagen
    const imageUpload = document.getElementById('productImage');
    const uploadArea = document.getElementById('imageUploadArea');
    
    uploadArea.addEventListener('click', () => imageUpload.click());
    imageUpload.addEventListener('change', handleImageUpload);

    // Formularios
    document.getElementById('productForm').addEventListener('submit', handleProductSubmit);
    document.getElementById('categoryForm').addEventListener('submit', handleCategorySubmit);

    // Cálculo automático de margen
    document.getElementById('productCostPrice').addEventListener('input', calculateMargin);
    document.getElementById('productSellingPrice').addEventListener('input', calculateMargin);
}

// ===== CARGA DE DATOS =====
async function loadProducts(page = 1) {
    try {
        showLoading(true);
        
        const params = new URLSearchParams({
            page: page,
            limit: 12,
            search: ProductsState.filters.search,
            category: ProductsState.filters.category,
            stock: ProductsState.filters.stock
        });

        // Simulación de API call
        const response = await simulateAPICall('products', {
            products: generateSampleProducts(),
            pagination: {
                current_page: page,
                total_pages: 3,
                total_records: 32
            }
        });

        ProductsState.products = response.products;
        ProductsState.currentPage = response.pagination.current_page;
        ProductsState.totalPages = response.pagination.total_pages;

        renderProducts();
        renderPagination();
        
    } catch (error) {
        console.error('Error cargando productos:', error);
        Notifications.error('Error al cargar productos');
    } finally {
        showLoading(false);
    }
}

async function loadCategories() {
    try {
        // Simulación de categorías
        const categories = [
            { id: 1, name: 'Alimentación', color: '#10B981' },
            { id: 2, name: 'Electrónicos', color: '#3B82F6' },
            { id: 3, name: 'Ropa', color: '#8B5CF6' },
            { id: 4, name: 'Hogar', color: '#F59E0B' },
            { id: 5, name: 'Salud', color: '#EF4444' }
        ];

        ProductsState.categories = categories;
        renderCategoryOptions();
        renderCategoriesList();
        
    } catch (error) {
        console.error('Error cargando categorías:', error);
        Notifications.error('Error al cargar categorías');
    }
}

async function updateStats() {
    try {
        // Calcular estadísticas basadas en productos actuales
        const totalProducts = ProductsState.products.length;
        const inventoryValue = ProductsState.products.reduce((sum, product) => {
            return sum + (product.cost_price * product.stock_quantity);
        }, 0);
        const lowStockCount = ProductsState.products.filter(p => p.stock_quantity <= p.min_stock && p.stock_quantity > 0).length;
        const outOfStockCount = ProductsState.products.filter(p => p.stock_quantity === 0).length;

        // Actualizar DOM
        document.getElementById('totalProducts').textContent = totalProducts;
        document.getElementById('inventoryValue').textContent = Utils.formatCurrency(inventoryValue);
        document.getElementById('lowStockCount').textContent = lowStockCount;
        document.getElementById('outOfStockCount').textContent = outOfStockCount;
        
    } catch (error) {
        console.error('Error actualizando estadísticas:', error);
    }
}

// ===== RENDERIZADO =====
function renderProducts() {
    const grid = document.getElementById('productsGrid');
    const list = document.getElementById('productsList');
    const tableBody = document.getElementById('productsTableBody');
    const emptyState = document.getElementById('emptyState');

    if (ProductsState.products.length === 0) {
        grid.classList.add('hidden');
        list.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');

    if (ProductsState.currentView === 'grid') {
        grid.classList.remove('hidden');
        list.classList.add('hidden');
        grid.innerHTML = ProductsState.products.map(product => createProductCard(product)).join('');
    } else {
        grid.classList.add('hidden');
        list.classList.remove('hidden');
        tableBody.innerHTML = ProductsState.products.map(product => createProductRow(product)).join('');
    }
}

function createProductCard(product) {
    const stockBadge = getStockBadge(product);
    const category = ProductsState.categories.find(c => c.id === product.category_id);
    
    return `
        <div class="product-card" data-product-id="${product.id}">
            <div class="product-image">
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` :
                    `<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21,15 16,10 5,21"/>
                    </svg>`
                }
                ${stockBadge}
            </div>
            <div class="product-info">
                <h4 class="product-name">${product.name}</h4>
                ${product.sku ? `<div class="product-sku">SKU: ${product.sku}</div>` : ''}
                
                ${category ? `
                    <span class="product-category" style="background-color: ${category.color}20; color: ${category.color};">
                        ${category.name}
                    </span>
                ` : ''}
                
                <div class="product-price">
                    <div class="price-selling">${Utils.formatCurrency(product.selling_price)}</div>
                    ${product.cost_price > 0 ? `<div class="price-cost">Costo: ${Utils.formatCurrency(product.cost_price)}</div>` : ''}
                </div>
                
                <div class="product-stock">
                    <div class="stock-info">
                        Stock: <span class="stock-quantity ${getStockClass(product)}">${product.stock_quantity} ${product.unit}</span>
                    </div>
                    ${product.min_stock > 0 ? `<div class="text-xs text-gray-500">Mín: ${product.min_stock}</div>` : ''}
                </div>
                
                <div class="product-actions">
                    <button class="btn-icon edit" onclick="editProduct(${product.id})" title="Editar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-icon stock" onclick="adjustStock(${product.id})" title="Ajustar Stock">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                            <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                            <path d="M3 21h18"/>
                        </svg>
                    </button>
                    <button class="btn-icon delete" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3,6 5,6 21,6"/>
                            <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function createProductRow(product) {
    const stockBadge = getStockBadge(product);
    const category = ProductsState.categories.find(c => c.id === product.category_id);
    
    return `
        <tr data-product-id="${product.id}">
            <td>
                ${product.image ? 
                    `<img src="${product.image}" alt="${product.name}">` :
                    `<div style="width: 50px; height: 50px; background-color: var(--gray-100); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21,15 16,10 5,21"/>
                        </svg>
                    </div>`
                }
            </td>
            <td>
                <div class="font-semibold">${product.name}</div>
                ${product.description ? `<div class="text-sm text-gray-500">${product.description}</div>` : ''}
            </td>
            <td>${product.sku || '-'}</td>
            <td>${category ? category.name : '-'}</td>
            <td class="font-mono">${Utils.formatCurrency(product.selling_price)}</td>
            <td class="font-mono ${getStockClass(product)}">${product.stock_quantity} ${product.unit}</td>
            <td>${stockBadge}</td>
            <td>
                <div class="product-actions">
                    <button class="btn-icon edit" onclick="editProduct(${product.id})" title="Editar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-icon stock" onclick="adjustStock(${product.id})" title="Ajustar Stock">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7h-9a2 2 0 0 1-2-2V2"/>
                            <path d="M9 2v5a2 2 0 0 0 2 2h9"/>
                            <path d="M3 13.6V7a2 2 0 0 1 2-2h5"/>
                            <path d="M3 21h18"/>
                        </svg>
                    </button>
                    <button class="btn-icon delete" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3,6 5,6 21,6"/>
                            <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2,2h4a2,2,0,0,1,2,2V6"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function renderCategoryOptions() {
    const selects = ['categoryFilter', 'productCategory'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Limpiar opciones existentes (excepto la primera)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        // Agregar nuevas opciones
        ProductsState.categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            select.appendChild(option);
        });
    });
}

function renderCategoriesList() {
    const container = document.getElementById('categoriesList');
    if (!container) return;
    
    container.innerHTML = ProductsState.categories.map(category => `
        <div class="category-item">
            <div class="category-info">
                <div class="category-color" style="background-color: ${category.color};"></div>
                <span class="category-name">${category.name}</span>
            </div>
            <div class="category-actions">
                <button class="btn-icon edit" onclick="editCategory(${category.id})" title="Editar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="btn-icon delete" onclick="deleteCategory(${category.id})" title="Eliminar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3,6 5,6 21,6"/>
                        <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2,2h4a2,2,0,0,1,2,2V6"/>
                    </svg>
                </button>
            </div>
        </div>
    `).join('');
}

function renderPagination() {
    const container = document.getElementById('paginationContainer');
    if (!container || ProductsState.totalPages <= 1) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    
    let paginationHTML = '<div class="pagination">';
    
    // Botón anterior
    paginationHTML += `
        <button class="btn ${ProductsState.currentPage === 1 ? 'disabled' : ''}" 
                onclick="changePage(${ProductsState.currentPage - 1})" 
                ${ProductsState.currentPage === 1 ? 'disabled' : ''}>
            ‹
        </button>
    `;
    
    // Números de página
    for (let i = 1; i <= ProductsState.totalPages; i++) {
        paginationHTML += `
            <button class="btn ${i === ProductsState.currentPage ? 'active' : ''}" 
                    onclick="changePage(${i})">
                ${i}
            </button>
        `;
    }
    
    // Botón siguiente
    paginationHTML += `
        <button class="btn ${ProductsState.currentPage === ProductsState.totalPages ? 'disabled' : ''}" 
                onclick="changePage(${ProductsState.currentPage + 1})" 
                ${ProductsState.currentPage === ProductsState.totalPages ? 'disabled' : ''}>
            ›
        </button>
    `;
    
    paginationHTML += '</div>';
    
    // Información de paginación
    const start = (ProductsState.currentPage - 1) * 12 + 1;
    const end = Math.min(ProductsState.currentPage * 12, ProductsState.products.length);
    
    paginationHTML += `
        <div class="pagination-info">
            Mostrando ${start}-${end} de ${ProductsState.products.length} productos
        </div>
    `;
    
    container.innerHTML = paginationHTML;
}

// ===== FUNCIONES AUXILIARES =====
function getStockBadge(product) {
    if (product.stock_quantity === 0) {
        return '<span class="product-badge out-of-stock">Sin Stock</span>';
    } else if (product.stock_quantity <= product.min_stock) {
        return '<span class="product-badge low-stock">Stock Bajo</span>';
    } else {
        return '<span class="product-badge in-stock">En Stock</span>';
    }
}

function getStockClass(product) {
    if (product.stock_quantity === 0) {
        return 'stock-out';
    } else if (product.stock_quantity <= product.min_stock) {
        return 'stock-low';
    }
    return '';
}

function showLoading(show) {
    const loading = document.getElementById('productsLoading');
    const grid = document.getElementById('productsGrid');
    const list = document.getElementById('productsList');
    
    if (show) {
        loading.classList.remove('hidden');
        grid.classList.add('hidden');
        list.classList.add('hidden');
        ProductsState.isLoading = true;
    } else {
        loading.classList.add('hidden');
        ProductsState.isLoading = false;
    }
}

// ===== FILTROS Y BÚSQUEDA =====
function handleSearch(event) {
    ProductsState.filters.search = event.target.value;
    ProductsState.currentPage = 1;
    loadProducts();
}

function handleCategoryFilter(event) {
    ProductsState.filters.category = event.target.value;
    ProductsState.currentPage = 1;
    loadProducts();
}

function handleStockFilter(event) {
    ProductsState.filters.stock = event.target.value;
    ProductsState.currentPage = 1;
    loadProducts();
}

function applyFilters() {
    loadProducts();
}

function clearFilters() {
    document.getElementById('searchProducts').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('stockFilter').value = '';
    
    ProductsState.filters = { search: '', category: '', stock: '' };
    ProductsState.currentPage = 1;
    loadProducts();
}

// ===== VISTA =====
function toggleView(view) {
    ProductsState.currentView = view;
    
    // Actualizar botones
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.view === view) {
            btn.classList.add('active');
        }
    });
    
    renderProducts();
}

function changePage(page) {
    if (page < 1 || page > ProductsState.totalPages || page === ProductsState.currentPage) return;
    
    ProductsState.currentPage = page;
    loadProducts(page);
}

// ===== MODALES =====
function openProductModal(productId = null) {
    const modal = document.getElementById('productModal');
    const title = document.getElementById('productModalTitle');
    const form = document.getElementById('productForm');
    
    ProductsState.editingProduct = productId;
    
    if (productId) {
        title.textContent = 'Editar Producto';
        loadProductData(productId);
    } else {
        title.textContent = 'Nuevo Producto';
        Forms.clear(form);
        clearImagePreview();
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
    ProductsState.editingProduct = null;
}

function openCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// ===== CRUD PRODUCTOS =====
async function handleProductSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = document.getElementById('productSubmitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    try {
        // Validar formulario
        const validation = Forms.validate(form);
        if (!validation.isValid) {
            validation.errors.forEach(error => Notifications.error(error));
            return;
        }
        
        // Mostrar loading
        submitBtn.disabled = true;
        btnText.classList.add('hidden');
        btnLoading.classList.remove('hidden');
        
        // Obtener datos del formulario
        const formData = Forms.serialize(form);
        
        // Simulación de guardado
        await simulateAPICall('save-product', formData);
        
        Notifications.success(ProductsState.editingProduct ? 'Producto actualizado exitosamente' : 'Producto creado exitosamente');
        
        closeProductModal();
        loadProducts();
        updateStats();
        
    } catch (error) {
        console.error('Error guardando producto:', error);
        Notifications.error('Error al guardar el producto');
    } finally {
        submitBtn.disabled = false;
        btnText.classList.remove('hidden');
        btnLoading.classList.add('hidden');
    }
}

function loadProductData(productId) {
    const product = ProductsState.products.find(p => p.id === productId);
    if (!product) return;
    
    const form = document.getElementById('productForm');
    Forms.populate(form, product);
    
    // Cargar imagen si existe
    if (product.image) {
        showImagePreview(product.image);
    }
}

async function editProduct(productId) {
    openProductModal(productId);
}

async function deleteProduct(productId) {
    const confirmed = await Modal.confirm(
        '¿Estás seguro de que deseas eliminar este producto?',
        'Confirmar Eliminación'
    );
    
    if (!confirmed) return;
    
    try {
        await simulateAPICall('delete-product', { id: productId });
        
        Notifications.success('Producto eliminado exitosamente');
        loadProducts();
        updateStats();
        
    } catch (error) {
        console.error('Error eliminando producto:', error);
        Notifications.error('Error al eliminar el producto');
    }
}

async function adjustStock(productId) {
    const product = ProductsState.products.find(p => p.id === productId);
    if (!product) return;
    
    const newStock = prompt(`Ajustar stock de ${product.name}\nStock actual: ${product.stock_quantity}`, product.stock_quantity);
    
    if (newStock === null || newStock === '' || isNaN(newStock)) return;
    
    try {
        await simulateAPICall('adjust-stock', {
            product_id: productId,
            new_stock: parseInt(newStock),
            reason: 'Ajuste manual'
        });
        
        Notifications.success('Stock actualizado exitosamente');
        loadProducts();
        updateStats();
        
    } catch (error) {
        console.error('Error ajustando stock:', error);
        Notifications.error('Error al ajustar el stock');
    }
}

// ===== CRUD CATEGORÍAS =====
async function handleCategorySubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = Forms.serialize(form);
    
    if (!formData.name.trim()) {
        Notifications.error('El nombre de la categoría es requerido');
        return;
    }
    
    try {
        await simulateAPICall('save-category', formData);
        
        Notifications.success('Categoría agregada exitosamente');
        Forms.clear(form);
        loadCategories();
        
    } catch (error) {
        console.error('Error guardando categoría:', error);
        Notifications.error('Error al guardar la categoría');
    }
}

async function deleteCategory(categoryId) {
    const confirmed = await Modal.confirm(
        '¿Estás seguro de que deseas eliminar esta categoría?',
        'Confirmar Eliminación'
    );
    
    if (!confirmed) return;
    
    try {
        await simulateAPICall('delete-category', { id: categoryId });
        
        Notifications.success('Categoría eliminada exitosamente');
        loadCategories();
        
    } catch (error) {
        console.error('Error eliminando categoría:', error);
        Notifications.error('Error al eliminar la categoría');
    }
}

// ===== UPLOAD DE IMÁGENES =====
function handleImageUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validar tipo y tamaño
    if (!file.type.startsWith('image/')) {
        Notifications.error('Por favor selecciona una imagen válida');
        return;
    }
    
    if (file.size > 2 * 1024 * 1024) { // 2MB
        Notifications.error('La imagen debe ser menor a 2MB');
        return;
    }
    
    // Mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
        showImagePreview(e.target.result);
    };
    reader.readAsDataURL(file);
}

function showImagePreview(imageSrc) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = `
        <img src="${imageSrc}" alt="Preview">
        <button type="button" class="image-remove" onclick="clearImagePreview()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    `;
    preview.classList.add('has-image');
}

function clearImagePreview() {
    const preview = document.getElementById('imagePreview');
    const fileInput = document.getElementById('productImage');
    
    preview.innerHTML = `
        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21,15 16,10 5,21"/>
        </svg>
        <p>Clic para subir imagen</p>
        <span class="upload-hint">JPG, PNG o GIF. Máximo 2MB</span>
    `;
    preview.classList.remove('has-image');
    fileInput.value = '';
}

// ===== UTILIDADES =====
function calculateMargin() {
    const costPrice = parseFloat(document.getElementById('productCostPrice').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('productSellingPrice').value) || 0;
    
    if (costPrice > 0 && sellingPrice > 0) {
        const margin = ((sellingPrice - costPrice) / sellingPrice * 100).toFixed(1);
        console.log(`Margen de ganancia: ${margin}%`);
    }
}

async function simulateAPICall(endpoint, data) {
    // Simular delay de red
    await new Promise(resolve => setTimeout(resolve, 500 + Math.random() * 1000));
    
    // Simular posible error (5% de probabilidad)
    if (Math.random() < 0.05) {
        throw new Error('Error de red simulado');
    }
    
    return { success: true, data };
}

function generateSampleProducts() {
    return [
        {
            id: 1,
            name: 'Coca Cola 500ml',
            sku: 'COC-500-001',
            description: 'Gaseosa Coca Cola de 500ml',
            category_id: 1,
            cost_price: 2.50,
            selling_price: 4.00,
            stock_quantity: 50,
            min_stock: 10,
            unit: 'unit',
            image: null
        },
        {
            id: 2,
            name: 'Cable USB-C',
            sku: 'ELE-USB-001',
            description: 'Cable USB-C de 1 metro',
            category_id: 2,
            cost_price: 15.00,
            selling_price: 25.00,
            stock_quantity: 2,
            min_stock: 5,
            unit: 'unit',
            image: null
        },
        {
            id: 3,
            name: 'Camiseta Básica',
            sku: 'ROP-CAM-001',
            description: 'Camiseta 100% algodón',
            category_id: 3,
            cost_price: 25.00,
            selling_price: 45.00,
            stock_quantity: 0,
            min_stock: 5,
            unit: 'unit',
            image: null
        },
        {
            id: 4,
            name: 'Pan Integral',
            sku: 'ALM-PAN-001',
            description: 'Pan integral artesanal',
            category_id: 1,
            cost_price: 1.20,
            selling_price: 2.50,
            stock_quantity: 15,
            min_stock: 10,
            unit: 'unit',
            image: null
        }
    ];
}

function logout() {
    Modal.confirm('¿Estás seguro que deseas cerrar sesión?', 'Confirmar Logout')
        .then(confirmed => {
            if (confirmed) {
                Storage.clear();
                window.location.href = 'login.html';
            }
        });
}

// Exportar funciones globales
window.openProductModal = openProductModal;
window.closeProductModal = closeProductModal;
window.openCategoryModal = openCategoryModal;
window.closeCategoryModal = closeCategoryModal;
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.adjustStock = adjustStock;
window.deleteCategory = deleteCategory;
window.toggleView = toggleView;
window.changePage = changePage;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.clearImagePreview = clearImagePreview;
window.logout = logout;