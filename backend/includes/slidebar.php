<?php
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}

$menu_items = [
    'dashboard' => ['url' => 'dashboard.php', 'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>', 'label' => 'Dashboard'],
    'pos' => ['url' => 'pos.php', 'icon' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', 'label' => 'Punto de Venta'],
    'sales' => ['url' => 'sales.php', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M16 8l-4 4-4-4"/>', 'label' => 'Ventas'],
    'products' => ['url' => 'products.php', 'icon' => '<path d="M20 7h-9a2 2 0 0 1-2-2V2"/><path d="M9 2v5a2 2 0 0 0 2 2h9"/><path d="M3 13.6V7a2 2 0 0 1 2-2h5"/><path d="M3 21h18"/>', 'label' => 'Productos'],
    'customers' => ['url' => 'customers.php', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', 'label' => 'Clientes'],
    'expenses' => ['url' => 'expenses.php', 'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>', 'label' => 'Gastos'],
    'debts' => ['url' => 'debts.php', 'icon' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', 'label' => 'Deudas'],
    'reports' => ['url' => 'reports.php', 'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>', 'label' => 'Reportes'],
    'employees' => ['url' => 'employees.php', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'label' => 'Empleados'],
    'settings' => ['url' => 'settings.php', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>', 'label' => 'Configuración']
];
?>
<div class="mobile-overlay" id="mobileOverlay"></div>

<aside class="sidebar" id="sidebar">
    <!-- Botón de colapso -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15,18 9,12 15,6"/>
        </svg>
    </button>

    <div class="sidebar-header">
        <svg class="sidebar-logo" viewBox="0 0 100 100" width="40" height="40">
            <circle cx="50" cy="50" r="45" fill="#2563eb"/>
            <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
        </svg>
        <div class="sidebar-title-section sidebar-content-expanded">
            <h2 class="sidebar-title">Treinta</h2>
            <p class="sidebar-business"><?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Mi Negocio'); ?></p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-nav-list">
            <?php foreach ($menu_items as $key => $item): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo $item['url']; ?>" class="sidebar-nav-link" data-page="<?php echo $key; ?>" data-tooltip="<?php echo $item['label']; ?>">
                        <svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php echo $item['icon']; ?>
                        </svg>
                        <span class="sidebar-nav-label sidebar-content-expanded"><?php echo $item['label']; ?></span>
                        <?php if ($key === 'pos'): ?>
                            <span class="sidebar-nav-badge sidebar-content-expanded">POS</span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="user-info sidebar-content-expanded">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_type']); ?></div>
            </div>
        </div>
        <div class="sidebar-actions sidebar-content-expanded">
            <button class="sidebar-action-btn" onclick="toggleTheme()" title="Cambiar tema">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </button>
            <a href="backend/auth/logout.php" class="sidebar-action-btn" title="Cerrar sesión">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<script>
// ===== FUNCIONALIDAD MEJORADA DEL SIDEBAR =====

// Inicialización del sidebar
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
});

function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const overlay = document.getElementById('mobileOverlay');
    
    // Verificar que los elementos existen
    if (!sidebar) {
        console.error('Sidebar no encontrado');
        return;
    }
    
    // Restaurar estado guardado
    restoreSidebarState();
    
    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }
    
    // Event listeners para hover en sidebar colapsado
    setupHoverEvents();
    
    // Marcar enlace activo
    setActiveSidebarLink();
    
    console.log('Sidebar inicializado correctamente');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    sidebar.classList.toggle('collapsed');
    
    // Guardar estado
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
    
    console.log('Sidebar toggled:', isCollapsed ? 'collapsed' : 'expanded');
    
    // Trigger resize event para que otros componentes se ajusten
    window.dispatchEvent(new Event('resize'));
}

function setupHoverEvents() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    let hoverTimeout;
    
    sidebar.addEventListener('mouseenter', function() {
        if (sidebar.classList.contains('collapsed')) {
            clearTimeout(hoverTimeout);
            sidebar.classList.add('hover-expanded');
            console.log('Sidebar hover: expanded');
        }
    });
    
    sidebar.addEventListener('mouseleave', function() {
        if (sidebar.classList.contains('collapsed')) {
            hoverTimeout = setTimeout(() => {
                sidebar.classList.remove('hover-expanded');
                console.log('Sidebar hover: collapsed');
            }, 300); // Delay para evitar flickering
        }
    });
}

function restoreSidebarState() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        console.log('Sidebar restored: collapsed');
    } else {
        sidebar.classList.remove('collapsed');
        console.log('Sidebar restored: expanded');
    }
}

function setActiveSidebarLink() {
    // Obtener página actual
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const pageName = currentPage.replace('.php', '').replace('.html', '');
    
    // Limpiar enlaces activos
    document.querySelectorAll('.sidebar-nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Marcar enlace activo
    const activeLink = document.querySelector(`.sidebar-nav-link[data-page="${pageName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
        console.log('Active link set:', pageName);
    } else {
        console.log('No active link found for:', pageName);
    }
}

// Funciones para móvil
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!sidebar || !overlay) return;
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    
    // Prevenir scroll del body cuando el sidebar está abierto
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
    
    console.log('Mobile sidebar toggled');
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!sidebar || !overlay) return;
    
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
    
    console.log('Mobile sidebar closed');
}

function toggleTheme() {
    console.log('Toggle theme');
}

// Debug function para verificar estado
function debugSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
        console.log('❌ Sidebar element not found');
        return;
    }
    
    console.log('🔍 Sidebar Debug Info:');
    console.log('- Element exists:', !!sidebar);
    console.log('- Classes:', sidebar.className);
    console.log('- Collapsed:', sidebar.classList.contains('collapsed'));
    console.log('- Open (mobile):', sidebar.classList.contains('open'));
    console.log('- Hover expanded:', sidebar.classList.contains('hover-expanded'));
    console.log('- Local storage:', localStorage.getItem('sidebarCollapsed'));
    
    const toggle = document.querySelector('.sidebar-toggle');
    console.log('- Toggle button exists:', !!toggle);
    
    const overlay = document.getElementById('mobileOverlay');
    console.log('- Mobile overlay exists:', !!overlay);
}

// Exponer funciones globalmente
window.toggleSidebar = toggleSidebar;
window.toggleMobileSidebar = toggleMobileSidebar;
window.closeMobileSidebar = closeMobileSidebar;
window.debugSidebar = debugSidebar;
window.toggleTheme = toggleTheme;

// Auto-ejecutar debug en desarrollo
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    setTimeout(debugSidebar, 1000);
}
</script>