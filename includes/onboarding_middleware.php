<?php
/**
 * MIDDLEWARE DE ONBOARDING
 * Archivo: includes/onboarding_middleware.php
 * Verifica que el usuario haya completado el proceso de onboarding
 */

/**
 * Verificar si el usuario ha completado el onboarding
 */
function hasCompletedOnboarding() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
        return false;
    }
    
    // Si ya tiene business_id, asumimos que completó el onboarding
    return !empty($_SESSION['business_id']);
}

/**
 * Verificar si estamos en una página de onboarding
 */
function isOnboardingPage() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $onboarding_pages = [
        'onboarding.php',
        'setup-business.php',
        'setup-complete.php'
    ];
    
    return in_array($current_page, $onboarding_pages);
}

/**
 * Verificar si estamos en una página de autenticación
 */
function isAuthPage() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $auth_pages = [
        'login.php',
        'register.php',
        'forgot-password.php',
        'reset-password.php'
    ];
    
    return in_array($current_page, $auth_pages);
}

/**
 * Requerir que el usuario haya completado el onboarding
 * Redirige a onboarding si no lo ha completado
 */
function requireOnboarding() {
    // No verificar en páginas de auth o onboarding
    if (isAuthPage() || isOnboardingPage()) {
        return;
    }
    
    // Verificar que el usuario esté logueado
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Verificar que haya completado el onboarding
    if (!hasCompletedOnboarding()) {
        header('Location: onboarding.php');
        exit();
    }
}

/**
 * Requerir que el usuario NO haya completado el onboarding
 * Redirige al dashboard si ya lo completó
 */
function requireNoOnboarding() {
    if (hasCompletedOnboarding()) {
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Obtener el progreso del onboarding
 */
function getOnboardingProgress() {
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }
    
    $progress = 0;
    
    // Paso 1: Usuario registrado (25%)
    if (isset($_SESSION['user_id'])) {
        $progress += 25;
    }
    
    // Paso 2: Información del negocio completada (50%)
    if (isset($_SESSION['business_id'])) {
        $progress += 25;
    }
    
    // Paso 3: Configuración básica (75%)
    if (isset($_SESSION['onboarding_config_done'])) {
        $progress += 25;
    }
    
    // Paso 4: Productos iniciales (100%)
    if (isset($_SESSION['onboarding_products_done'])) {
        $progress += 25;
    }
    
    return min($progress, 100);
}

/**
 * Marcar un paso del onboarding como completado
 */
function markOnboardingStep($step) {
    switch ($step) {
        case 'business':
            $_SESSION['onboarding_business_done'] = true;
            break;
        case 'config':
            $_SESSION['onboarding_config_done'] = true;
            break;
        case 'products':
            $_SESSION['onboarding_products_done'] = true;
            break;
        case 'complete':
            $_SESSION['onboarding_complete'] = true;
            break;
    }
}

/**
 * Verificar si un paso específico está completado
 */
function isStepCompleted($step) {
    switch ($step) {
        case 'user':
            return isset($_SESSION['user_id']);
        case 'business':
            return isset($_SESSION['business_id']);
        case 'config':
            return isset($_SESSION['onboarding_config_done']);
        case 'products':
            return isset($_SESSION['onboarding_products_done']);
        case 'complete':
            return isset($_SESSION['onboarding_complete']);
        default:
            return false;
    }
}

/**
 * Limpiar datos de onboarding (cuando se completa)
 */
function clearOnboardingData() {
    $keys_to_remove = [
        'onboarding_business_done',
        'onboarding_config_done',
        'onboarding_products_done'
    ];
    
    foreach ($keys_to_remove as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    $_SESSION['onboarding_complete'] = true;
}

/**
 * Obtener la siguiente página de onboarding
 */
function getNextOnboardingPage() {
    if (!isset($_SESSION['user_id'])) {
        return 'register.php';
    }
    
    if (!isset($_SESSION['business_id'])) {
        return 'onboarding.php?step=business';
    }
    
    if (!isStepCompleted('config')) {
        return 'onboarding.php?step=config';
    }
    
    if (!isStepCompleted('products')) {
        return 'onboarding.php?step=products';
    }
    
    return 'dashboard.php';
}

/**
 * Verificar si el usuario tiene permisos de administrador
 */
function requireAdmin() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        echo 'Acceso denegado. Se requieren permisos de administrador.';
        exit();
    }
}

/**
 * Verificar si el usuario pertenece al negocio
 */
function requireBusinessAccess($business_id = null) {
    if ($business_id && $_SESSION['business_id'] != $business_id) {
        header('HTTP/1.0 403 Forbidden');
        echo 'Acceso denegado. No tiene permisos para acceder a este negocio.';
        exit();
    }
}

/**
 * Obtener información del estado del onboarding para mostrar en UI
 */
function getOnboardingStatus() {
    return [
        'completed' => hasCompletedOnboarding(),
        'progress' => getOnboardingProgress(),
        'current_step' => getCurrentOnboardingStep(),
        'next_page' => getNextOnboardingPage(),
        'steps' => [
            'user' => isStepCompleted('user'),
            'business' => isStepCompleted('business'),
            'config' => isStepCompleted('config'),
            'products' => isStepCompleted('products'),
            'complete' => isStepCompleted('complete')
        ]
    ];
}

/**
 * Obtener el paso actual del onboarding
 */
function getCurrentOnboardingStep() {
    if (!isset($_SESSION['user_id'])) {
        return 'user';
    }
    
    if (!isset($_SESSION['business_id'])) {
        return 'business';
    }
    
    if (!isStepCompleted('config')) {
        return 'config';
    }
    
    if (!isStepCompleted('products')) {
        return 'products';
    }
    
    return 'complete';
}

/**
 * Crear datos de ejemplo para el onboarding
 */
function createSampleData($business_id) {
    try {
        require_once 'backend/config/database.php';
        $db = getDB();
        
        // Crear categorías de ejemplo
        $categories = [
            ['name' => 'Bebidas', 'color' => '#007bff'],
            ['name' => 'Snacks', 'color' => '#28a745'],
            ['name' => 'Limpieza', 'color' => '#ffc107'],
            ['name' => 'Hogar', 'color' => '#6f42c1']
        ];
        
        $category_ids = [];
        foreach ($categories as $category) {
            $category_id = $db->insert('categories', [
                'business_id' => $business_id,
                'name' => $category['name'],
                'color' => $category['color'],
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $category_ids[] = $category_id;
        }
        
        // Crear productos de ejemplo
        $products = [
            ['name' => 'Coca Cola 500ml', 'category' => 0, 'cost' => 1.50, 'price' => 2.50, 'stock' => 50],
            ['name' => 'Galletas Oreo', 'category' => 1, 'cost' => 3.00, 'price' => 4.50, 'stock' => 30],
            ['name' => 'Detergente Ariel', 'category' => 2, 'cost' => 12.00, 'price' => 18.00, 'stock' => 15],
            ['name' => 'Papel Higiénico', 'category' => 3, 'cost' => 8.00, 'price' => 12.00, 'stock' => 25]
        ];
        
        foreach ($products as $product) {
            $product_id = $db->insert('products', [
                'business_id' => $business_id,
                'category_id' => $category_ids[$product['category']],
                'name' => $product['name'],
                'sku' => strtoupper(substr(md5($product['name']), 0, 8)),
                'cost_price' => $product['cost'],
                'sale_price' => $product['price'],
                'stock_quantity' => $product['stock'],
                'min_stock' => 5,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Crear movimiento de inventario inicial
            $db->insert('inventory_movements', [
                'business_id' => $business_id,
                'product_id' => $product_id,
                'movement_type' => 'in',
                'quantity' => $product['stock'],
                'unit_cost' => $product['cost'],
                'total_cost' => $product['cost'] * $product['stock'],
                'reason' => 'Stock inicial',
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Crear cliente de ejemplo
        $db->insert('customers', [
            'business_id' => $business_id,
            'first_name' => 'Cliente',
            'last_name' => 'General',
            'email' => 'cliente@ejemplo.com',
            'phone' => '999999999',
            'address' => 'Dirección de ejemplo',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Crear configuraciones básicas
        $settings = [
            ['key' => 'currency', 'value' => 'PEN', 'type' => 'string'],
            ['key' => 'tax_rate', 'value' => '18', 'type' => 'decimal'],
            ['key' => 'low_stock_threshold', 'value' => '10', 'type' => 'int'],
            ['key' => 'email_notifications', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'receipt_footer', 'value' => 'Gracias por su compra', 'type' => 'string']
        ];
        
        foreach ($settings as $setting) {
            $db->insert('settings', [
                'business_id' => $business_id,
                'setting_key' => $setting['key'],
                'setting_value' => $setting['value'],
                'setting_type' => $setting['type'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('Error creating sample data: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función para debugging del onboarding
 */
function debugOnboarding() {
    if (!isset($_GET['debug_onboarding'])) {
        return;
    }
    
    echo '<div style="background: #f8f9fa; padding: 10px; margin: 10px; border: 1px solid #ddd;">';
    echo '<h4>Debug Onboarding</h4>';
    echo '<p><strong>User ID:</strong> ' . ($_SESSION['user_id'] ?? 'No set') . '</p>';
    echo '<p><strong>Business ID:</strong> ' . ($_SESSION['business_id'] ?? 'No set') . '</p>';
    echo '<p><strong>Has Completed:</strong> ' . (hasCompletedOnboarding() ? 'Yes' : 'No') . '</p>';
    echo '<p><strong>Progress:</strong> ' . getOnboardingProgress() . '%</p>';
    echo '<p><strong>Current Step:</strong> ' . getCurrentOnboardingStep() . '</p>';
    echo '<p><strong>Next Page:</strong> ' . getNextOnboardingPage() . '</p>';
    echo '<p><strong>Current Page:</strong> ' . basename($_SERVER['PHP_SELF']) . '</p>';
    echo '<p><strong>Is Auth Page:</strong> ' . (isAuthPage() ? 'Yes' : 'No') . '</p>';
    echo '<p><strong>Is Onboarding Page:</strong> ' . (isOnboardingPage() ? 'Yes' : 'No') . '</p>';
    echo '</div>';
}

/**
 * Inicializar middleware de onboarding
 */
function initOnboardingMiddleware() {
    // Asegurar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Debug si está habilitado
    if (defined('ONBOARDING_DEBUG') && ONBOARDING_DEBUG) {
        debugOnboarding();
    }
}

// Auto-inicializar el middleware
initOnboardingMiddleware();
?>