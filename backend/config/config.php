<?php
/**
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA
 * Archivo: backend/config/config.php
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ===== CONFIGURACIÓN DE ERRORES =====
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // development | production
}

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ===== RUTAS DEL SISTEMA =====
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', __DIR__);
}

if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}

if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', ROOT_PATH . '/assets');
}

if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', ROOT_PATH . '/uploads');
}

// ===== CONFIGURACIÓN DE BASE DE DATOS =====
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u347334547_inv_db');
    define('DB_USER', 'u347334547_inv_user');
    define('DB_PASS', 'CH7322a#');
    define('DB_CHARSET', 'utf8mb4');
}

// ===== CONFIGURACIÓN DE LA APLICACIÓN =====
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistema de Inventario');
    define('APP_VERSION', '2.0.0');
    define('APP_URL', 'https://inventario.misitio.com');
    define('APP_TIMEZONE', 'America/Lima');
}

// ===== CONFIGURACIÓN DE SEGURIDAD =====
if (!defined('SECURITY_KEY')) {
    define('SECURITY_KEY', 'tu_clave_secreta_aqui_cambiar_en_produccion');
    define('PASSWORD_MIN_LENGTH', 6);
    define('SESSION_TIMEOUT', 3600); // 1 hora en segundos
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('LOCKOUT_TIME', 900); // 15 minutos
}

// ===== CONFIGURACIÓN DE ARCHIVOS =====
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
}

// ===== CONFIGURACIÓN REGIONAL =====
if (!defined('DEFAULT_LANGUAGE')) {
    define('DEFAULT_LANGUAGE', 'es');
    define('DEFAULT_CURRENCY', 'PEN');
    define('CURRENCY_SYMBOL', 'S/');
    define('DECIMAL_PLACES', 2);
    define('DATE_FORMAT', 'd/m/Y');
    define('DATETIME_FORMAT', 'd/m/Y H:i');
}

// ===== CONFIGURACIÓN DE PAGINACIÓN =====
if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 20);
    define('MAX_PAGE_SIZE', 100);
}

// ===== TIPOS DE USUARIO =====
if (!defined('USER_TYPE_ADMIN')) {
    define('USER_TYPE_ADMIN', 'admin');
    define('USER_TYPE_MANAGER', 'manager');
    define('USER_TYPE_CASHIER', 'cashier');
}

// ===== TIPOS DE MOVIMIENTO DE INVENTARIO =====
if (!defined('INVENTORY_IN')) {
    define('INVENTORY_IN', 'in');
    define('INVENTORY_OUT', 'out');
    define('INVENTORY_ADJUSTMENT', 'adjustment');
}

// ===== MÉTODOS DE PAGO =====
if (!defined('PAYMENT_CASH')) {
    define('PAYMENT_CASH', 'cash');
    define('PAYMENT_CARD', 'card');
    define('PAYMENT_TRANSFER', 'transfer');
    define('PAYMENT_CREDIT', 'credit');
}

// ===== CONFIGURACIÓN DE WHATSAPP =====
if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', 'https://api.whatsapp.com/send');
    define('WHATSAPP_BUSINESS_NUMBER', '+51999999999');
}

// ===== CONFIGURACIÓN DE ZONA HORARIA =====
if (!date_default_timezone_get()) {
    date_default_timezone_set(APP_TIMEZONE);
}

// ===== FUNCIONES UTILITARIAS =====

/**
 * Cargar archivos de configuración
 */
function loadConfig($configFile) {
    $path = CONFIG_PATH . '/' . $configFile . '.php';
    return file_exists($path) ? require $path : false;
}

/**
 * Obtener configuración específica
 */
function getConfig($key, $default = null) {
    static $config = [];
    if (empty($config)) {
        $config = loadConfig('settings') ?: [];
    }
    return $config[$key] ?? $default;
}

/**
 * Incluir archivos de forma segura
 */
function safeInclude($file) {
    $path = INCLUDES_PATH . '/' . $file . '.php';
    return file_exists($path) ? require_once $path : false;
}

/**
 * Redirección segura
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    }
    echo '<script>window.location.href = "' . $url . '";</script>';
    exit();
}

/**
 * Limpiar entrada de datos
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formatear moneda
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, DECIMAL_PLACES, '.', ',');
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = DATE_FORMAT) {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format($format);
}

/**
 * Log de errores personalizado
 */
function logError($message, $file = null, $line = null) {
    $logMessage = date('Y-m-d H:i:s') . " - ERROR: " . $message;
    if ($file) $logMessage .= " in {$file}";
    if ($line) $logMessage .= " on line {$line}";
    
    error_log($logMessage);
}

/**
 * Generar hash seguro de contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generar UUID v4
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Sanitizar nombre de archivo
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '', $filename);
    return substr($filename, 0, 255);
}

/**
 * Validar tipo de archivo
 */
function isValidFileType($filename, $allowedTypes) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedTypes);
}

/**
 * Obtener tamaño de archivo legible
 */
function humanFileSize($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

/**
 * Verificar si es petición AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Respuesta JSON
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Respuesta de error JSON
 */
function jsonError($message, $status = 400, $errors = []) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $status);
}

/**
 * Respuesta de éxito JSON
 */
function jsonSuccess($data = [], $message = 'OK') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Validar sesión activa
 */
function validateSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Verificar timeout de sesión
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Verificar permisos de usuario
 */
function hasPermission($requiredRole) {
    if (!validateSession()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    $roleHierarchy = [
        USER_TYPE_ADMIN => 3,
        USER_TYPE_MANAGER => 2,
        USER_TYPE_CASHIER => 1
    ];
    
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Middleware de autenticación
 */
function requireAuth($redirectTo = 'login.php') {
    if (!validateSession()) {
        if (isAjaxRequest()) {
            jsonError('No autorizado', 401);
        } else {
            redirect($redirectTo);
        }
    }
}

/**
 * Middleware de permisos
 */
function requireRole($role, $redirectTo = 'dashboard.php') {
    requireAuth();
    
    if (!hasPermission($role)) {
        if (isAjaxRequest()) {
            jsonError('No tienes permisos para esta acción', 403);
        } else {
            redirect($redirectTo);
        }
    }
}

// ===== LOG DE CONFIGURACIÓN =====
if (ENVIRONMENT === 'development') {
    error_log("Sistema inicializado - " . date('Y-m-d H:i:s'));
}
?>