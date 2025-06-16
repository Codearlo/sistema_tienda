<?php
/**
 * CONFIGURACIÓN PRINCIPAL - TREINTA APP
 * Archivo: backend/config/config.php
 */

// Prevenir acceso directo
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Configuración de error reporting para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la aplicación
if (!defined('APP_NAME')) define('APP_NAME', 'Treinta App');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', 'https://tu-dominio.com');

// Configuración de rutas
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__DIR__)));
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', ROOT_PATH . '/backend/config');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', ROOT_PATH . '/includes');
if (!defined('ASSETS_PATH')) define('ASSETS_PATH', ROOT_PATH . '/assets');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', ROOT_PATH . '/uploads');
if (!defined('API_PATH')) define('API_PATH', ROOT_PATH . '/api');

// URLs públicas
if (!defined('BASE_URL')) define('BASE_URL', APP_URL);
if (!defined('ASSETS_URL')) define('ASSETS_URL', APP_URL . '/assets');
if (!defined('UPLOADS_URL')) define('UPLOADS_URL', APP_URL . '/uploads');
if (!defined('API_URL')) define('API_URL', APP_URL . '/api');

// Configuración de sesión (debe ir antes de session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400); // 24 horas
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

// Configuración de zona horaria
date_default_timezone_set('America/Lima'); // Perú

// Configuración de paginación
if (!defined('RECORDS_PER_PAGE')) define('RECORDS_PER_PAGE', 20);
if (!defined('MAX_RECORDS_PER_PAGE')) define('MAX_RECORDS_PER_PAGE', 100);

// Configuración de archivos
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
if (!defined('ALLOWED_DOCUMENT_TYPES')) define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Configuración de seguridad
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 8);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 300); // 5 minutos

// Configuración de email
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.hostinger.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'noreply@tu-dominio.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'TU_PASSWORD_EMAIL');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', APP_NAME);

// Configuración de moneda
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'S/'); // Sol peruano
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', 'PEN');
if (!defined('DECIMAL_PLACES')) define('DECIMAL_PLACES', 2);

// Estados de la aplicación
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 1);
if (!defined('STATUS_INACTIVE')) define('STATUS_INACTIVE', 0);
if (!defined('STATUS_DELETED')) define('STATUS_DELETED', -1);

// Tipos de usuario
if (!defined('USER_TYPE_ADMIN')) define('USER_TYPE_ADMIN', 'admin');
if (!defined('USER_TYPE_OWNER')) define('USER_TYPE_OWNER', 'owner');
if (!defined('USER_TYPE_MANAGER')) define('USER_TYPE_MANAGER', 'manager'); 
if (!defined('USER_TYPE_EMPLOYEE')) define('USER_TYPE_EMPLOYEE', 'employee');
if (!defined('USER_TYPE_CASHIER')) define('USER_TYPE_CASHIER', 'cashier');

// Tipos de movimiento de inventario
if (!defined('INVENTORY_IN')) define('INVENTORY_IN', 'in');
if (!defined('INVENTORY_OUT')) define('INVENTORY_OUT', 'out');
if (!defined('INVENTORY_ADJUSTMENT')) define('INVENTORY_ADJUSTMENT', 'adjustment');

// Métodos de pago
if (!defined('PAYMENT_CASH')) define('PAYMENT_CASH', 'cash');
if (!defined('PAYMENT_CARD')) define('PAYMENT_CARD', 'card');
if (!defined('PAYMENT_TRANSFER')) define('PAYMENT_TRANSFER', 'transfer');
if (!defined('PAYMENT_CREDIT')) define('PAYMENT_CREDIT', 'credit');

// Configuración de notificaciones
if (!defined('NOTIFICATION_SUCCESS')) define('NOTIFICATION_SUCCESS', 'success');
if (!defined('NOTIFICATION_ERROR')) define('NOTIFICATION_ERROR', 'error');
if (!defined('NOTIFICATION_WARNING')) define('NOTIFICATION_WARNING', 'warning');
if (!defined('NOTIFICATION_INFO')) define('NOTIFICATION_INFO', 'info');

// Configuración de WhatsApp (para futuro)
if (!defined('WHATSAPP_API_URL')) define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send');
if (!defined('WHATSAPP_BUSINESS_NUMBER')) define('WHATSAPP_BUSINESS_NUMBER', '+51999999999'); // Cambiar por número real

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
 * Función para incluir archivos de forma segura
 */
function safeInclude($file) {
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
    if ($user === null && function_exists('getDB')) {
        try {
            $db = getDB();
            $user = $db->single(
                "SELECT * FROM users WHERE id = ? AND status = ?", 
                [$_SESSION['user_id'], STATUS_ACTIVE]
            );
        } catch (Exception $e) {
            error_log('Error getting current user: ' . $e->getMessage());
            return null;
        }
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
    
    // Owner tiene todos los permisos de su negocio
    if ($user['user_type'] === USER_TYPE_OWNER) return true;
    
    // Aquí puedes implementar lógica más compleja de permisos
    return true;
}

/**
 * Función para formatear moneda
 */
function formatCurrency($amount) {
    if (!defined('CURRENCY_SYMBOL')) return number_format($amount, 2);
    if (!defined('DECIMAL_PLACES')) return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
    return CURRENCY_SYMBOL . ' ' . number_format($amount, DECIMAL_PLACES);
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
 * Función para limpiar input
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
 * Función para generar token seguro
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Función para redirect con mensaje
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Función para mostrar mensajes flash
 */
function showFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Autoloader simple para clases
 */
spl_autoload_register(function ($className) {
    $file = INCLUDES_PATH . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Incluir archivos esenciales solo si existen
if (file_exists(CONFIG_PATH . '/database.php')) {
    require_once CONFIG_PATH . '/database.php';
}

// Crear directorios necesarios si no existen
$directories = [
    UPLOADS_PATH,
    UPLOADS_PATH . '/products',
    UPLOADS_PATH . '/receipts',
    UPLOADS_PATH . '/documents',
    UPLOADS_PATH . '/temp'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Log de inicialización
if (getConfig('enable_logging', true)) {
    error_log('[' . date('Y-m-d H:i:s') . '] Aplicación inicializada - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}
?>