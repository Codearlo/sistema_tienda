<?php
session_start();
require_once 'backend/config/config.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = cleanInput($_POST['first_name'] ?? '');
    $last_name = cleanInput($_POST['last_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $business_name = cleanInput($_POST['business_name'] ?? '');
    $business_type = cleanInput($_POST['business_type'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    
    // Validaciones
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($business_name)) {
        $error = 'Todos los campos obligatorios deben ser completados.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no tiene un formato válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $db = getDB();
            
            // Verificar si el email ya existe
            $existingUser = $db->single("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                $error = 'Este email ya está registrado.';
            } else {
                $db->beginTransaction();
                
                // Crear usuario
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $userId = $db->insert("users", [
                    'email' => $email,
                    'password' => $hashedPassword,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'user_type' => USER_TYPE_OWNER,
                    'status' => STATUS_ACTIVE,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Crear negocio
                $businessId = $db->insert("businesses", [
                    'owner_id' => $userId,
                    'business_name' => $business_name,
                    'business_type' => $business_type,
                    'phone' => $phone,
                    'email' => $email,
                    'status' => STATUS_ACTIVE,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Crear configuración inicial del negocio
                $db->insert("settings", [
                    'business_id' => $businessId,
                    'currency_symbol' => '$',
                    'decimal_places' => 2,
                    'tax_rate' => 0,
                    'business_hours' => '09:00-18:00',
                    'timezone' => 'America/Lima',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Crear categorías por defecto
                $defaultCategories = [
                    'General',
                    'Alimentos',
                    'Bebidas',
                    'Limpieza',
                    'Electrónicos'
                ];
                
                foreach ($defaultCategories as $categoryName) {
                    $db->insert("categories", [
                        'business_id' => $businessId,
                        'name' => $categoryName,
                        'status' => STATUS_ACTIVE,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                $db->commit();
                
                // Crear sesión automáticamente
                $_SESSION['user_id'] = $userId;
                $_SESSION['business_id'] = $businessId;
                
                redirectWithMessage('dashboard.php', '¡Registro exitoso! Bienvenido a tu nuevo negocio.', 'success');
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error al crear la cuenta. Por favor intenta nuevamente.';
            error_log('Error en registro: ' . $e->getMessage());
        }
    }
}

$flash = showFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Crear cuenta</h1>
                <p>Comienza a gestionar tu negocio hoy</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= $flash['message'] ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form" id="registerForm">
                <div class="form-section">
                    <h3>Información personal</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Nombres *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?= $_POST['first_name'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Apellidos *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?= $_POST['last_name'] ?? '' ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= $_POST['email'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Contraseña *</label>
                            <input type="password" id="password" name="password" required>
                            <small>Mínimo 6 caracteres</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar contraseña *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Información del negocio</h3>
                    <div class="form-group">
                        <label for="business_name">Nombre del negocio *</label>
                        <input type="text" id="business_name" name="business_name" 
                               value="<?= $_POST['business_name'] ?? '' ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="business_type">Tipo de negocio</label>
                            <select id="business_type" name="business_type">
                                <option value="">Seleccionar</option>
                                <option value="Retail" <?= ($_POST['business_type'] ?? '') === 'Retail' ? 'selected' : '' ?>>Tienda/Retail</option>
                                <option value="Restaurant" <?= ($_POST['business_type'] ?? '') === 'Restaurant' ? 'selected' : '' ?>>Restaurante</option>
                                <option value="Services" <?= ($_POST['business_type'] ?? '') === 'Services' ? 'selected' : '' ?>>Servicios</option>
                                <option value="Wholesale" <?= ($_POST['business_type'] ?? '') === 'Wholesale' ? 'selected' : '' ?>>Mayorista</option>
                                <option value="Other" <?= ($_POST['business_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="phone">Teléfono</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= $_POST['phone'] ?? '' ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        <span class="checkmark"></span>
                        Acepto los <a href="#" target="_blank">términos y condiciones</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Crear cuenta
                </button>
            </form>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Validación del formulario
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                Utils.showAlert('Las contraseñas no coinciden', 'error');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                Utils.showAlert('La contraseña debe tener al menos 6 caracteres', 'error');
                return;
            }
        });
        
        // Validación en tiempo real
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>