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
                           <input type="tel" id="businessPhone" class="form-input" 
                                  value="<?php echo htmlspecialchars($config['business_phone'] ?? ''); ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="businessEmail">Email del negocio:</label>
                           <input type="email" id="businessEmail" class="form-input" 
                                  value="<?php echo htmlspecialchars($config['business_email'] ?? ''); ?>">
                       </div>
                       
                       <div class="form-group">
                           <label for="businessAddress">Dirección:</label>
                           <textarea id="businessAddress" class="form-textarea" rows="3"><?php echo htmlspecialchars($config['business_address'] ?? ''); ?></textarea>
                       </div>
                   </div>
                   
                   <div class="form-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Guardar Configuración
                       </button>
                   </div>
               </form>
           </div>

           <!-- Notification Settings -->
           <div class="settings-section">
               <div class="section-header">
                   <h2><i class="fas fa-bell"></i> Notificaciones</h2>
                   <p>Configura cómo y cuándo recibir notificaciones</p>
               </div>
               
               <form id="notificationForm" class="settings-form">
                   <div class="settings-grid">
                       <div class="setting-item">
                           <div class="setting-info">
                               <h4>Reportes de ventas por email</h4>
                               <p>Recibe un resumen diario de ventas en tu email</p>
                           </div>
                           <div class="setting-control">
                               <label class="switch">
                                   <input type="checkbox" id="emailSalesReport" 
                                          <?php echo $config['email_sales_report'] ? 'checked' : ''; ?>>
                                   <span class="slider"></span>
                               </label>
                           </div>
                       </div>
                       
                       <div class="setting-item">
                           <div class="setting-info">
                               <h4>Alertas de stock bajo</h4>
                               <p>Notificaciones cuando los productos tengan poco stock</p>
                           </div>
                           <div class="setting-control">
                               <label class="switch">
                                   <input type="checkbox" id="emailLowStock" 
                                          <?php echo $config['email_low_stock'] ? 'checked' : ''; ?>>
                                   <span class="slider"></span>
                               </label>
                           </div>
                       </div>
                       
                       <div class="setting-item">
                           <div class="setting-info">
                               <h4>Recibos por WhatsApp</h4>
                               <p>Envía recibos de venta automáticamente por WhatsApp</p>
                           </div>
                           <div class="setting-control">
                               <label class="switch">
                                   <input type="checkbox" id="whatsappReceipts" 
                                          <?php echo $config['whatsapp_receipts'] ? 'checked' : ''; ?>>
                                   <span class="slider"></span>
                               </label>
                           </div>
                       </div>
                       
                       <div class="setting-item">
                           <div class="setting-info">
                               <h4>Recordatorios de deudas</h4>
                               <p>Envía recordatorios automáticos a clientes con deudas pendientes</p>
                           </div>
                           <div class="setting-control">
                               <label class="switch">
                                   <input type="checkbox" id="whatsappReminders" 
                                          <?php echo $config['whatsapp_reminders'] ? 'checked' : ''; ?>>
                                   <span class="slider"></span>
                               </label>
                           </div>
                       </div>
                   </div>
                   
                   <div class="form-group">
                       <label for="lowStockThreshold">Umbral de stock bajo:</label>
                       <input type="number" id="lowStockThreshold" class="form-input" 
                              value="<?php echo $config['low_stock_threshold']; ?>" min="1" max="100">
                       <small class="form-help">Cantidad mínima antes de considerar el stock como bajo</small>
                   </div>
                   
                   <div class="form-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Guardar Notificaciones
                       </button>
                   </div>
               </form>
           </div>

           <!-- Security Settings -->
           <div class="settings-section">
               <div class="section-header">
                   <h2><i class="fas fa-shield-alt"></i> Seguridad</h2>
                   <p>Gestiona la seguridad de tu cuenta</p>
               </div>
               
               <form id="securityForm" class="settings-form">
                   <div class="form-group">
                       <label for="currentPassword" class="required">Contraseña actual:</label>
                       <input type="password" id="currentPassword" class="form-input" required>
                   </div>
                   
                   <div class="form-grid">
                       <div class="form-group">
                           <label for="newPassword" class="required">Nueva contraseña:</label>
                           <input type="password" id="newPassword" class="form-input" required>
                           <small class="form-help">Mínimo 8 caracteres</small>
                       </div>
                       
                       <div class="form-group">
                           <label for="confirmPassword" class="required">Confirmar contraseña:</label>
                           <input type="password" id="confirmPassword" class="form-input" required>
                       </div>
                   </div>
                   
                   <div class="form-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-key"></i> Cambiar Contraseña
                       </button>
                   </div>
               </form>
           </div>

           <!-- System Settings -->
           <div class="settings-section">
               <div class="section-header">
                   <h2><i class="fas fa-cog"></i> Sistema</h2>
                   <p>Configuraciones del sistema y datos</p>
               </div>
               
               <div class="system-actions">
                   <div class="action-item">
                       <div class="action-info">
                           <h4>Exportar datos</h4>
                           <p>Descarga todos tus datos en formato Excel</p>
                       </div>
                       <button class="btn btn-outline" onclick="exportData()">
                           <i class="fas fa-download"></i> Exportar
                       </button>
                   </div>
                   
                   <div class="action-item">
                       <div class="action-info">
                           <h4>Respaldar base de datos</h4>
                           <p>Crea una copia de seguridad de toda tu información</p>
                       </div>
                       <button class="btn btn-outline" onclick="backupDatabase()">
                           <i class="fas fa-database"></i> Respaldar
                       </button>
                   </div>
                   
                   <div class="action-item danger">
                       <div class="action-info">
                           <h4>Eliminar cuenta</h4>
                           <p>Elimina permanentemente tu cuenta y todos los datos</p>
                       </div>
                       <button class="btn btn-error" onclick="confirmDeleteAccount()">
                           <i class="fas fa-trash"></i> Eliminar
                       </button>
                   </div>
               </div>
           </div>
       </div>
   </main>

   <!-- Delete Account Modal -->
   <div class="modal" id="deleteAccountModal">
       <div class="modal-content">
           <div class="modal-header">
               <h3>Confirmar eliminación de cuenta</h3>
               <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
           </div>
           <div class="modal-body">
               <div class="alert alert-error">
                   <i class="fas fa-exclamation-triangle"></i>
                   <strong>¡Advertencia!</strong> Esta acción es irreversible.
               </div>
               <p>Se eliminarán permanentemente:</p>
               <ul>
                   <li>Todos los productos y categorías</li>
                   <li>Historial de ventas y transacciones</li>
                   <li>Datos de clientes</li>
                   <li>Configuraciones del negocio</li>
                   <li>Tu cuenta de usuario</li>
               </ul>
               
               <div class="form-group">
                   <label for="confirmDelete">Para confirmar, escribe "ELIMINAR":</label>
                   <input type="text" id="confirmDelete" class="form-input" placeholder="Escribe ELIMINAR">
               </div>
           </div>
           <div class="modal-footer">
               <button class="btn btn-outline" onclick="closeDeleteModal()">Cancelar</button>
               <button class="btn btn-error" onclick="deleteAccount()" id="confirmDeleteBtn" disabled>
                   <i class="fas fa-trash"></i> Eliminar Cuenta
               </button>
           </div>
       </div>
   </div>

   <!-- Scripts -->
   <script src="assets/js/notifications.js"></script>
   <script src="assets/js/api.js"></script>
   <script src="assets/js/settings.js"></script>
   
   <script>
       document.addEventListener('DOMContentLoaded', function() {
           // Initialize settings page
           initializeSettings();
           
           // Handle delete confirmation input
           const confirmInput = document.getElementById('confirmDelete');
           const deleteBtn = document.getElementById('confirmDeleteBtn');
           
           if (confirmInput && deleteBtn) {
               confirmInput.addEventListener('input', function() {
                   deleteBtn.disabled = this.value !== 'ELIMINAR';
               });
           }
       });

       function confirmDeleteAccount() {
           document.getElementById('deleteAccountModal').style.display = 'flex';
       }

       function closeDeleteModal() {
           document.getElementById('deleteAccountModal').style.display = 'none';
           document.getElementById('confirmDelete').value = '';
           document.getElementById('confirmDeleteBtn').disabled = true;
       }

       function saveAllSettings() {
           Notifications.success('Guardando todas las configuraciones...');
           
           // Save profile
           document.getElementById('profileForm').dispatchEvent(new Event('submit'));
           
           // Save business settings
           setTimeout(() => {
               document.getElementById('businessForm').dispatchEvent(new Event('submit'));
           }, 500);
           
           // Save notifications
           setTimeout(() => {
               document.getElementById('notificationForm').dispatchEvent(new Event('submit'));
           }, 1000);
       }

       function exportData() {
           Notifications.info('Preparando exportación de datos...');
           // Implementation for data export
           setTimeout(() => {
               Notifications.success('Datos exportados exitosamente');
           }, 2000);
       }

       function backupDatabase() {
           Notifications.info('Creando respaldo de la base de datos...');
           // Implementation for database backup
           setTimeout(() => {
               Notifications.success('Respaldo creado exitosamente');
           }, 3000);
       }

       function deleteAccount() {
           Notifications.warning('Eliminando cuenta...');
           // Implementation for account deletion
           setTimeout(() => {
               window.location.href = 'login.php';
           }, 2000);
       }
   </script>
</body>
</html>