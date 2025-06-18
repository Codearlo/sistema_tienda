<?php
/**
 * Verificador de Autenticación Simple
 * Archivo: includes/auth.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Debes iniciar sesión para acceder a esta página.';
    header('Location: login.php');
    exit();
}

// Verificar tiempo de inactividad (30 minutos)
if (isset($_SESSION['logged_in_at'])) {
    $max_inactive_time = 30 * 60; // 30 minutos
    if (time() - $_SESSION['logged_in_at'] > $max_inactive_time) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error_message'] = 'Tu sesión ha expirado por inactividad.';
        header('Location: login.php');
        exit();
    }
}

// Actualizar timestamp de última actividad
$_SESSION['logged_in_at'] = time();

// Incluir configuración básica
require_once __DIR__ . '/../backend/config/database.php';

// Definir constantes básicas si no están definidas
if (!defined('STATUS_ACTIVE')) {
    define('STATUS_ACTIVE', 1);
}

if (!defined('STATUS_INACTIVE')) {
    define('STATUS_INACTIVE', 0);
}

if (!defined('STATUS_DELETED')) {
    define('STATUS_DELETED', -1);
}


/**
 * Verificar si el usuario está autenticado
 * Esta función verifica que todas las variables de sesión necesarias estén presentes
 * @return bool true si está autenticado, false si no
 */
function isAuthenticated() {
    // Verificar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar que existan las variables de sesión críticas
    $hasUserId = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    $hasBusinessId = isset($_SESSION['business_id']) && !empty($_SESSION['business_id']);
    
    // Opcional: verificar tiempo de sesión para mayor seguridad
    $sessionValid = true;
    if (isset($_SESSION['logged_in_at'])) {
        $maxInactiveTime = 30 * 60; // 30 minutos
        $sessionValid = (time() - $_SESSION['logged_in_at']) <= $maxInactiveTime;
    }
    
    // Retornar true solo si todas las verificaciones pasan
    return $hasUserId && $hasBusinessId && $sessionValid;
}

/**
 * Función auxiliar para APIs que necesiten verificación de autenticación
 * Envía respuesta JSON y termina ejecución si no está autenticado
 */
function requireAuthenticationForAPI() {
    if (!isAuthenticated()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Sesión no válida. Por favor, inicia sesión nuevamente.'
        ]);
        exit();
    }
}
?>