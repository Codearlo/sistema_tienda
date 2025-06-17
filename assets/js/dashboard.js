/**
 * DASHBOARD - JavaScript
 * Lógica específica para la página del dashboard
 */

document.addEventListener('DOMContentLoaded', () => {
    loadSidebar().then(() => {
        initializeDashboard();
    });
});

async function loadSidebar() {
    try {
        const response = await fetch('includes/slidebar.php');
        if (!response.ok) throw new Error('Network response was not ok.');
        
        const sidebarHtml = await response.text();
        const sidebarContainer = document.getElementById('sidebar-container');
        
        if (sidebarContainer) {
            sidebarContainer.innerHTML = sidebarHtml;
            setActiveSidebarLink();
        }
    } catch (error) {
        console.error('Error al cargar el sidebar:', error);
        const sidebarContainer = document.getElementById('sidebar-container');
        if(sidebarContainer) sidebarContainer.innerHTML = '<p>Error al cargar el menú.</p>';
    }
}

function setActiveSidebarLink() {
    const currentPageFile = window.location.pathname.split('/').pop() || 'dashboard.html';
    const pageName = currentPageFile.replace('.html', '').replace('.php', '');

    const targetLink = document.querySelector(`.sidebar-nav-link[data-page='${pageName}']`);
    if (targetLink) {
        // Quitar la clase 'active' de cualquier otro enlace
        document.querySelectorAll('.sidebar-nav-link.active').forEach(link => link.classList.remove('active'));
        targetLink.classList.add('active');
    }
}

function initializeDashboard() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const mobileOverlay = document.getElementById('mobileOverlay');

    if (mobileMenuBtn && sidebar && mobileOverlay) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            mobileOverlay.classList.toggle('show');
        });

        mobileOverlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            mobileOverlay.classList.remove('show');
        });
    }

    loadDashboardData();
    setInterval(loadDashboardData, 30000); // Actualizar cada 30 segundos
}

async function loadDashboardData() {
    try {
        const response = await API.get('dashboard');
        if (response && response.success) {
            updateDashboardUI(response.data);
        } else {
            Notifications.error(response.message || 'No se pudieron cargar los datos del dashboard.');
        }
    } catch (error) {
        console.error('Error cargando datos del dashboard:', error);
        Notifications.error('Error de conexión al cargar datos del dashboard.');
    }
}

function updateDashboardUI(data) {
    // 1. Actualizar Tarjetas de Estadísticas
    document.getElementById('salesTodayValue').textContent = Utils.formatCurrency(data.stats.sales_today.total);
    document.getElementById('productsSoldValue').textContent = data.stats.products_sold_today.total;
    document.getElementById('lowStockValue').textContent = data.stats.low_stock_products.length;
    document.getElementById('pendingDebtsValue').textContent = Utils.formatCurrency(data.stats.pending_debts.total);

    // 2. Renderizar Ventas Recientes
    const recentSalesTable = document.getElementById('recentSalesTable');
    if(recentSalesTable) {
        recentSalesTable.innerHTML = data.recent_sales.map(sale => `
            <tr>
                <td>${Utils.formatDate(sale.sale_date, 'DD/MM/YY HH:mm')}</td>
                <td>${Utils.cleanInput(sale.customer_name)}</td>
                <td>${sale.item_count}</td>
                <td>${Utils.formatCurrency(sale.total_amount)}</td>
                <td><span class="badge badge-${sale.payment_status === 'paid' ? 'success' : 'warning'}">${sale.payment_status}</span></td>
            </tr>
        `).join('');
    }

    // 3. Renderizar Productos con Stock Bajo
    const stockAlertsContainer = document.getElementById('stockAlerts');
    if(stockAlertsContainer) {
        if (data.stats.low_stock_products.length > 0) {
            stockAlertsContainer.innerHTML = data.stats.low_stock_products.map(product => {
                const isOutOfStock = product.stock_quantity <= 0;
                return `
                <div class="stock-item ${isOutOfStock ? 'stock-item-critical' : 'stock-item-low'}">
                    <div class="stock-info">
                        <div class="stock-name">${Utils.cleanInput(product.name)}</div>
                        <div class="stock-quantity">Stock: ${product.stock_quantity} ${product.unit}</div>
                    </div>
                    <span class="badge ${isOutOfStock ? 'badge-error' : 'badge-warning'}">${isOutOfStock ? 'Agotado' : 'Bajo'}</span>
                </div>
            `}).join('');
        } else {
            stockAlertsContainer.innerHTML = '<p>No hay productos con stock bajo.</p>';
        }
    }

    // 4. Renderizar Deudas por Cobrar
    const debtsListContainer = document.getElementById('debtsList');
    if(debtsListContainer) {
        if (data.upcoming_debts.length > 0) {
            debtsListContainer.innerHTML = data.upcoming_debts.map(debt => {
                const dueDate = new Date(debt.due_date);
                const today = new Date();
                const isOverdue = dueDate < today;
                return `
                <div class="debt-item ${isOverdue ? 'debt-item-overdue' : 'debt-item-due-soon'}">
                    <div class="debt-info">
                        <div class="debt-customer">${Utils.cleanInput(debt.customer_name)}</div>
                        <div class="debt-date">${isOverdue ? 'Venció' : 'Vence'}: ${Utils.formatDate(debt.due_date)}</div>
                    </div>
                    <div class="debt-amount-info">
                        <div class="debt-amount">${Utils.formatCurrency(debt.remaining_amount)}</div>
                        <span class="badge ${isOverdue ? 'badge-error' : 'badge-warning'}">${debt.status}</span>
                    </div>
                </div>
            `}).join('');
        } else {
            debtsListContainer.innerHTML = '<p>No hay deudas pendientes por cobrar.</p>';
        }
    }
}


// Funciones de acciones rápidas (accesibles globalmente)
window.newSale = () => window.location.href = 'pos.html';
window.addProduct = () => window.location.href = 'products.html?action=add';
window.addExpense = () => window.location.href = 'expenses.html?action=add';
window.viewReports = () => window.location.href = 'reports.html';
window.logout = () => {
    Modal.confirm('¿Estás seguro que deseas cerrar sesión?', 'Confirmar Cierre de Sesión')
        .then(confirmed => {
            if (confirmed) {
                Storage.clear();
                window.location.href = 'login.html';
            }
        });
};