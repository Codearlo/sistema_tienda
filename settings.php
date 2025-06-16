<?php
session_start();

require_once 'includes/onboarding_middleware.php';
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = null;
$success_message = null;
$config = [];
$user = null;

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];

try {
    $db = getDB();
    
    $settings = $db->fetchAll(
        "SELECT * FROM business_settings WHERE business_id = ?",
        [$business_id]
    );
    
    $config = [];
    foreach ($settings as $setting) {
        $config[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $user = $db->single(
        "SELECT * FROM users WHERE id = ?",
        [$user_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

$config = array_merge([
    'email_sales_report' => 0,
    'email_low_stock' => 0,
    'whatsapp_receipts' => 0,
    'whatsapp_reminders' => 0,
    'low_stock_threshold' => 10
], $config);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Treinta</title>
    <?php 
    forceCssReload();
    includeCss('assets/css/style.css');
    includeCss('assets/css/layouts/settings.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Configuración</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="saveAllSettings()">
                    <i class="fas fa-save"></i>
                    Guardar Cambios
                </button>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- Profile Settings -->
            <div class="settings-section">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> Perfil del Usuario</h2>
                    <p>Gestiona tu información personal y de acceso</p>
                </div>
                
                <form id="profileForm" class="settings-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName" class="required">Nombre:</label>
                            <input type="text" id="firstName" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName" class="required">Apellido:</label>
                            <input type="text" id="lastName" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email:</label>
                            <input type="email" id="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Teléfono:</label>
                            <input type="tel" id="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Perfil
                        </button>
                    </div>
                </form>
            </div>

            <!-- Business Settings -->
            <div class="settings-section">
                <div class="section-header">
                    <h2><i class="fas fa-store"></i> Configuración del Negocio</h2>
                    <p>Administra la configuración general de tu negocio</p>
                </div>
                
                <form id="businessForm" class="settings-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="businessName" class="required">Nombre del negocio:</label>
                            <input type="text" id="businessName" class="form-input" 
                                   value="<?php echo htmlspecialchars($_SESSION['business_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="businessPhone">Teléfono del negocio:</label>
                            <input type="tel" id="businessPhone" class="form-