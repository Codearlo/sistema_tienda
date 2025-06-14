<?php
/**
 * Verificador de Autenticación
 * Archivo: includes/auth.php
 * Descripción: Inicia la sesión y verifica si el usuario está autenticado.
 * Si no lo está, lo redirige a la página de login.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Guardar un mensaje de error para mostrar en la página de login
    $_SESSION['error_message'] = 'Debes iniciar sesión para acceder a esta página.';
    
    // Redirigir al login
    header('Location: login.html');
    exit();
}

// Refrescar el tiempo de la sesión para mantenerla activa
if (isset($_SESSION['logged_in_at'])) {
    // Opcional: Definir un tiempo de inactividad máximo (e.g., 30 minutos)
    $max_inactive_time = 30 * 60; 
    if (time() - $_SESSION['logged_in_at'] > $max_inactive_time) {
        // Destruir sesión por inactividad
        session_unset();
        session_destroy();
        
        $_SESSION['error_message'] = 'Tu sesión ha expirado por inactividad.';
        header('Location: login.php');
        exit();
    }
}

// Actualizar el timestamp de la última actividad
$_SESSION['logged_in_at'] = time();

?>