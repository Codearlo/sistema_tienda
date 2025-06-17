<?php
/**
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA
 * Archivo: backend/config/config.php
 */

// Mostrar errores solo en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== CONFIGURACIÓN DE RUTAS =====
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(dirname(__DIR__)));
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', __DIR__);
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', BASE_PATH . '/includes');
if (!defined('ASSETS_PATH')) define('ASSETS_PATH', BASE_PATH . '/assets');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', BASE_PATH . '/uploads');

// ===== CONFIGURACIÓN DE LA APLICACIÓN =====
if (!defined('APP_NAME')) define('APP_NAME', 'Treinta - Sistema de Ventas');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_DESCRIPTION')) define('APP_DESCRIPTION', 'Sistema de punto de venta para pequeñas y medianas empresas');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/treinta');
if (!defined('TIMEZONE')) define('TIMEZONE', 'America/Lima');

// Configurar zona horaria
date_default_timezone_set(TIMEZONE);

// ===== CONFIGURACIÓN DE BASE DE DATOS =====
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'treinta_pos');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ===== CONFIGURACIÓN DE SEGURIDAD =====
if (!defined('SECRET_KEY')) define('SECRET_KEY', 'tu_clave_secreta_aqui');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 86400); // 24 horas
if (!defined('BCRYPT_COST')) define('BCRYPT_COST', 12);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// ===== CONFIGURACIÓN DE ARCHIVOS =====
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5242880); // 5MB
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
if (!defined('ALLOWED_DOCUMENT_TYPES')) define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// ===== CONFIGURACIÓN DE EMAIL =====
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'noreply@tu-dominio.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'TU_PASSWORD_EMAIL');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', APP_NAME);

// ===== CONFIGURACIÓN DE MONEDA =====
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'S/'); // Sol peruano
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', 'PEN');
if (!defined('DECIMAL_PLACES')) define('DECIMAL_PLACES', 2);

// ===== ESTADOS DE LA APLICACIÓN =====
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 1);
if (!defined('STATUS_INACTIVE')) define('STATUS_INACTIVE', 0);
if (!defined('STATUS_DELETED')) define('STATUS_DELETED', -1);

// ===== TIPOS DE USUARIO =====
if (!defined('USER_TYPE_ADMIN')) define('USER_TYPE_ADMIN', 'admin');
if (!defined('USER_TYPE_OWNER')) define('USER_TYPE_OWNER', 'owner');
if (!defined('USER_TYPE_MANAGER')) define('USER_TYPE_MANAGER', 'manager'); 
if (!defined('USER_TYPE_EMPLOYEE')) define('USER_TYPE_EMPLOYEE', 'employee');
if (!defined('USER_TYPE_CASHIER')) define('USER_TYPE_CASHIER', 'cashier');

// ===== TIPOS DE MOVIMIENTO DE INVENTARIO =====
if (!defined('INVENTORY_IN')) define('INVENTORY_IN', 'in');
if (!defined('INVENTORY_OUT')) define('INVENTORY_OUT', 'out');
if (!defined('INVENTORY_ADJUSTMENT')) define('INVENTORY_ADJUSTMENT', 'adjustment');

// ===== MÉTODOS DE PAGO =====
if (!defined('PAYMENT_CASH')) define('PAYMENT_CASH', 'cash');
if (!defined('PAYMENT_CARD')) define('PAYMENT_CARD', 'card');
if (!defined('PAYMENT_TRANSFER')) define('PAYMENT_TRANSFER', 'transfer');
if (!defined('PAYMENT_CREDIT')) define('PAYMENT_CREDIT', 'credit');

// ===== CONFIGURACIÓN DE WHATSAPP =====
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
        return require_once $path;
    }
    return false;
}

/**
 * Función para redireccionar
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    } else {
        echo '<script>window.location.href = "' . $url . '";</script>';
        exit();
    }
}

/**
 * Función para limpiar entrada de datos
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
 * Función para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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
 * Función para verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para formatear moneda
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, DECIMAL_PLACES, '.', ',');
}

/**
 * Función para formatear fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format($format);
}

/**
 * Función para formatear fecha y hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (is_string($datetime)) {
        $datetime = new DateTime($datetime);
    }
    return $datetime->format($format);
}

/**
 * Función para log de errores personalizado
 */
function logError($message, $file = null, $line = null) {
    $logMessage = date('Y-m-d H:i:s') . " - ERROR: " . $message;
    if ($file) {
        $logMessage .= " in file: " . $file;
    }
    if ($line) {
        $logMessage .= " on line: " . $line;
    }
    error_log($logMessage . PHP_EOL, 3, BASE_PATH . '/logs/error.log');
}

/**
 * Función para log de actividades
 */
function logActivity($user_id, $action, $details = null) {
    $logMessage = date('Y-m-d H:i:s') . " - USER ID: " . $user_id . " - ACTION: " . $action;
    if ($details) {
        $logMessage .= " - DETAILS: " . $details;
    }
    error_log($logMessage . PHP_EOL, 3, BASE_PATH . '/logs/activity.log');
}

/**
 * Verificar si estamos en modo de desarrollo
 */
function isDevelopmentMode() {
    return (defined('ENVIRONMENT') && ENVIRONMENT === 'development') || 
           (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']));
}

/**
 * Configurar manejo de errores basado en el entorno
 */
if (isDevelopmentMode()) {
    // Modo desarrollo - mostrar errores
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // Modo producción - ocultar errores
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Autoloader simple
spl_autoload_register(function ($className) {
    $directories = [
        BASE_PATH . '/backend/classes/',
        BASE_PATH . '/backend/models/',
        BASE_PATH . '/backend/controllers/',
        BASE_PATH . '/includes/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Función para cargar dependencias críticas
 */
function loadCriticalDependencies() {
    // Cargar database si existe
    $dbFile = __DIR__ . '/database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
    }
    
    // Cargar funciones de autenticación si existen
    $authFile = INCLUDES_PATH . '/auth.php';
    if (file_exists($authFile)) {
        require_once $authFile;
    }
}

// Cargar dependencias críticas automáticamente
loadCriticalDependencies();
?>