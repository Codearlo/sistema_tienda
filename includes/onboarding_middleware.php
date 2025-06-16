<?php
/**
 * Middleware de Onboarding
 * Verifica si el usuario ha completado el proceso de configuración inicial
 */

function checkOnboardingStatus() {
    // Si no está logueado, redirigir al login
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Si no tiene business_id en sesión, verificar en base de datos
    if (!isset($_SESSION['business_id']) || !isset($_SESSION['onboarding_completed'])) {
        try {
            require_once 'backend/config/database.php';
            $db = getDB();
            
            $user = $db->single(
                "SELECT business_id FROM users WHERE id = ?",
                [$_SESSION['user_id']]
            );
            
            if ($user && $user['business_id']) {
                // Usuario tiene negocio, actualizar sesión
                $_SESSION['business_id'] = $user['business_id'];
                $_SESSION['onboarding_completed'] = true;
            } else {
                // Usuario no ha completado onboarding
                header('Location: onboarding.php');
                exit();
            }
            
        } catch (Exception $e) {
            // Error al verificar, redirigir al login por seguridad
            session_destroy();
            header('Location: login.php?error=session_error');
            exit();
        }
    }
}

/**
 * Verificar si el usuario puede acceder a una página específica
 */
function requireOnboarding() {
    checkOnboardingStatus();
}

/**
 * Verificar si el usuario está en onboarding y no debería acceder a otras páginas
 */
function preventAccessDuringOnboarding() {
    if (isset($_SESSION['user_id']) && !isset($_SESSION['onboarding_completed'])) {
        header('Location: onboarding.php');
        exit();
    }
}
?>