<?php
session_start();

// Incluir middleware de onboarding
require_once 'includes/onboarding_middleware.php';

// Verificar que el usuario haya completado el onboarding
requireOnboarding();

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $user_id = $_SESSION['user_id'];
    
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
    $business = [];
    $config = [];
    $user = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Treinta</title>
    <?php includeCss('assets/css/style.css'); ?>
</head>
<body class="dashboard-page">

    <?php include 'includes/slidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="page-title">Configuración</h1>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Información del Negocio -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información del Negocio</h3>
                    <button class="btn btn-primary btn-small" onclick="editBusiness()">Editar</button>
                </div>
                <div class="card-content">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Nombre del Negocio</label>
                            <div style="color: var(--gray-900);" data-business-name><?php echo htmlspecialchars($business['business_name'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Tipo de Negocio</label>
                            <div style="color: var(--gray-900);" data-business-type><?php echo htmlspecialchars($business['business_type'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">RUC</label>
                            <div style="color: var(--gray-900);" data-ruc><?php echo htmlspecialchars($business['ruc'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Dirección</label>
                            <div style="color: var(--gray-900);" data-address><?php echo htmlspecialchars($business['address'] ?? 'No definida'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Teléfono</label>
                            <div style="color: var(--gray-900);" data-phone><?php echo htmlspecialchars($business['phone'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Email</label>
                            <div style="color: var(--gray-900);" data-email><?php echo htmlspecialchars($business['email'] ?? 'No definido'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Usuario -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mi Perfil</h3>
                    <button class="btn btn-primary btn-small" onclick="editProfile()">Editar</button>
                </div>
                <div class="card-content">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Nombre</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Email</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($user['email'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Tipo de Usuario</label>
                            <div style="color: var(--gray-900);"><?php echo ucfirst($user['user_type'] ?? 'usuario'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Último Acceso</label>
                            <div style="color: var(--gray-900);"><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Primera vez'; ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                        <button class="btn btn-gray btn-small" onclick="changePassword()">Cambiar Contraseña</button>
                    </div>
                </div>
            </div>

            <!-- Configuraciones del Sistema -->
            <div class="card" style="grid-column: 1 / -1;">
                <div class="card-header">
                    <h3 class="card-title">Configuraciones del Sistema</h3>
                    <button class="btn btn-primary btn-small" onclick="saveSettings()">Guardar Cambios</button>
                </div>
                <div class="card-content">
                    <form id="settingsForm">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                            <!-- Configuraciones Generales -->
                            <div>
                                <h4 style="margin-bottom: 1rem; color: var(--gray-900); font-weight: 600;">Configuraciones Generales</h4>
                                <div class="form-group">
                                    <label for="currency_symbol">Símbolo de Moneda</label>
                                    <input type="text" id="currency_symbol" name="currency_symbol" class="form-input" 
                                           value="<?php echo htmlspecialchars($config['currency_symbol'] ?? 'S/'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="default_tax_rate">Tasa de Impuesto por Defecto (%)</label>
                                    <input type="number" id="default_tax_rate" name="default_tax_rate" class="form-input" 
                                           step="0.01" min="0" max="100" 
                                           value="<?php echo htmlspecialchars($config['default_tax_rate'] ?? '18'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="business_timezone">Zona Horaria</label>
                                    <select id="business_timezone" name="business_timezone" class="form-input">
                                        <option value="America/Lima" <?php echo ($config['business_timezone'] ?? '') === 'America/Lima' ? 'selected' : ''; ?>>Lima, Perú (UTC-5)</option>
                                        <option value="America/Bogota" <?php echo ($config['business_timezone'] ?? '') === 'America/Bogota' ? 'selected' : ''; ?>>Bogotá, Colombia (UTC-5)</option>
                                        <option value="America/Mexico_City" <?php echo ($config['business_timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                        <option value="America/Argentina/Buenos_Aires" <?php echo ($config['business_timezone'] ?? '') === 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>Buenos Aires, Argentina (UTC-3)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Notificaciones y Alertas -->
                            <div>
                                <h4 style="margin-bottom: 1rem; color: var(--gray-900); font-weight: 600;">Notificaciones y Alertas</h4>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="low_stock_alert" value="1" 
                                               <?php echo ($config['low_stock_alert'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Alertas de Stock Bajo
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="payment_reminders" value="1" 
                                               <?php echo ($config['payment_reminders'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Recordatorios de Pago
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="daily_reports" value="1" 
                                               <?php echo ($config['daily_reports'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Reportes Diarios Automáticos
                                    </label>
                                </div>
                            </div>

                            <!-- Backup y Seguridad -->
                            <div>
                                <h4 style="margin-bottom: 1rem; color: var(--gray-900); font-weight: 600;">Backup y Seguridad</h4>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="auto_backup" value="1" 
                                               <?php echo ($config['auto_backup'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Backup Automático
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label for="backup_frequency">Frecuencia de Backup</label>
                                    <select id="backup_frequency" name="backup_frequency" class="form-input">
                                        <option value="daily" <?php echo ($config['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Diario</option>
                                        <option value="weekly" <?php echo ($config['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                                        <option value="monthly" <?php echo ($config['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Mensual</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-gray" onclick="createBackup()">Crear Backup Manual</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Acciones de Datos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Gestión de Datos</h3>
                </div>
                <div class="card-content">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <button class="btn btn-primary" onclick="exportData()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                            Exportar Datos
                        </button>
                        <button class="btn btn-yellow" onclick="createBackup()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                                <path d="M12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                            </svg>
                            Backup Manual
                        </button>
                        <button class="btn btn-red" onclick="clearData()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                                <path d="M9,3V4H4V6H5V19A2,2 0 0,0 7,21H17A2,2 0 0,0 19,19V6H20V4H15V3H9M7,6H17V19H7V6M9,8V17H11V8H9M13,8V17H15V8H13Z"/>
                            </svg>
                            Limpiar Datos Antiguos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Cambio de Contraseña -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cambiar Contraseña</h3>
                <button type="button" class="modal-close" onclick="closePasswordModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closePasswordModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Edición de Negocio -->
    <div class="modal" id="businessModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Información del Negocio</h3>
                <button type="button" class="modal-close" onclick="closeBusinessModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="businessForm" onsubmit="handleBusinessUpdate(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_business_name">Nombre del Negocio *</label>
                            <input type="text" id="edit_business_name" name="business_name" 
                                   class="form-input" required 
                                   value="<?php echo htmlspecialchars($business['business_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_business_type">Tipo de Negocio *</label>
                            <select id="edit_business_type" name="business_type" class="form-input" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="Retail" <?php echo ($business['business_type'] ?? '') === 'Retail' ? 'selected' : ''; ?>>Retail</option>
                                <option value="Restaurante" <?php echo ($business['business_type'] ?? '') === 'Restaurante' ? 'selected' : ''; ?>>Restaurante</option>
                                <option value="Servicios" <?php echo ($business['business_type'] ?? '') === 'Servicios' ? 'selected' : ''; ?>>Servicios</option>
                                <option value="Mayorista" <?php echo ($business['business_type'] ?? '') === 'Mayorista' ? 'selected' : ''; ?>>Mayorista</option>
                                <option value="Manufactura" <?php echo ($business['business_type'] ?? '') === 'Manufactura' ? 'selected' : ''; ?>>Manufactura</option>
                                <option value="Otro" <?php echo ($business['business_type'] ?? '') === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_ruc">RUC</label>
                            <input type="text" id="edit_ruc" name="ruc" class="form-input" 
                                   maxlength="11" value="<?php echo htmlspecialchars($business['ruc'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Teléfono</label>
                            <input type="tel" id="edit_phone" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email del Negocio</label>
                        <input type="email" id="edit_email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_address">Dirección</label>
                        <textarea id="edit_address" name="address" class="form-input" rows="2"><?php echo htmlspecialchars($business['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeBusinessModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editBusiness() {
            document.getElementById('businessModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeBusinessModal() {
            document.getElementById('businessModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        async function handleBusinessUpdate(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const businessData = {};
            
            // Convertir FormData a objeto
            for (let [key, value] of formData.entries()) {
                businessData[key] = value.trim();
            }
            
            // Validaciones
            if (!businessData.business_name) {
                alert('El nombre del negocio es requerido');
                return;
            }
            
            if (!businessData.business_type) {
                alert('El tipo de negocio es requerido');
                return;
            }
            
            // Mostrar loading
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Guardando...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('backend/business/update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(businessData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Actualizar la información mostrada en la página
                    updateBusinessDisplay(businessData);
                    closeBusinessModal();
                    
                    // Mostrar notificación de éxito
                    showNotification('Información del negocio actualizada exitosamente', 'success');
                } else {
                    throw new Error(result.message || 'Error al actualizar la información');
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al actualizar la información: ' + error.message);
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        }

        function updateBusinessDisplay(businessData) {
            // Actualizar los elementos mostrados en la página
            const businessNameElement = document.querySelector('[data-business-name]');
            const businessTypeElement = document.querySelector('[data-business-type]');
            const addressElement = document.querySelector('[data-address]');
            const phoneElement = document.querySelector('[data-phone]');
            const emailElement = document.querySelector('[data-email]');
            const rucElement = document.querySelector('[data-ruc]');
            
            if (businessNameElement) businessNameElement.textContent = businessData.business_name;
            if (businessTypeElement) businessTypeElement.textContent = businessData.business_type;
            if (addressElement) addressElement.textContent = businessData.address || 'No definida';
            if (phoneElement) phoneElement.textContent = businessData.phone || 'No definido';
            if (emailElement) emailElement.textContent = businessData.email || 'No definido';
            if (rucElement) rucElement.textContent = businessData.ruc || 'No definido';
        }

        function showNotification(message, type = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `<span>${message}</span>`;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        function editProfile() {
            alert('Funcionalidad de edición de perfil en desarrollo');
        }

        function changePassword() {
            document.getElementById('passwordModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('passwordForm').reset();
        }

        function saveSettings() {
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            
            // Convertir a objeto para enviar como JSON
            const settings = {};
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            // Manejar checkboxes (que no aparecen en FormData si están desmarcados)
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                settings[checkbox.name] = checkbox.checked ? '1' : '0';
            });
            
            alert('Configuraciones guardadas:\n' + JSON.stringify(settings, null, 2));
            // Aquí implementarías el envío real a la API
        }

        function exportData() {
            if (confirm('¿Exportar todos los datos del negocio?')) {
                alert('Iniciando exportación de datos...\n(Funcionalidad en desarrollo)');
            }
        }

        function createBackup() {
            if (confirm('¿Crear un backup manual del sistema?')) {
                alert('Creando backup...\n(Funcionalidad en desarrollo)');
            }
        }

        function clearData() {
            if (confirm('ADVERTENCIA: Esta acción eliminará datos antiguos permanentemente.\n¿Estás seguro?')) {
                if (confirm('¿Realmente deseas continuar? Esta acción no se puede deshacer.')) {
                    alert('Limpiando datos antiguos...\n(Funcionalidad en desarrollo)');
                }
            }
        }

        // Validación de RUC en tiempo real
        document.getElementById('edit_ruc').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });

        // Formato de teléfono
        document.getElementById('edit_phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
            } else if (value.length > 3) {
                value = value.replace(/(\d{3})(\d{3})/, '$1 $2');
            }
            e.target.value = value;
        });

        // Agregar estilos para las animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>