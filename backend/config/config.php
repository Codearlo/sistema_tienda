<?php
/**
 * Configuración General - Treinta App
 * Archivo: config/config.php
 */

// Configuración del error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la aplicación
define('APP_NAME', 'Treinta - Gestión de Negocios');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://tu-dominio.com'); // Cambiar por tu dominio real

// Configuración de rutas
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('API_PATH', ROOT_PATH . '/api');

// URLs públicas
define('BASE_URL', APP_URL);
define('ASSETS_URL', APP_URL . '/assets');
define('UPLOADS_URL', APP_URL . '/uploads');
define('API_URL', APP_URL . '/api');

// Configuración de sesión
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400);
session_start();

// Configuración de zona horaria
date_default_timezone_set('America/Lima'); // Perú

// Configuración de paginación
define('RECORDS_PER_PAGE', 20);
define('MAX_RECORDS_PER_PAGE', 100);

// Configuración de archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Configuración de seguridad
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutos

// Configuración de email
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@tu-dominio.com');
define('SMTP_PASSWORD', 'TU_PASSWORD_EMAIL');
define('SMTP_FROM_NAME', APP_NAME);

// Configuración de moneda
define('CURRENCY_SYMBOL', 'S/'); // Sol peruano
define('CURRENCY_CODE', 'PEN');
define('DECIMAL_PLACES', 2);

// Estados de la aplicación
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);
define('STATUS_DELETED', -1);

// Tipos de usuario
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_MANAGER', 'manager'); 
define('USER_TYPE_EMPLOYEE', 'employee');
define('USER_TYPE_CASHIER', 'cashier');

// Tipos de movimiento de inventario
define('INVENTORY_IN', 'in');
define('INVENTORY_OUT', 'out');
define('INVENTORY_ADJUSTMENT', 'adjustment');

// Métodos de pago
define('PAYMENT_CASH', 'cash');
define('PAYMENT_CARD', 'card');
define('PAYMENT_TRANSFER', 'transfer');
define('PAYMENT_CREDIT', 'credit');

// Configuración de notificaciones
define('NOTIFICATION_SUCCESS', 'success');
define('NOTIFICATION_ERROR', 'error');
define('NOTIFICATION_WARNING', 'warning');
define('NOTIFICATION_INFO', 'info');

// Configuración de WhatsApp (para futuro)
define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send');
define('WHATSAPP_BUSINESS_NUMBER', '+51999999999'); // Cambiar por número real

/**
 * Función para cargar archivos de configuración
 */
function loadConfig($configFile) {
    $path = CONFIG_PATH . '/' . $configFile . '.php';
    if (file_exists($path)) {
        return require $path;
    }
    return false;
}

/**
 * Función para obtener configuración específica
 */
function getConfig($key, $default = null) {
    static $config = [];
    
    if (empty($config)) {
        $config = loadConfig('settings') ?: [];
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Función para formatear moneda
 */
function formatCurrency($amount, $includeSymbol = true) {
    $formatted = number_format($amount, DECIMAL_PLACES, '.', ',');
    return $includeSymbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

/**
 * Función para formatear fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Función para formatear fecha y hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Función para limpiar y validar datos
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Función para generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para validar token CSRF
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para redirigir
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
}

/**
 * Función para incluir archivos de forma segura
 */
function includeFile($file) {
    $path = INCLUDES_PATH . '/' . $file . '.php';
    if (file_exists($path)) {
        include $path;
        return true;
    }
    return false;
}

/**
 * Función para verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Función para obtener el usuario actual
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $user = $db->single(
            "SELECT * FROM users WHERE id = ? AND status = ?", 
            [$_SESSION['user_id'], STATUS_ACTIVE]
        );
    }
    return $user;
}

/**
 * Función para verificar permisos
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Admin tiene todos los permisos
    if ($user['user_type'] === USER_TYPE_ADMIN) return true;
    
    // Aquí puedes implementar lógica más compleja de permisos
    return true;
}

/**
 * Autoloader simple para clases
 */
spl_autoload_register(function ($className) {
    $file = INCLUDES_PATH . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Incluir archivos esenciales
require_once CONFIG_PATH . '/database.php';

// Crear directorios necesarios si no existen
$directories = [
    UPLOADS_PATH,
    UPLOADS_PATH . '/products',
    UPLOADS_PATH . '/receipts',
    UPLOADS_PATH . '/documents'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Log de inicialización
if (getConfig('enable_logging', true)) {
    error_log('[' . date('Y-m-d H:i:s') . '] Aplicación inicializada - IP: ' . $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
?>