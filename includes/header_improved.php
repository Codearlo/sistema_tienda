<?php
/**
 * Header Mejorado con Sistema de Notificaciones Optimizado
 */

require_once 'notification_config.php';

// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['user_id']);
$showNotifications = $isLoggedIn && areNotificationsEnabledForUser($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Treinta - Sistema POS'; ?></title>
    
    <!-- CSS Base -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Meta tags para PWA -->
    <meta name="theme-color" content="#1f2937">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <?php if ($showNotifications): ?>
        <!-- Configuración del sistema de notificaciones -->
        <?php echo getNotificationConfigScript(); ?>
        
        <!-- Verificación de compatibilidad -->
        <script>
            window.BROWSER_COMPATIBILITY = <?php echo json_encode(getBrowserCompatibility()); ?>;
            window.USER_NOTIFICATION_PREFERENCES = <?php echo json_encode(getUserNotificationPreferences($_SESSION['user_id'])); ?>;
        </script>
    <?php endif; ?>
    
    <!-- CSS específico de la página -->
    <?php if (isset($pageCss)): ?>
        <?php foreach ($pageCss as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?php echo $bodyClass ?? ''; ?> <?php echo $isLoggedIn ? 'dashboard-page' : ''; ?>" data-page="<?php echo $currentPage ?? ''; ?>">

<?php if ($isLoggedIn): ?>
    <!-- Header de navegación -->
    <header class="dashboard-header">
        <div class="header-container">
            <!-- Logo y navegación principal -->
            <div class="header-left">
                <div class="logo">
                    <a href="dashboard.php">
                        <img src="assets/images/logo.png" alt="Treinta" height="32">
                    </a>
                </div>
                
                <nav class="main-nav">
                    <a href="dashboard.php" class="nav-item <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="pos.php" class="nav-item <?php echo ($currentPage === 'pos') ? 'active' : ''; ?>">
                        <i class="fas fa-cash-register"></i>
                        <span>Punto de Venta</span>
                    </a>
                    <a href="products.php" class="nav-item <?php echo ($currentPage === 'products') ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>
                        <span>Productos</span>
                    </a>
                    <a href="sales.php" class="nav-item <?php echo ($currentPage === 'sales') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Ventas</span>
                    </a>
                    <a href="customers.php" class="nav-item <?php echo ($currentPage === 'customers') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
                    </a>
                </nav>
            </div>
            
            <!-- Área derecha del header -->
            <div class="header-right">
                <!-- Indicador de notificaciones -->
                <?php if ($showNotifications): ?>
                    <div class="notification-indicator" id="notificationIndicator">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                    </div>
                <?php endif; ?>
                
                <!-- Menú de usuario -->
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-toggle" id="userMenuToggle">
                        <img src="assets/images/default-avatar.png" alt="Usuario" class="user-avatar">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['business_name'] ?? 'Usuario'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Mi Perfil</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                        <?php if ($showNotifications): ?>
                            <a href="notification-settings.php" class="dropdown-item">
                                <i class="fas fa-bell"></i>
                                <span>Notificaciones</span>
                            </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mensaje de compatibilidad para navegadores antiguos -->
    <?php if ($showNotifications): ?>
        <div id="browserCompatibilityWarning" style="display: none;">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Tu navegador no soporta todas las características de notificaciones. 
                Por favor actualiza tu navegador para una mejor experiencia.
            </div>
        </div>
    <?php endif; ?>

    <!-- Navegación móvil -->
    <nav class="mobile-nav">
        <a href="dashboard.php" class="mobile-nav-item <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <a href="pos.php" class="mobile-nav-item <?php echo ($currentPage === 'pos') ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>POS</span>
        </a>
        <a href="products.php" class="mobile-nav-item <?php echo ($currentPage === 'products') ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            <span>Productos</span>
        </a>
        <a href="sales.php" class="mobile-nav-item <?php echo ($currentPage === 'sales') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Ventas</span>
        </a>
        <a href="customers.php" class="mobile-nav-item <?php echo ($currentPage === 'customers') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Clientes</span>
        </a>
    </nav>

    <!-- Overlay para menús móviles -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

<?php endif; ?>

<!-- JavaScript base -->
<script>
// Funciones básicas del header
document.addEventListener('DOMContentLoaded', function() {
    // Menú de usuario
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    if (userMenuToggle && userMenuDropdown) {
        userMenuToggle.addEventListener('click', function() {
            userMenuDropdown.classList.toggle('show');
        });
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                userMenuDropdown.classList.remove('show');
            }
        });
    }
    
    <?php if ($showNotifications): ?>
        // Verificar compatibilidad del navegador
        if (window.BROWSER_COMPATIBILITY && !window.BROWSER_COMPATIBILITY.is_compatible) {
            const warning = document.getElementById('browserCompatibilityWarning');
            if (warning) {
                warning.style.display = 'block';
            }
        }
        
        // Inicializar badge de notificaciones
        if (window.notificationSystem) {
            window.notificationSystem.onNotificationReceived = function(count) {
                const badge = document.getElementById('notificationBadge');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            };
        }
    <?php endif; ?>
});
</script>

<?php if ($showNotifications): ?>
    <!-- Cargar sistema de notificaciones mejorado -->
    <script src="assets/js/notifications-improved.js"></script>
<?php endif; ?>

<!-- Contenido principal -->
<main class="main-content <?php echo $isLoggedIn ? 'dashboard-main' : ''; ?>">