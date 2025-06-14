/**
 * DASHBOARD - JavaScript
 * Lógica específica para la página del dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

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
        // Aquí harías la llamada real a tu API: const response = await API.get('dashboard');
        // Por ahora, simulamos los datos para la demostración.
        const mockData = {
            stats: {
                sales_today: { total: 1240.50, count: 12 },
                products_sold_today: { total: 47 },
                low_stock_products: 3,
                pending_debts: { total: 890.00, count: 3 }
            },
            recent_sales: [
                { date: '2025-06-13 14:30:00', customer: 'María García', items: 3, total: 125.50, status: 'Pagado' },
                { date: '2025-06-13 13:15:00', customer: 'Juan Pérez', items: 1, total: 45.00, status: 'Pagado' },
                { date: '2025-06-13 12:45:00', customer: 'Ana López', items: 5, total: 230.00, status: 'Pendiente' },
                { date: '2025-06-13 11:20:00', customer: 'Carlos Ruiz', items: 2, total: 89.75, status: 'Pagado' },
            ]
        };
        
        updateDashboardUI(mockData);
        
    } catch (error) {
        console.error('Error cargando datos del dashboard:', error);
        Notifications.error('Error al cargar datos del dashboard');
    }
}

function updateDashboardUI(data) {
    // Actualizar estadísticas principales
    document.getElementById('salesTodayValue').textContent = Utils.formatCurrency(data.stats.sales_today.total);
    document.getElementById('productsSoldValue').textContent = data.stats.products_sold_today.total;
    document.getElementById('lowStockValue').textContent = data.stats.low_stock_products;
    document.getElementById('pendingDebtsValue').textContent = Utils.formatCurrency(data.stats.pending_debts.total);

    // Aquí se agregarían las actualizaciones para las tablas y otros widgets
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
                // Limpiar sesión y redirigir
                Storage.clear();
                window.location.href = 'login.html';
            }
        });
}

// Hacer funciones accesibles globalmente si se llaman desde el HTML
window.newSale = newSale;
window.addProduct = addProduct;
window.addExpense = addExpense;
window.viewReports = viewReports;
window.logout = logout;