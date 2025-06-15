<?php
session_start();

// Redirigir a login si no está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirigir al dashboard
header('Location: dashboard.php');
exit();
?>