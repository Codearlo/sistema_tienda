/**
 * DASHBOARD - JavaScript
 * Lógica específica para la página del dashboard
 */

document.addEventListener('DOMContentLoaded', () => {
    initializeDashboard();
});

function initializeDashboard() {
    // Inicializar controles móviles
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const mobileOverlay = document.querySelector('.mobile-overlay');

    if (mobileMenuBtn && sidebar && mobileOverlay) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            mobileOverlay.classList.toggle('show');
        });

        mobileOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('show');
        });
    }

    // Cargar datos del dashboard
    loadDashboardData();
    
    // Actualizar cada 30 segundos
    setInterval(loadDashboardData, 30000);
}

async function loadDashboardData() {
    try {
        const response = await fetch('backend/api/dashboard.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        
        if (result.success) {
            updateDashboardUI(result.data);
        } else {
            console.error('Error:', result.message);
            showNotification(result.message || 'No se pudieron cargar los datos del dashboard.', 'error');
        }
    } catch (error) {
        console.error('Error cargando datos del dashboard:', error);
        showNotification('Error de conexión al cargar datos del dashboard.', 'error');
    }
}

function updateDashboardUI(data) {
    // 1. Actualizar Tarjetas de Estadísticas
    const salesTodayValue = document.querySelector('.stats-grid .stat-card:nth-child(1) .stat-value');
    const salesMonthValue = document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-value');
    const productsValue = document.querySelector('.stats-grid .stat-card:nth-child(3) .stat-value');
    const clientsValue = document.querySelector('.stats-grid .stat-card:nth-child(4) .stat-value');
    
    if (salesTodayValue) {
        salesTodayValue.textContent = formatCurrency(data.stats.sales_today.total);
        // Actualizar contador de ventas
        const salesCount = document.querySelector('.stats-grid .stat-card:nth-child(1) .stat-description');
        if (salesCount) {
            salesCount.textContent = `${data.stats.sales_today.count} ventas realizadas`;
        }
    }
    
    if (productsValue) {
        // Actualizar contador de productos con stock bajo
        const lowStockBadge = document.querySelector('.stats-grid .stat-card:nth-child(3) .stat-change.negative');
        if (lowStockBadge && data.stats.low_stock_products.length > 0) {
            lowStockBadge.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <span>${data.stats.low_stock_products.length} con stock bajo</span>`;
            lowStockBadge.style.display = 'flex';
        }
    }
    
    // 2. Actualizar Alertas de Stock
    const stockAlertsContainer = document.querySelector('.stock-alerts');
    if (stockAlertsContainer) {
        if (data.stats.low_stock_products.length > 0) {
            stockAlertsContainer.innerHTML = data.stats.low_stock_products.map(product => {
                const isOutOfStock = product.stock_quantity <= 0;
                const stockClass = isOutOfStock ? 'stock-item-out' : 
                                  (product.stock_quantity <= product.min_stock / 2 ? 'stock-item-critical' : 'stock-item-low');
                
                return `
                    <div class="stock-item ${stockClass}">
                        <div class="stock-info">
                            <div class="stock-name">${escapeHtml(product.name)}</div>
                            <div class="stock-quantity ${isOutOfStock ? 'stock-out' : 'stock-low'}">
                                Stock: ${product.stock_quantity} ${product.unit || 'unidades'}
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="window.location.href='products.php?action=edit&id=${product.id}'">
                            <i class="fas fa-plus"></i>
                            Reabastecer
                        </button>
                    </div>
                `;
            }).join('');
        } else {
            stockAlertsContainer.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 2rem;">
                    <div class="empty-icon">✓</div>
                    <p style="color: var(--gray-600);">¡Excelente! No hay productos con stock bajo.</p>
                </div>
            `;
        }
    }
    
    // 3. Actualizar Productos Populares
    const popularProductsContainer = document.querySelector('.popular-products');
    if (popularProductsContainer && data.recent_sales.length > 0) {
        // Aquí podrías mostrar productos más vendidos si tienes esa data
        popularProductsContainer.innerHTML = `
            <div class="empty-state" style="text-align: center; padding: 2rem;">
                <p style="color: var(--gray-600);">No hay datos de ventas aún. ¡Realiza tu primera venta!</p>
                <button class="btn btn-primary btn-sm" style="margin-top: 1rem;" onclick="window.location.href='pos.php'">
                    <i class="fas fa-cash-register"></i> Ir al POS
                </button>
            </div>
        `;
    }
    
    // 4. Actualizar Deudas por Cobrar
    const debtsContainer = document.querySelector('.pending-debts');
    if (debtsContainer) {
        if (data.stats.pending_debts.count > 0) {
            debtsContainer.style.display = 'block';
            const debtAmount = debtsContainer.querySelector('.debt-amount');
            const debtCount = debtsContainer.querySelector('.debt-count');
            
            if (debtAmount) debtAmount.textContent = formatCurrency(data.stats.pending_debts.total);
            if (debtCount) debtCount.textContent = `${data.stats.pending_debts.count} deudas pendientes`;
        } else {
            debtsContainer.style.display = 'none';
        }
    }
    
    // 5. Actualizar gráfico si existe
    if (window.salesChart && data.sales_chart) {
        updateSalesChart(data.sales_chart);
    }
}

function updateSalesChart(salesData) {
    const labels = salesData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric' });
    });
    
    const values = salesData.map(item => parseFloat(item.total));
    
    if (window.salesChart) {
        window.salesChart.data.labels = labels;
        window.salesChart.data.datasets[0].data = values;
        window.salesChart.update();
    }
}

// Funciones auxiliares
function formatCurrency(amount) {
    return 'S/ ' + parseFloat(amount).toFixed(2);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function showNotification(message, type = 'info') {
    // Implementación simple de notificaciones
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Insertar al principio del main-content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(notification, mainContent.firstChild);
        
        // Eliminar después de 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// Funciones globales para acciones rápidas
window.toggleMobileSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
    
    if (overlay) {
        overlay.classList.toggle('show');
    }
};

window.updateChart = function(period) {
    console.log('Updating chart for period:', period);
    // Aquí implementarías la lógica para actualizar el gráfico según el período
};