<?php
session_start();

// Incluir middleware de onboarding
require_once 'includes/onboarding_middleware.php';

// Verificar que el usuario haya completado el onboarding
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = null;
$success_message = null;

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $user_id = $_SESSION['user_id'];
    
    // Procesar formulario de configuración del negocio
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business'])) {
        $business_name = trim($_POST['business_name']);
        $business_address = trim($_POST['business_address']);
        $business_phone = trim($_POST['business_phone']);
        $business_email = trim($_POST['business_email']);
        $tax_id = trim($_POST['tax_id']);
        $currency = $_POST['currency'];
        $timezone = $_POST['timezone'];
        
        if (empty($business_name)) {
            $error_message = "El nombre del negocio es requerido";
        } else {
            $result = $db->update(
                'businesses',
                [
                    'name' => $business_name,
                    'address' => $business_address,
                    'phone' => $business_phone,
                    'email' => $business_email,
                    'tax_id' => $tax_id,
                    'currency' => $currency,
                    'timezone' => $timezone,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$business_id]
            );
            
            if ($result) {
                $success_message = "Configuración del negocio actualizada correctamente";
            } else {
                $error_message = "Error al actualizar la configuración";
            }
        }
    }
    
    // Procesar configuración de notificaciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
        $notifications = [
            'email_sales_report' => isset($_POST['email_sales_report']) ? 1 : 0,
            'email_low_stock' => isset($_POST['email_low_stock']) ? 1 : 0,
            'whatsapp_receipts' => isset($_POST['whatsapp_receipts']) ? 1 : 0,
            'whatsapp_reminders' => isset($_POST['whatsapp_reminders']) ? 1 : 0,
            'low_stock_threshold' => intval($_POST['low_stock_threshold'])
        ];
        
        foreach ($notifications as $key => $value) {
            $existing = $db->single(
                "SELECT id FROM settings WHERE business_id = ? AND setting_key = ?",
                [$business_id, $key]
            );
            
            if ($existing) {
                $db->update(
                    'settings',
                    [
                        'setting_value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$existing['id']]
                );
            } else {
                $db->insert('settings', [
                    'business_id' => $business_id,
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => is_numeric($value) ? 'int' : 'boolean',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        $success_message = "Configuración de notificaciones actualizada correctamente";
    }
    
    // Procesar cambio de contraseña
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Todos los campos de contraseña son requeridos";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Las contraseñas nuevas no coinciden";
        } elseif (strlen($new_password) < 8) {
            $error_message = "La contraseña debe tener al menos 8 caracteres";
        } else {
            $user = $db->single("SELECT password FROM users WHERE id = ?", [$user_id]);
            
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $result = $db->update(
                    'users',
                    [
                        'password' => $hashed_password,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$user_id]
                );
                
                if ($result) {
                    $success_message = "Contraseña actualizada correctamente";
                } else {
                    $error_message = "Error al actualizar la contraseña";
                }
            } else {
                $error_message = "La contraseña actual es incorrecta";
            }
        }
    }
    
    // Cargar información del negocio
    $business = $db->single(
        "SELECT * FROM businesses WHERE id = ?",
        [$business_id]
    );
    
    // Cargar configuraciones
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value, setting_type FROM settings WHERE business_id = ?",
        [$business_id]
    );
    
    // Convertir configuraciones a array asociativo
    $config = [];
    foreach ($settings as $setting) {
        $config[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Cargar información del usuario
    $user = $db->single(
        "SELECT * FROM users WHERE id = ?",
        [$user_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Valores por defecto
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
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-cash-register"></i>
                    <span>Treinta</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="pos.php" class="nav-item">
                    <i class="fas fa-cash-register"></i>
                    <span>Punto de Venta</span>
                </a>
                <a href="products.php" class="nav-item">
                    <i class="fas fa-box"></i>
                    <span>Productos</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="sales.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Ventas</span>
                </a>
                <a href="expenses.php" class="nav-item">
                    <i class="fas fa-receipt"></i>
                    <span>Gastos</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
                <a href="settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-cog"></i> Configuración</h1>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Configuración del Negocio -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-store"></i> Información del Negocio</h2>
                        <p>Configuración básica de tu negocio</p>
                    </div>

                    <form method="POST" class="settings-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="business_name">Nombre del Negocio *</label>
                                <input type="text" id="business_name" name="business_name" 
                                       value="<?php echo htmlspecialchars($business['name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="tax_id">RUC/DNI</label>
                                <input type="text" id="tax_id" name="tax_id" 
                                       value="<?php echo htmlspecialchars($business['tax_id'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="business_phone">Teléfono</label>
                                <input type="tel" id="business_phone" name="business_phone" 
                                       value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="business_email">Email</label>
                                <input type="email" id="business_email" name="business_email" 
                                       value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>">
                            </div>

                            <div class="form-group full-width">
                                <label for="business_address">Dirección</label>
                                <textarea id="business_address" name="business_address" 
                                          rows="3"><?php echo htmlspecialchars($business['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="currency">Moneda</label>
                                <select id="currency" name="currency">
                                    <option value="PEN" <?php echo ($business['currency'] ?? 'PEN') === 'PEN' ? 'selected' : ''; ?>>Sol Peruano (S/)</option>
                                    <option value="USD" <?php echo ($business['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>Dólar Americano ($)</option>
                                    <option value="EUR" <?php echo ($business['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Zona Horaria</label>
                                <select id="timezone" name="timezone">
                                    <option value="America/Lima" <?php echo ($business['timezone'] ?? 'America/Lima') === 'America/Lima' ? 'selected' : ''; ?>>Lima (UTC-5)</option>
                                    <option value="America/Bogota" <?php echo ($business['timezone'] ?? '') === 'America/Bogota' ? 'selected' : ''; ?>>Bogotá (UTC-5)</option>
                                    <option value="America/Mexico_City" <?php echo ($business['timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_business" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Configuración de Notificaciones -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bell"></i> Notificaciones</h2>
                        <p>Configura cómo y cuándo recibir notificaciones</p>
                    </div>

                    <form method="POST" class="settings-form">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <h3>Notificaciones por Email</h3>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="email_sales_report" value="1" 
                                               <?php echo $config['email_sales_report'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Reporte diario de ventas
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="email_low_stock" value="1" 
                                               <?php echo $config['email_low_stock'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Alertas de stock bajo
                                    </label>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <h3>Notificaciones WhatsApp</h3>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="whatsapp_receipts" value="1" 
                                               <?php echo $config['whatsapp_receipts'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Enviar recibos por WhatsApp
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="whatsapp_reminders" value="1" 
                                               <?php echo $config['whatsapp_reminders'] ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Recordatorios de pago
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="low_stock_threshold">Umbral de Stock Bajo</label>
                                <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                                       value="<?php echo $config['low_stock_threshold']; ?>" min="1" max="100">
                                <small>Cantidad mínima antes de considerar stock bajo</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_notifications" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Seguridad -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i> Seguridad</h2>
                        <p>Cambiar contraseña y configuración de seguridad</p>
                    </div>

                    <form method="POST" class="settings-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="current_password">Contraseña Actual *</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">Nueva Contraseña *</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8">
                                <small>Mínimo 8 caracteres</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Información del Sistema -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-info-circle"></i> Información del Sistema</h2>
                        <p>Detalles técnicos y de la cuenta</p>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <label>Usuario:</label>
                            <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Tipo de Usuario:</label>
                            <span class="badge badge-<?php echo $user['user_type']; ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Cuenta Creada:</label>
                            <span><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Última Conexión:</label>
                            <span><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Primera vez'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Versión del Sistema:</label>
                            <span>Treinta v1.0.0</span>
                        </div>
                    </div>
                </div>

                <!-- Acciones de Sistema -->
                <div class="settings-section">
                    <div class="section-header">
                        <h2><i class="fas fa-tools"></i> Herramientas del Sistema</h2>
                        <p>Herramientas avanzadas y mantenimiento</p>
                    </div>

                    <div class="system-actions">
                        <button class="btn btn-secondary" onclick="exportData()">
                            <i class="fas fa-download"></i> Exportar Datos
                        </button>
                        <button class="btn btn-info" onclick="generateReport()">
                            <i class="fas fa-file-pdf"></i> Generar Reporte Completo
                        </button>
                        <button class="btn btn-warning" onclick="clearCache()">
                            <i class="fas fa-broom"></i> Limpiar Caché
                        </button>
                        <button class="btn btn-danger" onclick="confirmDataReset()">
                            <i class="fas fa-exclamation-triangle"></i> Resetear Datos de Prueba
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Validación de formulario de contraseña
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });

        // Funciones del sistema
        function exportData() {
            if (confirm('¿Desea exportar todos los datos del negocio?')) {
                window.location.href = 'api/export/data.php';
            }
        }

        function generateReport() {
            if (confirm('¿Desea generar un reporte completo del negocio?')) {
                window.open('api/reports/complete.php', '_blank');
            }
        }

        function clearCache() {
            if (confirm('¿Desea limpiar la caché del sistema?')) {
                fetch('api/system/clear-cache.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Caché limpiada exitosamente');
                            location.reload();
                        } else {
                            alert('Error al limpiar la caché');
                        }
                    });
            }
        }

        function confirmDataReset() {
            const confirmed = confirm('⚠️ ADVERTENCIA: Esta acción eliminará TODOS los datos de prueba.\n\n¿Está absolutamente seguro de continuar?');
            if (confirmed) {
                const doubleConfirm = confirm('⚠️ ÚLTIMA CONFIRMACIÓN: Se perderán todos los productos, ventas y clientes de prueba.\n\n¿Proceder con el reseteo?');
                if (doubleConfirm) {
                    resetTestData();
                }
            }
        }

        function resetTestData() {
            fetch('api/system/reset-test-data.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Datos de prueba eliminados exitosamente');
                        location.reload();
                    } else {
                        alert('Error al resetear los datos: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error de conexión');
                });
        }

        // Auto-save para configuraciones
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Opcional: auto-guardar configuraciones
                console.log('Configuración cambiada:', this.name, this.checked);
            });
        });
    </script>

    <?php includeJs('assets/js/common.js'); ?>
</body>
</html>