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
$user = [];

$user_id = $_SESSION['user_id'];
$business_id = $_SESSION['business_id'];

try {
    $db = getDB();
    
    // Obtener configuraciones de la tabla settings
    $settings = $db->fetchAll(
        "SELECT * FROM settings WHERE business_id = ?",
        [$business_id]
    );
    
    $config = [];
    foreach ($settings as $setting) {
        $config[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Obtener datos del negocio desde la tabla businesses
    $business_data = $db->single(
        "SELECT * FROM businesses WHERE id = ?",
        [$business_id]
    );
    
    // Obtener datos del usuario
    $user = $db->single(
        "SELECT * FROM users WHERE id = ?",
        [$user_id]
    );
    
    // Si no se pudo obtener el usuario, usar datos de sesión
    if (!$user) {
        $user = [
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'phone' => '',
            'user_type' => $_SESSION['user_type'] ?? 'admin'
        ];
    }
    
    // Asegurar que user_name esté disponible en la sesión
    if (!isset($_SESSION['user_name']) && $user) {
        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    // Usar datos de sesión como fallback
    $user = [
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'user_type' => $_SESSION['user_type'] ?? 'admin'
    ];
    $business_data = [];
}

// Configuraciones por defecto
$config = array_merge([
    'email_sales_report' => 0,
    'email_low_stock' => 0,
    'whatsapp_receipts' => 0,
    'whatsapp_reminders' => 0,
    'low_stock_threshold' => 10
], $config);

// Datos del negocio por defecto
$business_data = $business_data ?? [];
$business_info = array_merge([
    'business_name' => $_SESSION['business_name'] ?? '',
    'phone' => '',
    'email' => '',
    'address' => ''
], $business_data ?? []);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Treinta</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="assets/js/notifications.js"></script>
</head>
<body class="dashboard-page">
    <?php include 'includes/slidebar.php'; ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="btn-icon mobile-menu-btn" onclick="toggleMobileSidebar()">
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

        <?php if (isset($error_message) && $error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message) && $success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- Profile Settings -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> Perfil del Usuario</h2>
                    <p>Gestiona tu información personal y de acceso</p>
                </div>
                
                <div class="card-body">
                    <form id="profileForm" class="form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="firstName" class="form-label required">Nombre</label>
                                <input type="text" id="firstName" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lastName" class="form-label required">Apellido</label>
                                <input type="text" id="lastName" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" id="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="tel" id="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Perfil
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Business Settings -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-store"></i> Configuración del Negocio</h2>
                    <p>Administra la configuración general de tu negocio</p>
                </div>
                
                <div class="card-body">
                    <form id="businessForm" class="form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="businessName" class="form-label required">Nombre del negocio</label>
                                <input type="text" id="businessName" class="form-input" 
                                       value="<?php echo htmlspecialchars($business_info['business_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                               <label for="businessPhone" class="form-label">Teléfono del negocio</label>
                               <input type="tel" id="businessPhone" class="form-input" 
                                      value="<?php echo htmlspecialchars($business_info['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                               <label for="businessEmail" class="form-label">Email del negocio</label>
                               <input type="email" id="businessEmail" class="form-input" 
                                      value="<?php echo htmlspecialchars($business_info['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="businessAddress" class="form-label">Dirección</label>
                            <textarea id="businessAddress" class="form-input" rows="3"><?php echo htmlspecialchars($business_info['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Negocio
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-bell"></i> Notificaciones</h2>
                    <p>Configura cómo y cuándo recibir notificaciones</p>
                </div>
                
                <div class="card-body">
                    <form id="notificationForm" class="form">
                        <div class="notification-group">
                            <div>
                                <h3>Reportes de ventas por email</h3>
                                <p>Recibe reportes automáticos de tus ventas diarias</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="emailSalesReport" <?php echo $config['email_sales_report'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-group">
                            <div>
                                <h3>Alertas de stock bajo</h3>
                                <p>Notificaciones cuando los productos tengan poco stock</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="emailLowStock" <?php echo $config['email_low_stock'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="lowStockThreshold" class="form-label">Umbral de stock bajo</label>
                            <input type="number" id="lowStockThreshold" class="form-input" 
                                   value="<?php echo htmlspecialchars($config['low_stock_threshold'] ?? 10); ?>" min="1">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Notificaciones
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Management -->
            <div class="card danger-zone">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Gestión de Cuenta</h2>
                    <p>Opciones avanzadas para tu cuenta</p>
                </div>
                
                <div class="card-body">
                    <div class="danger-actions">
                        <button class="btn btn-gray" onclick="exportData()">
                            <i class="fas fa-download"></i> Exportar Datos
                        </button>
                        
                        <button class="btn btn-warning" onclick="backupDatabase()">
                            <i class="fas fa-shield-alt"></i> Respaldar Datos
                        </button>
                        
                        <button class="btn btn-error" onclick="confirmDeleteAccount()">
                            <i class="fas fa-trash"></i> Eliminar Cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Account Modal -->
    <div id="deleteAccountModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmar eliminación de cuenta</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Advertencia:</strong> Esta acción es irreversible.
                </div>
                
                <p>Se eliminarán permanentemente:</p>
                <ul>
                    <li>Todos los productos y categorías</li>
                    <li>Historial de ventas y transacciones</li>
                    <li>Datos de clientes</li>
                    <li>Configuraciones del negocio</li>
                    <li>Tu cuenta de usuario</li>
                </ul>
                
                <p>Para confirmar, escribe <strong>"ELIMINAR"</strong>:</p>
                <input type="text" id="confirmDelete" class="form-input" placeholder="ELIMINAR">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeDeleteModal()">Cancelar</button>
                <button id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteAccount()" disabled>
                    Eliminar Cuenta
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar formulario de perfil
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveProfile();
            });
            
            // Manejar formulario de negocio
            document.getElementById('businessForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveBusiness();
            });
            
            // Manejar formulario de notificaciones
            document.getElementById('notificationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveNotifications();
            });
            
            // Handle delete confirmation input
            const confirmInput = document.getElementById('confirmDelete');
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            
            if (confirmInput && deleteBtn) {
                confirmInput.addEventListener('input', function() {
                    deleteBtn.disabled = this.value !== 'ELIMINAR';
                });
            }
        });

        async function saveProfile() {
            const form = document.getElementById('profileForm');
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            button.disabled = true;
            
            try {
                const data = {
                    first_name: document.getElementById('firstName').value,
                    last_name: document.getElementById('lastName').value,
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone').value
                };
                
                const response = await fetch('backend/settings/save_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Perfil guardado exitosamente', 'success');
                } else {
                    showAlert(result.message || 'Error al guardar el perfil', 'error');
                }
            } catch (error) {
                showAlert('Error de conexión al guardar el perfil', 'error');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        async function saveBusiness() {
            const form = document.getElementById('businessForm');
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            button.disabled = true;
            
            try {
                const data = {
                    business_name: document.getElementById('businessName').value,
                    business_phone: document.getElementById('businessPhone').value,
                    business_email: document.getElementById('businessEmail').value,
                    business_address: document.getElementById('businessAddress').value
                };
                
                const response = await fetch('backend/settings/save_business.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Información del negocio guardada exitosamente', 'success');
                } else {
                    showAlert(result.message || 'Error al guardar la información del negocio', 'error');
                }
            } catch (error) {
                showAlert('Error de conexión al guardar la información del negocio', 'error');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        async function saveNotifications() {
            const form = document.getElementById('notificationForm');
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            button.disabled = true;
            
            try {
                const data = {
                    email_sales_report: document.getElementById('emailSalesReport').checked,
                    email_low_stock: document.getElementById('emailLowStock').checked,
                    low_stock_threshold: document.getElementById('lowStockThreshold').value
                };
                
                const response = await fetch('backend/settings/save_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Configuraciones de notificaciones guardadas exitosamente', 'success');
                } else {
                    showAlert(result.message || 'Error al guardar las configuraciones', 'error');
                }
            } catch (error) {
                showAlert('Error de conexión al guardar las configuraciones', 'error');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        function showAlert(message, type) {
            // Crear elemento de alerta
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} fixed-alert`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            document.body.appendChild(alert);
            
            // Remover después de 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }
            }, 5000);
        }

        function saveAllSettings() {
            // Mostrar mensaje de confirmación real
            if (confirm('¿Guardar todos los cambios?')) {
                saveProfile();
                setTimeout(() => saveBusiness(), 500);
                setTimeout(() => saveNotifications(), 1000);
            }
        }

        function confirmDeleteAccount() {
            document.getElementById('deleteAccountModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteAccountModal').style.display = 'none';
            document.getElementById('confirmDelete').value = '';
            document.getElementById('confirmDeleteBtn').disabled = true;
        }

        function exportData() {
            alert('Función de exportar datos en desarrollo');
        }

        function backupDatabase() {
            alert('Función de respaldo en desarrollo');
        }

        function deleteAccount() {
            if (confirm('¿Estás seguro de que quieres eliminar tu cuenta? Esta acción no se puede deshacer.')) {
                alert('Eliminando cuenta...');
                // Aquí iría la lógica real de eliminación
            }
        }
    </script>
</body>
</html>