<?php
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

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
    <div class="sidebar-header">
        <svg class="sidebar-logo" viewBox="0 0 100 100" width="40" height="40">
            <circle cx="50" cy="50" r="45" fill="#2563eb"/>
            <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
        </svg>
        <div class="sidebar-title-section">
            <h2 class="sidebar-title">Treinta</h2>
            <p class="sidebar-business"><?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Mi Negocio'); ?></p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-nav-list">
            <?php foreach ($menu_items as $key => $item): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo $item['url']; ?>" class="sidebar-nav-link <?php echo $current_page === $key ? 'active' : ''; ?>" data-page="<?php echo $key; ?>" data-tooltip="<?php echo $item['label']; ?>">
                        <svg class="sidebar-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php echo $item['icon']; ?>
                        </svg>
                        <span class="sidebar-nav-label"><?php echo $item['label']; ?></span>
                        <?php if ($key === 'pos'): ?>
                            <span class="sidebar-nav-badge">POS</span>
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
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_type']); ?></div>
            </div>
        </div>
        <div class="sidebar-actions">
            <a href="backend/auth/logout.php" class="sidebar-action-btn" title="Cerrar sesión">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!sidebar || !overlay) return;
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    
    if (sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!sidebar || !overlay) return;
    
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('mobileOverlay');
    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }
});

window.toggleMobileSidebar = toggleMobileSidebar;
window.closeMobileSidebar = closeMobileSidebar;
</script>