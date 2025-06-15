<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

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
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['business_name'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Tipo de Negocio</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['business_type'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">RUC</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['ruc'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Dirección</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['address'] ?? 'No definida'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Teléfono</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['phone'] ?? 'No definido'); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Email</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($business['email'] ?? 'No definido'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mi Perfil -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mi Perfil</h3>
                    <button class="btn btn-primary btn-small" onclick="editProfile()">Editar</button>
                </div>
                <div class="card-content">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Nombre Completo</label>
                            <div style="color: var(--gray-900);">
                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Email</label>
                            <div style="color: var(--gray-900);"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Tipo de Usuario</label>
                            <div style="color: var(--gray-900);">
                                <span class="badge badge-primary"><?php echo ucfirst($user['user_type'] ?? 'usuario'); ?></span>
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Último Acceso</label>
                            <div style="color: var(--gray-900);">
                                <?php 
                                if ($user['last_login']) {
                                    echo date('d/m/Y H:i', strtotime($user['last_login']));
                                } else {
                                    echo 'Nunca';
                                }
                                ?>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-warning" onclick="changePassword()">Cambiar Contraseña</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuraciones del Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Configuraciones del Sistema</h3>
                <button class="btn btn-primary btn-small" onclick="saveSettings()">Guardar Cambios</button>
            </div>
            <div class="card-content">
                <form id="settingsForm">
                    <div class="form-grid">
                        <!-- Configuraciones Generales -->
                        <div class="form-section">
                            <h4 class="form-section-title">Configuraciones Generales</h4>
                            
                            <div class="form-group">
                                <label class="form-label">Zona Horaria</label>
                                <select name="business_timezone" class="form-input">
                                    <option value="America/Lima" <?php echo ($config['business_timezone'] ?? '') === 'America/Lima' ? 'selected' : ''; ?>>
                                        Lima (UTC-5)
                                    </option>
                                    <option value="America/Mexico_City" <?php echo ($config['business_timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : ''; ?>>
                                        Ciudad de México (UTC-6)
                                    </option>
                                    <option value="America/Bogota" <?php echo ($config['business_timezone'] ?? '') === 'America/Bogota' ? 'selected' : ''; ?>>
                                        Bogotá (UTC-5)
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Moneda</label>
                                <select name="currency_code" class="form-input">
                                    <option value="PEN" <?php echo ($config['currency_code'] ?? '') === 'PEN' ? 'selected' : ''; ?>>
                                        Sol Peruano (PEN)
                                    </option>
                                    <option value="USD" <?php echo ($config['currency_code'] ?? '') === 'USD' ? 'selected' : ''; ?>>
                                        Dólar Americano (USD)
                                    </option>
                                    <option value="EUR" <?php echo ($config['currency_code'] ?? '') === 'EUR' ? 'selected' : ''; ?>>
                                        Euro (EUR)
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Símbolo de Moneda</label>
                                <input type="text" name="currency_symbol" class="form-input" 
                                       value="<?php echo htmlspecialchars($config['currency_symbol'] ?? 'S/'); ?>" 
                                       placeholder="S/">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tasa de Impuesto por Defecto (%)</label>
                                <input type="number" name="default_tax_rate" class="form-input" step="0.01" min="0" max="100"
                                       value="<?php echo htmlspecialchars($config['default_tax_rate'] ?? '18'); ?>" 
                                       placeholder="18.00">
                            </div>
                        </div>

                        <!-- Configuraciones de Inventario -->
                        <div class="form-section">
                            <h4 class="form-section-title">Inventario</h4>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="low_stock_alert" class="checkbox" 
                                           <?php echo ($config['low_stock_alert'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Alertas de Stock Bajo
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="auto_reduce_stock" class="checkbox" 
                                           <?php echo ($config['auto_reduce_stock'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Reducir Stock Automáticamente en Ventas
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="allow_negative_stock" class="checkbox" 
                                           <?php echo ($config['allow_negative_stock'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Permitir Stock Negativo
                                </label>
                            </div>
                        </div>

                        <!-- Configuraciones de Ventas -->
                        <div class="form-section">
                            <h4 class="form-section-title">Ventas</h4>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="auto_print_receipt" class="checkbox" 
                                           <?php echo ($config['auto_print_receipt'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Imprimir Recibo Automáticamente
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_customer_sale" class="checkbox" 
                                           <?php echo ($config['require_customer_sale'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Requerir Cliente en Todas las Ventas
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Descuento Máximo Permitido (%)</label>
                                <input type="number" name="max_discount_percent" class="form-input" step="0.01" min="0" max="100"
                                       value="<?php echo htmlspecialchars($config['max_discount_percent'] ?? '50'); ?>" 
                                       placeholder="50.00">
                            </div>
                        </div>

                        <!-- Configuraciones de Backup -->
                        <div class="form-section">
                            <h4 class="form-section-title">Backup y Seguridad</h4>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="auto_backup" class="checkbox" 
                                           <?php echo ($config['auto_backup'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Backup Automático Diario
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="enable_logging" class="checkbox" 
                                           <?php echo ($config['enable_logging'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Registrar Actividades del Sistema
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Días de Retención de Logs</label>
                                <input type="number" name="log_retention_days" class="form-input" min="1" max="365"
                                       value="<?php echo htmlspecialchars($config['log_retention_days'] ?? '30'); ?>" 
                                       placeholder="30">
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-warning" onclick="exportData()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7,10 12,15 17,10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        Exportar Datos
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="createBackup()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                            <line x1="9" y1="9" x2="9.01" y2="9"/>
                                            <line x1="15" y1="9" x2="15.01" y2="9"/>
                                        </svg>
                                        Crear Backup Manual
                                    </button>
                                    <button type="button" class="btn btn-error" onclick="clearData()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3,6 5,6 21,6"/>
                                            <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                                        </svg>
                                        Limpiar Datos Antiguos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Información del Sistema</h3>
            </div>
            <div class="card-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div>
                        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Versión</label>
                        <div style="color: var(--gray-900);">Treinta v1.0.0</div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Base de Datos</label>
                        <div style="color: var(--gray-900);">MySQL 8.0</div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Servidor Web</label>
                        <div style="color: var(--gray-900);">Apache/Nginx</div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem;">Última Actualización</label>
                        <div style="color: var(--gray-900);"><?php echo date('d/m/Y'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Cambio de Contraseña -->
    <div class="modal-overlay" id="passwordModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Cambiar Contraseña</h3>
                <button class="modal-close" onclick="closePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Contraseña Actual</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" name="new_password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" name="confirm_password" class="form-input" required minlength="8">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closePasswordModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editBusiness() {
            alert('Funcionalidad de edición de negocio en desarrollo');
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
                alert('Limpiando datos antiguos...\n(Funcionalidad en desarrollo)');
            }
        }

        // Validar formulario de contraseña
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = this.new_password.value;
            const confirmPassword = this.confirm_password.value;
            
            if (newPassword !== confirmPassword) {
                alert('Las contraseñas no coinciden');
                return;
            }
            
            if (newPassword.length < 8) {
                alert('La contraseña debe tener al menos 8 caracteres');
                return;
            }
            
            alert('Contraseña cambiada exitosamente\n(Funcionalidad en desarrollo)');
            closePasswordModal();
        });

        // Cerrar modal al hacer clic fuera
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePasswordModal();
            }
        });
    </script>

</body>
</html>