<?php
/**
 * Verificador de Autenticación
 * Archivo: backend/includes/auth.php
 * Descripción: Verifica si el usuario está autenticado y mantiene la sesión activa
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
        // Destruir sesión por inactividad
        session_unset();
        session_destroy();
        
        // Reiniciar sesión para mensaje de error
        session_start();
        $_SESSION['error_message'] = 'Tu sesión ha expirado por inactividad.';
        header('Location: login.php');
        exit();
    }
}

// Actualizar timestamp de última actividad
$_SESSION['logged_in_at'] = time();

// Incluir archivos de configuración necesarios
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que el usuario sigue activo en la BD
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $user = $db->single(
            "SELECT status FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if (!$user || $user['status'] != STATUS_ACTIVE) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['error_message'] = 'Tu cuenta ha sido desactivada.';
            header('Location: login.php');
            exit();
        }
    } catch (Exception $e) {
        // Si hay error de BD, continuar pero registrar
        error_log('Error verificando usuario activo: ' . $e->getMessage());
    }
}
?>