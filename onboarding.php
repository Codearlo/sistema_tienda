<?php
session_start();

// Verificar que el usuario esté logueado pero no haya completado el onboarding
if (!isset($_SESSION['user_id']) || isset($_SESSION['onboarding_completed'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

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
    $error_message = "Error al cargar datos del usuario: " . $e->getMessage();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $business_name = trim($_POST['business_name'] ?? '');
        $business_type = trim($_POST['business_type'] ?? '');
        $ruc = trim($_POST['ruc'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        
        // Validaciones
        if (empty($business_name)) {
            throw new Exception('El nombre del negocio es requerido');
        }
        
        if (empty($business_type)) {
            throw new Exception('El tipo de negocio es requerido');
        }
        
        // Iniciar transacción
        $db->query('START TRANSACTION');
        
        // Crear el negocio
        $business_id = $db->insert(
            "INSERT INTO businesses (owner_id, business_name, business_type, ruc, address, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$user['id'], $business_name, $business_type, $ruc, $address, $phone, $user['email']]
        );
        
        // Actualizar el usuario con el business_id
        $db->query(
            "UPDATE users SET business_id = ? WHERE id = ?",
            [$business_id, $user['id']]
        );
        
        // Insertar configuraciones iniciales
        $default_settings = [
            ['business_timezone', 'America/Lima', 'string', 'Zona horaria del negocio'],
            ['default_tax_rate', '18', 'number', 'Tasa de impuesto por defecto'],
            ['currency_symbol', 'S/', 'string', 'Símbolo de moneda'],
            ['low_stock_alert', '1', 'boolean', 'Alertas de stock bajo activadas'],
            ['auto_backup', '1', 'boolean', 'Backup automático activado']
        ];
        
        foreach ($default_settings as $setting) {
            $db->query(
                "INSERT INTO settings (business_id, setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?, ?)",
                array_merge([$business_id], $setting)
            );
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
            $db->query(
                "INSERT INTO categories (business_id, name, description, color) VALUES (?, ?, ?, ?)",
                array_merge([$business_id], $category)
            );
        }
        
        $db->query('COMMIT');
        
        // Actualizar sesión
        $_SESSION['business_id'] = $business_id;
        $_SESSION['onboarding_completed'] = true;
        
        // Redirigir al dashboard
        header('Location: dashboard.php?welcome=1');
        exit();
        
    } catch (Exception $e) {
        $db->query('ROLLBACK');
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalizar mi Negocio - Treinta</title>
    <?php includeCss('assets/css/style.css'); ?>
    <style>
        .onboarding-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .onboarding-card {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .onboarding-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .onboarding-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .onboarding-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-icon {
            width: 20px;
            height: 20px;
            color: #667eea;
        }
        
        .business-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .business-type-option {
            position: relative;
        }
        
        .business-type-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        
        .business-type-label {
            display: block;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            color: #374151;
        }
        
        .business-type-input:checked + .business-type-label {
            border-color: #667eea;
            background-color: #667eea;
            color: white;
        }
        
        .business-type-label:hover {
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .onboarding-card {
                padding: 2rem;
            }
            
            .business-types {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            }
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background-color: #e5e7eb;
            border-radius: 2px;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 100%;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="onboarding-card">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            
            <div class="onboarding-header">
                <h1 class="onboarding-title">¡Bienvenido a Treinta!</h1>
                <p class="onboarding-subtitle">Personalicemos tu negocio para comenzar</p>
            </div>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;">
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="onboardingForm">
                <!-- Información Personal -->
                <div class="form-section">
                    <h3 class="section-title">
                        <svg class="section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Información Personal
                    </h3>
                    
                    <div class="form-group">
                        <label for="owner_name">Nombre del Cliente *</label>
                        <input type="text" id="owner_name" name="owner_name" 
                               value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" 
                               readonly class="form-input" style="background-color: #f9fafb;">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               readonly class="form-input" style="background-color: #f9fafb;">
                    </div>
                </div>
                
                <!-- Información del Negocio -->
                <div class="form-section">
                    <h3 class="section-title">
                        <svg class="section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Información del Negocio
                    </h3>
                    
                    <div class="form-group">
                        <label for="business_name">Nombre del Negocio *</label>
                        <input type="text" id="business_name" name="business_name" 
                               class="form-input" required placeholder="Ej: Mi Tienda">
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Negocio *</label>
                        <div class="business-types">
                            <div class="business-type-option">
                                <input type="radio" id="retail" name="business_type" value="Retail" class="business-type-input" required>
                                <label for="retail" class="business-type-label">Retail</label>
                            </div>
                            <div class="business-type-option">
                                <input type="radio" id="restaurant" name="business_type" value="Restaurante" class="business-type-input">
                                <label for="restaurant" class="business-type-label">Restaurante</label>
                            </div>
                            <div class="business-type-option">
                                <input type="radio" id="service" name="business_type" value="Servicios" class="business-type-input">
                                <label for="service" class="business-type-label">Servicios</label>
                            </div>
                            <div class="business-type-option">
                                <input type="radio" id="wholesale" name="business_type" value="Mayorista" class="business-type-input">
                                <label for="wholesale" class="business-type-label">Mayorista</label>
                            </div>
                            <div class="business-type-option">
                                <input type="radio" id="manufacturing" name="business_type" value="Manufactura" class="business-type-input">
                                <label for="manufacturing" class="business-type-label">Manufactura</label>
                            </div>
                            <div class="business-type-option">
                                <input type="radio" id="other" name="business_type" value="Otro" class="business-type-input">
                                <label for="other" class="business-type-label">Otro</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ruc">RUC (opcional)</label>
                            <input type="text" id="ruc" name="ruc" class="form-input" 
                                   placeholder="20123456789" maxlength="11">
                        </div>
                        <div class="form-group">
                            <label for="phone">Teléfono del Negocio</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   placeholder="+51 999 999 999">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Dirección (opcional)</label>
                        <textarea id="address" name="address" class="form-input" rows="2" 
                                  placeholder="Dirección completa del negocio"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contacto del Negocio (opcional)</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-input" 
                               placeholder="Persona de contacto">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">
                        Comenzar a usar Treinta
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Validación en tiempo real
        document.getElementById('onboardingForm').addEventListener('submit', function(e) {
            const businessName = document.getElementById('business_name').value.trim();
            const businessType = document.querySelector('input[name="business_type"]:checked');
            
            if (!businessName) {
                e.preventDefault();
                alert('El nombre del negocio es requerido');
                document.getElementById('business_name').focus();
                return;
            }
            
            if (!businessType) {
                e.preventDefault();
                alert('Por favor selecciona el tipo de negocio');
                return;
            }
            
            // Mostrar loading
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Configurando tu negocio...';
            submitBtn.disabled = true;
        });
        
        // Validación de RUC
        document.getElementById('ruc').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });
        
        // Formato de teléfono
        document.getElementById('phone').addEventListener('input', function(e) {
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
        
        // Mostrar campo personalizado para "Otro"
        document.querySelectorAll('input[name="business_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customField = document.getElementById('custom_business_type');
                if (this.value === 'Otro') {
                    if (!customField) {
                        const customInput = document.createElement('input');
                        customInput.type = 'text';
                        customInput.id = 'custom_business_type';
                        customInput.name = 'custom_business_type';
                        customInput.className = 'form-input';
                        customInput.placeholder = 'Especifica tu tipo de negocio';
                        customInput.style.marginTop = '0.5rem';
                        customInput.required = true;
                        
                        const businessTypesContainer = document.querySelector('.business-types');
                        businessTypesContainer.parentNode.insertBefore(customInput, businessTypesContainer.nextSibling);
                    }
                } else {
                    if (customField) {
                        customField.remove();
                    }
                }
            });
        });
    </script>
</body>
</html>