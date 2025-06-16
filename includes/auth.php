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
?>