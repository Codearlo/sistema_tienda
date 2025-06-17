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
    setInterval(loadDashboardData, 30000);
}

async function loadDashboardData() {
    try {
        const response = await API.get('?endpoint=dashboard');
        if (response && response.success) {
            updateDashboardUI(response.data);
        } else {
            console.error('Error en respuesta del dashboard:', response);
            if (typeof Notifications !== 'undefined') {
                Notifications.error(response.message || 'No se pudieron cargar los datos del dashboard.');
            } else {
                alert('Error al cargar los datos del dashboard');
            }
        }
    } catch (error) {
        console.error('Error cargando datos del dashboard:', error);
        if (typeof Notifications !== 'undefined') {
            Notifications.error('Error de conexión al cargar datos del dashboard.');
        } else {
            alert('Error de conexión al cargar datos del dashboard');
        }
    }
}

function updateDashboardUI(data) {
    updateStatCard('salesTodayValue', formatCurrency(data.stats.sales_today.total));
    updateStatCard('salesMonthValue', formatCurrency(data.stats.sales_month.total));
    updateStatCard('productsValue', data.stats.products_total);
    updateStatCard('customersValue', data.stats.customers_total);
    updateStatCard('lowStockValue', data.stats.low_stock_products.length);
    updateStatCard('pendingDebtsValue', formatCurrency(data.stats.pending_debts.total));

    renderRecentSales(data.recent_sales);
    renderLowStockProducts(data.stats.low_stock_products);
    renderUpcomingDebts(data.upcoming_debts);
    renderSalesChart(data.sales_chart);
}

function updateStatCard(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

function renderRecentSales(sales) {
    const container = document.getElementById('recentSalesTable');
    if (!container) return;

    if (sales.length === 0) {
        container.innerHTML = '<tr><td colspan="5" class="text-center">No hay ventas recientes</td></tr>';
        return;
    }

    container.innerHTML = sales.map(sale => `
        <tr>
            <td>${formatDate(sale.sale_date)}</td>
            <td>${cleanInput(sale.customer_name)}</td>
            <td>${sale.item_count} item(s)</td>
            <td>${formatCurrency(sale.total_amount)}</td>
            <td>
                <span class="badge badge-${sale.payment_status === 'paid' ? 'success' : 'warning'}">
                    ${sale.payment_status === 'paid' ? 'Pagado' : 'Pendiente'}
                </span>
            </td>
        </tr>
    `).join('');
}

function renderLowStockProducts(products) {
    const container = document.getElementById('stockAlerts');
    if (!container) return;

    if (products.length === 0) {
        container.innerHTML = '<div class="empty-state">¡Excelente! No hay productos con stock bajo.</div>';
        return;
    }

    container.innerHTML = products.map(product => {
        const isOutOfStock = product.stock_quantity <= 0;
        return `
            <div class="stock-item ${isOutOfStock ? 'stock-item-critical' : 'stock-item-low'}">
                <div class="stock-info">
                    <div class="stock-name">${cleanInput(product.name)}</div>
                    <div class="stock-quantity">Stock: ${product.stock_quantity} ${product.unit}</div>
                    <div class="stock-min">Mínimo: ${product.min_stock}</div>
                </div>
                <span class="badge ${isOutOfStock ? 'badge-error' : 'badge-warning'}">
                    ${isOutOfStock ? 'Agotado' : 'Bajo'}
                </span>
            </div>
        `;
    }).join('');
}

function renderUpcomingDebts(debts) {
    const container = document.getElementById('debtsList');
    if (!container) return;

    if (debts.length === 0) {
        container.innerHTML = '<div class="empty-state">No hay deudas pendientes próximas a vencer.</div>';
        return;
    }

    container.innerHTML = debts.map(debt => {
        const dueDate = new Date(debt.due_date);
        const today = new Date();
        const isOverdue = dueDate < today;
        const diffDays = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
        
        return `
            <div class="debt-item ${isOverdue ? 'debt-item-overdue' : 'debt-item-upcoming'}">
                <div class="debt-info">
                    <div class="debt-customer">${cleanInput(debt.customer_name)}</div>
                    <div class="debt-amount">${formatCurrency(debt.pending_amount)}</div>
                    <div class="debt-date">
                        Vence: ${formatDate(debt.due_date)}
                        ${isOverdue ? '(Vencida)' : diffDays === 0 ? '(Hoy)' : `(${diffDays} días)`}
                    </div>
                </div>
                <div class="debt-actions">
                    ${debt.phone ? `<button class="btn btn-sm btn-outline" onclick="contactCustomer('${debt.phone}')">
                        <i class="fas fa-phone"></i>
                    </button>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function renderSalesChart(chartData) {
    const canvas = document.getElementById('salesChart');
    if (!canvas) return;

    const labels = [];
    const data = [];
    
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        labels.push(formatDate(dateStr, 'DD/MM'));
        
        const dayData = chartData.find(item => item.date === dateStr);
        data.push(dayData ? parseFloat(dayData.total) : 0);
    }

    if (typeof Chart !== 'undefined') {
        const ctx = canvas.getContext('2d');
        
        if (window.salesChartInstance) {
            window.salesChartInstance.destroy();
        }
        
        window.salesChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventas',
                    data: data,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    } else {
        canvas.style.display = 'none';
        const parent = canvas.parentElement;
        if (parent) {
            parent.innerHTML = '<div class="chart-fallback">Gráfico no disponible</div>';
        }
    }
}

function formatCurrency(amount) {
    return 'S/ ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString, format = 'DD/MM/YY HH:mm') {
    const date = new Date(dateString);
    
    if (format === 'DD/MM') {
        return date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
    }
    
    if (format === 'DD/MM/YY HH:mm') {
        return date.toLocaleDateString('es-PE', { 
            day: '2-digit', 
            month: '2-digit', 
            year: '2-digit' 
        }) + ' ' + date.toLocaleTimeString('es-PE', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
    
    return date.toLocaleDateString('es-PE');
}

function cleanInput(str) {
    if (!str) return '';
    return String(str).replace(/[<>&"']/g, function(match) {
        const escapeMap = {
            '<': '&lt;',
            '>': '&gt;',
            '&': '&amp;',
            '"': '&quot;',
            "'": '&#x27;'
        };
        return escapeMap[match];
    });
}

function contactCustomer(phone) {
    if (phone) {
        const message = encodeURIComponent('Hola, me comunico por el tema de su deuda pendiente.');
        window.open(`https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${message}`, '_blank');
    }
}

function updateChart(period) {
    console.log('Actualizando gráfico para periodo:', period);
    if (typeof Notifications !== 'undefined') {
        Notifications.info(`Periodo cambiado a: ${period === 'week' ? '7 días' : '30 días'}`);
    }
}

window.addEventListener('error', function(e) {
    console.error('Error en dashboard:', e.error);
});

function checkDependencies() {
    const missingDeps = [];
    
    if (typeof API === 'undefined') {
        missingDeps.push('API Client');
    }
    
    if (missingDeps.length > 0) {
        console.warn('Dependencias faltantes en dashboard:', missingDeps);
    }
}

checkDependencies();