<?php
/**
 * BARRA LATERAL DE NAVEGACIÓN
 * Archivo: includes/slidebar.php
 */

require_once __DIR__ . '/cache_control.php';

if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Función para nombres cortos
function getDisplayName($fullName, $maxLength = 15) {
    if (strlen($fullName) <= $maxLength) {
        return $fullName;
    }
    $names = explode(' ', $fullName);
    return $names[0];
}

$user_display_name = getDisplayName($_SESSION['user_name'] ?? 'Usuario', 15);

// Menú de navegación
$menu_items = [
    'dashboard' => [
        'url' => 'dashboard.php', 
        'icon' => 'fas fa-home', 
        'label' => 'Dashboard'
    ],
    'pos' => [
        'url' => 'pos.php', 
        'icon' => 'fas fa-cash-register', 
        'label' => 'Punto de Venta', 
        'badge' => 'POS'
    ],
    'sales' => [
        'url' => 'sales.php', 
        'icon' => 'fas fa-chart-line', 
        'label' => 'Ventas'
    ],
    'products' => [
        'url' => 'products.php', 
        'icon' => 'fas fa-box', 
        'label' => 'Productos'
    ],
    'customers' => [
        'url' => 'customers.php', 
        'icon' => 'fas fa-users', 
        'label' => 'Clientes'
    ],
    'expenses' => [
        'url' => 'expenses.php', 
        'icon' => 'fas fa-receipt', 
        'label' => 'Gastos'
    ],
    'debts' => [
        'url' => 'debts.php', 
        'icon' => 'fas fa-credit-card', 
        'label' => 'Deudas'
    ],
    'reports' => [
        'url' => 'reports.php', 
        'icon' => 'fas fa-chart-bar', 
        'label' => 'Reportes'
    ],
    'employees' => [
        'url' => 'employees.php', 
        'icon' => 'fas fa-user-tie', 
        'label' => 'Empleados'
    ],
    'settings' => [
        'url' => 'settings.php', 
        'icon' => 'fas fa-cog', 
        'label' => 'Configuración'
    ]
];

// Filtrar menú según tipo de usuario
$user_type = $_SESSION['user_type'] ?? 'employee';
if ($user_type === 'employee' || $user_type === 'cashier') {
    unset($menu_items['employees'], $menu_items['settings'], $menu_items['reports']);
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="assets/images/logo.png" alt="Treinta" class="logo-img">
            <span class="logo-text">Treinta</span>
        </div>
        <button class="sidebar-toggle mobile-only" onclick="toggleMobileSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php foreach ($menu_items as $key => $item): ?>
                <li class="nav-item <?php echo ($current_page === $key) ? 'active' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="nav-link">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span class="nav-text"><?php echo $item['label']; ?></span>
                        <?php if (isset($item['badge'])): ?>
                            <span class="nav-badge"><?php echo $item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_display_name); ?></div>
                <div class="user-role"><?php echo ucfirst($_SESSION['user_type'] ?? 'Usuario'); ?></div>
            </div>
        </div>
        
        <div class="sidebar-actions">
            <a href="profile.php" class="action-btn" title="Perfil">
                <i class="fas fa-user-circle"></i>
            </a>
            <a href="logout.php" class="action-btn" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    
    sidebar.classList.toggle('sidebar-open');
    overlay.classList.toggle('active');
    body.classList.toggle('sidebar-mobile-open');
}

// Cerrar sidebar en móvil al hacer clic en un enlace
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            toggleMobileSidebar();
        }
    });
});

// Cerrar sidebar al redimensionar ventana
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('sidebar-open');
        document.getElementById('sidebarOverlay').classList.remove('active');
        document.body.classList.remove('sidebar-mobile-open');
    }
});
</script>