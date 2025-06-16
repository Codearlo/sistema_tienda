<?php
session_start();
require_once 'backend/config/config.php';

// Verificar que el usuario esté logueado pero no haya completado el onboarding
if (!isset($_SESSION['user_id']) || isset($_SESSION['business_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Obtener datos del usuario
try {
    $db = getDB();
    $user = $db->single(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
    
    if (!$user) {
        header('Location: login.php');
        exit();
    }
    
} catch (Exception $e) {
    $error = "Error al cargar datos del usuario: " . $e->getMessage();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $business_name = cleanInput($_POST['business_name'] ?? '');
        $business_type = cleanInput($_POST['business_type'] ?? '');
        $ruc = cleanInput($_POST['ruc'] ?? '');
        $address = cleanInput($_POST['address'] ?? '');
        $phone = cleanInput($_POST['phone'] ?? '');
        $contact_person = cleanInput($_POST['contact_person'] ?? '');
        
        // Validaciones
        if (empty($business_name)) {
            throw new Exception('El nombre del negocio es requerido');
        }
        
        if (empty($business_type)) {
            throw new Exception('El tipo de negocio es requerido');
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Crear el negocio
        $business_id = $db->insert("businesses", [
            'owner_id' => $user['id'],
            'business_name' => $business_name,
            'business_type' => $business_type,
            'ruc' => $ruc,
            'address' => $address,
            'phone' => $phone,
            'email' => $user['email'],
            'status' => STATUS_ACTIVE,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Actualizar el usuario con el business_id
        $db->update("users", ['business_id' => $business_id], ['id' => $user['id']]);
        
        // Insertar configuraciones iniciales
        $default_settings = [
            ['business_timezone', 'America/Lima', 'string', 'Zona horaria del negocio'],
            ['default_tax_rate', '18', 'number', 'Tasa de impuesto por defecto'],
            ['currency_symbol', 'S/', 'string', 'Símbolo de moneda'],
            ['low_stock_alert', '1', 'boolean', 'Alertas de stock bajo activadas'],
            ['auto_backup', '1', 'boolean', 'Backup automático activado']
        ];
        
        foreach ($default_settings as $setting) {
            $db->insert("settings", [
                'business_id' => $business_id,
                'setting_key' => $setting[0],
                'setting_value' => $setting[1],
                'setting_type' => $setting[2],
                'description' => $setting[3],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Crear categorías básicas
        $default_categories = [
            ['Alimentación', 'Productos de alimentación y bebidas', '#10B981'],
            ['Electrónicos', 'Dispositivos y accesorios electrónicos', '#3B82F6'],
            ['Ropa', 'Prendas de vestir y accesorios', '#8B5CF6'],
            ['Hogar', 'Artículos para el hogar', '#F59E0B'],
            ['Salud', 'Productos de salud e higiene', '#EF4444']
        ];
        
        foreach ($default_categories as $category) {
            $db->insert("categories", [
                'business_id' => $business_id,
                'name' => $category[0],
                'description' => $category[1],
                'color' => $category[2],
                'status' => STATUS_ACTIVE,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $db->commit();
        
        // Actualizar sesión
        $_SESSION['business_id'] = $business_id;
        $_SESSION['business_name'] = $business_name;
        
        // Redirigir al dashboard
        redirectWithMessage('dashboard.php', '¡Bienvenido! Tu negocio ha sido configurado exitosamente.', 'success');
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

$flash = showFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar mi Negocio - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="onboarding-container">
        <div class="onboarding-card">
            <!-- Progress bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 70%;"></div>
                </div>
                <p class="progress-text">Paso 2 de 3</p>
            </div>
            
            <!-- Header -->
            <div class="onboarding-header">
                <div class="welcome-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h1 class="onboarding-title">¡Bienvenido a Treinta!</h1>
                <p class="onboarding-subtitle">Personalicemos tu negocio para comenzar</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($flash['type'] === 'error'): ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        <?php else: ?>
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22,4 12,14.01 9,11.01"></polyline>
                        <?php endif; ?>
                    </svg>
                    <?= $flash['message'] ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="onboarding-form" id="onboardingForm">
                <!-- Información Personal -->
                <div class="form-section">
                    <div class="section-header">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <h3 class="section-title">Información Personal</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="owner_name" class="form-label">Nombre del Cliente *</label>
                        <div class="input-group">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input type="text" id="owner_name" class="form-input" 
                                   value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" 
                                   readonly>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <input type="email" id="email" class="form-input" 
                                   value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Información del Negocio -->
                <div class="form-section">
                    <div class="section-header">
                        <svg class="section-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"></path>
                        </svg>
                        <h3 class="section-title">Información del Negocio</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_name" class="form-label">Nombre del Negocio *</label>
                        <div class="input-group">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"></path>
                            </svg>
                            <input type="text" id="business_name" name="business_name" class="form-input" 
                                   placeholder="Ej: Mi Tienda" value="<?= $_POST['business_name'] ?? '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_type" class="form-label">Tipo de Negocio *</label>
                        <div class="business-types">
                            <?php 
                            $business_types = [
                                'retail' => ['Retail', 'Tienda física o en línea'],
                                'restaurant' => ['Restaurante', 'Comida y bebidas'],
                                'services' => ['Servicios', 'Consultoría, reparaciones, etc.'],
                                'manufacturing' => ['Manufactura', 'Producción de bienes'],
                                'wholesale' => ['Mayorista', 'Venta al por mayor'],
                                'other' => ['Otro', 'Otro tipo de negocio']
                            ];
                            
                            foreach ($business_types as $key => $type):
                            ?>
                                <label class="business-type-option">
                                    <input type="radio" name="business_type" value="<?= $key ?>" 
                                           <?= ($_POST['business_type'] ?? '') === $key ? 'checked' : '' ?> required>
                                    <div class="business-type-card">
                                        <h4><?= $type[0] ?></h4>
                                        <p><?= $type[1] ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ruc" class="form-label">RUC (opcional)</label>
                            <div class="input-group">
                                <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                </svg>
                                <input type="text" id="ruc" name="ruc" class="form-input" 
                                       placeholder="20123456789" value="<?= $_POST['ruc'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Teléfono del Negocio</label>
                            <div class="input-group">
                                <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       placeholder="+51 999 999 999" value="<?= $_POST['phone'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="form-label">Dirección (opcional)</label>
                        <div class="input-group">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <textarea id="address" name="address" class="form-input" rows="3" 
                                      placeholder="Dirección completa del negocio"><?= $_POST['address'] ?? '' ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person" class="form-label">Contacto del Negocio (opcional)</label>
                        <div class="input-group">
                            <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input type="text" id="contact_person" name="contact_person" class="form-input" 
                                   placeholder="Persona de contacto" value="<?= $_POST['contact_person'] ?? '' ?>">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-large">
                    <span>Configurar mi Negocio</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"></path>
                        <path d="M12 5l7 7-7 7"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
    
    <style>
        .onboarding-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-lg);
        }
        
        .onboarding-card {
            background: white;
            border-radius: var(--border-radius-2xl);
            padding: var(--spacing-2xl);
            max-width: 700px;
            width: 100%;
            box-shadow: var(--shadow-xl);
            animation: fadeIn 0.6s ease-out;
        }
        
        .progress-container {
            margin-bottom: var(--spacing-2xl);
            text-align: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: var(--gray-200);
            border-radius: var(--border-radius-full);
            overflow: hidden;
            margin-bottom: var(--spacing-sm);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
            border-radius: var(--border-radius-full);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: var(--text-sm);
            color: var(--gray-600);
            margin: 0;
        }
        
        .onboarding-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }
        
        .welcome-icon {
            display: inline-flex;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            border-radius: var(--border-radius-2xl);
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-lg);
        }
        
        .onboarding-title {
            font-size: var(--text-3xl);
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
        }
        
        .onboarding-subtitle {
            font-size: var(--text-lg);
            color: var(--gray-600);
            margin: 0;
        }
        
        .onboarding-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xl);
        }
        
        .form-section {
            background: var(--gray-50);
            border-radius: var(--border-radius-xl);
            padding: var(--spacing-xl);
            border: 1px solid var(--gray-200);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--primary-100);
        }
        
        .section-icon {
            color: var(--primary-600);
            flex-shrink: 0;
        }
        
        .section-title {
            font-size: var(--text-xl);
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
        }
        
        .business-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        
        .business-type-option {
            cursor: pointer;
        }
        
        .business-type-option input[type="radio"] {
            display: none;
        }
        
        .business-type-card {
            padding: var(--spacing-lg);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-lg);
            background: white;
            transition: var(--transition-fast);
            text-align: center;
        }
        
        .business-type-card h4 {
            font-size: var(--text-base);
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 var(--spacing-xs) 0;
        }
        
        .business-type-card p {
            font-size: var(--text-sm);
            color: var(--gray-600);
            margin: 0;
        }
        
        .business-type-option input[type="radio"]:checked + .business-type-card {
            border-color: var(--primary-500);
            background: var(--primary-50);
        }
        
        .business-type-option input[type="radio"]:checked + .business-type-card h4 {
            color: var(--primary-700);
        }
        
        .business-type-card:hover {
            border-color: var(--primary-300);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-large {
            padding: var(--spacing-lg) var(--spacing-xl);
            font-size: var(--text-lg);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-md);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .onboarding-container {
                padding: var(--spacing-md);
            }
            
            .onboarding-card {
                padding: var(--spacing-xl);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .business-types {
                grid-template-columns: 1fr;
            }
            
            .onboarding-title {
                font-size: var(--text-2xl);
            }
            
            .welcome-icon {
                width: 60px;
                height: 60px;
            }
        }
        
        @media (max-width: 480px) {
            .onboarding-card {
                padding: var(--spacing-lg);
            }
            
            .form-section {
                padding: var(--spacing-lg);
            }
        }
    </style>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Validación del formulario
        document.getElementById('onboardingForm').addEventListener('submit', function(e) {
            const businessName = document.getElementById('business_name').value.trim();
            const businessType = document.querySelector('input[name="business_type"]:checked');
            
            if (!businessName) {
                e.preventDefault();
                alert('Por favor ingresa el nombre de tu negocio');
                document.getElementById('business_name').focus();
                return;
            }
            
            if (!businessType) {
                e.preventDefault();
                alert('Por favor selecciona el tipo de negocio');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span>Configurando negocio...</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 11-6.219-8.56"/>
                </svg>
            `;
            
            // Reset after timeout
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }, 5000);
        });
        
        // Auto-focus on business name
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('business_name').focus();
        });
        
        // Animate progress bar on load
        setTimeout(() => {
            document.querySelector('.progress-fill').style.width = '70%';
        }, 500);
    </script>
</body>
</html>