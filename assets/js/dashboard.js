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
    // Obtener el nombre del archivo actual, ej: "dashboard.html"
    const currentPageFile = window.location.pathname.split('/').pop();
    // Obtener el nombre base sin la extensión, ej: "dashboard"
    const pageName = currentPageFile.replace('.html', '').replace('.php', '');

    // Quitar la clase 'active' de cualquier enlace que la tenga
    const activeLinks = document.querySelectorAll('.sidebar-nav-link.active');
    activeLinks.forEach(link => link.classList.remove('active'));

    // Encontrar el enlace que corresponde a la página actual y agregarle la clase 'active'
    const targetLink = document.querySelector(`.sidebar-nav-link[data-page='${pageName}']`);
    if (targetLink) {
        targetLink.classList.add('active');
    }
}

function initializeDashboard() {
    // Mobile menu toggle
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

    // Cargar datos del dashboard
    loadDashboardData();

    // Actualizar cada 30 segundos
    setInterval(loadDashboardData, 30000);
}

async function loadDashboardData() {
    try {
        const mockData = {
            stats: {
                sales_today: { total: 1240.50, count: 12 },
                products_sold_today: { total: 47 },
                low_stock_products: 3,
                pending_debts: { total: 890.00, count: 3 }
            }
        };
        
        updateDashboardUI(mockData);
        
    } catch (error) {
        console.error('Error cargando datos del dashboard:', error);
        Notifications.error('Error al cargar datos del dashboard');
    }
}

function updateDashboardUI(data) {
    document.getElementById('salesTodayValue').textContent = Utils.formatCurrency(data.stats.sales_today.total);
    document.getElementById('productsSoldValue').textContent = data.stats.products_sold_today.total;
    document.getElementById('lowStockValue').textContent = data.stats.low_stock_products;
    document.getElementById('pendingDebtsValue').textContent = Utils.formatCurrency(data.stats.pending_debts.total);
}

// Funciones de acciones rápidas
function newSale() {
    window.location.href = 'pos.html';
}

function addProduct() {
    window.location.href = 'products.html?action=add';
}

function addExpense() {
    window.location.href = 'expenses.html?action=add';
}

function viewReports() {
    window.location.href = 'reports.html';
}

function logout() {
    Modal.confirm('¿Estás seguro que deseas cerrar sesión?', 'Confirmar Cierre de Sesión')
        .then(confirmed => {
            if (confirmed) {
                Storage.clear();
                window.location.href = 'login.html';
            }
        });
}

// Hacer funciones accesibles globalmente
window.newSale = newSale;
window.addProduct = addProduct;
window.addExpense = addExpense;
window.viewReports = viewReports;
window.logout = logout;