<?php
session_start();
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

// Funciones auxiliares
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    if (isset($_SESSION['business_id']) && !empty($_SESSION['business_id'])) {
        header('Location: dashboard.php');
    } else {
        header('Location: onboarding.php');
    }
    exit();
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validaciones básicas
    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no tiene un formato válido.';
    } else {
        try {
            $db = getDB();
            
            // Verificar conexión
            if (!$db) {
                throw new Exception("Error del servidor. Por favor intenta nuevamente.");
            }
            
            // Buscar usuario por email
            $user = $db->fetchOne(
                "SELECT u.*, b.id as business_id, b.name as business_name 
                 FROM users u 
                 LEFT JOIN businesses b ON u.business_id = b.id 
                 WHERE u.email = ? AND u.status = 1", 
                [$email]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['logged_in_at'] = time();
                
                // Si tiene negocio configurado
                if ($user['business_id']) {
                    $_SESSION['business_id'] = $user['business_id'];
                    $_SESSION['business_name'] = $user['business_name'];
                }
                
                // Actualizar último login
                try {
                    $db->execute("UPDATE users SET last_login = ? WHERE id = ?", [date('Y-m-d H:i:s'), $user['id']]);
                } catch (Exception $updateError) {
                    error_log('Error updating last login: ' . $updateError->getMessage());
                }
                
                // Configurar cookie si marcó "recordarme"
                if ($remember) {
                    setcookie('remember_user', $user['email'], time() + (86400 * 30), '/'); // 30 días
                }
                
                // Redirigir según si tiene negocio configurado
                if ($user['business_id']) {
                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                    header('Location: ' . $redirect);
                } else {
                    header('Location: onboarding.php');
                }
                exit();
                
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
            
        } catch (Exception $e) {
            error_log('Error en login: ' . $e->getMessage());
            $error = 'Error del servidor. Por favor intenta nuevamente.';
        }
    }
}

// Verificar si hay mensajes en la sesión
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Obtener email recordado
$remembered_email = $_COOKIE['remember_user'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Treinta</title>
    <?php 
    forceCssReload();
    includeCss('assets/css/style.css');
    includeCss('assets/css/pages/auth.css');
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <!-- Background Pattern -->
        <div class="auth-background">
            <div class="auth-pattern"></div>
        </div>

        <!-- Login Form -->
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-store"></i>
                </div>
                <h1>Treinta</h1>
                <p>Inicia sesión en tu cuenta</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form" id="loginForm" novalidate>
                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i>
                        Email
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input <?php echo $error && strpos($error, 'email') !== false ? 'error' : ''; ?>" 
                        placeholder="tu@email.com"
                        value="<?php echo htmlspecialchars($remembered_email); ?>"
                        required
                        autocomplete="email"
                        autofocus
                    >
                    <div class="form-error" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i>
                        Contraseña
                    </label>
                    <div class="password-input-group">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input <?php echo $error && strpos($error, 'contraseña') !== false ? 'error' : ''; ?>" 
                            placeholder="Tu contraseña"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    <div class="form-error" id="passwordError"></div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" <?php echo $remembered_email ? 'checked' : ''; ?>>
                        <span class="checkbox-custom"></span>
                        Recordarme
                    </label>
                    <a href="forgot-password.php" class="link-secondary">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="loginButton">
                    <span class="btn-text">Iniciar Sesión</span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Iniciando...
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p>¿No tienes una cuenta? <a href="register.php" class="link-primary">Regístrate aquí</a></p>
            </div>

            <!-- Demo Users -->
            <div class="demo-section">
                <h4>Usuarios de Prueba</h4>
                <div class="demo-users">
                    <button type="button" class="demo-user-btn" onclick="fillDemoUser('admin@treinta.com', 'admin123')">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin</span>
                    </button>
                    <button type="button" class="demo-user-btn" onclick="fillDemoUser('vendedor@treinta.com', 'vendedor123')">
                        <i class="fas fa-user"></i>
                        <span>Vendedor</span>
                    </button>
                    <button type="button" class="demo-user-btn" onclick="fillDemoUser('gerente@treinta.com', 'gerente123')">
                        <i class="fas fa-user-tie"></i>
                        <span>Gerente</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- App Info -->
        <div class="app-info">
            <div class="app-features">
                <div class="feature">
                    <i class="fas fa-cash-register"></i>
                    <h3>Punto de Venta</h3>
                    <p>Sistema completo para ventas</p>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <h3>Reportes</h3>
                    <p>Analiza tu negocio en tiempo real</p>
                </div>
                <div class="feature">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Multiplataforma</h3>
                    <p>Accede desde cualquier dispositivo</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php includeJs('assets/js/app.js'); ?>
    
    <script>
        // Validación del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            let hasErrors = false;

            // Limpiar errores previos
            clearFormErrors();

            // Validar email
            if (!email) {
                showFieldError('email', 'El email es obligatorio');
                hasErrors = true;
            } else if (!isValidEmail(email)) {
                showFieldError('email', 'El formato del email no es válido');
                hasErrors = true;
            }

            // Validar contraseña
            if (!password) {
                showFieldError('password', 'La contraseña es obligatoria');
                hasErrors = true;
            } else if (password.length < 6) {
                showFieldError('password', 'La contraseña debe tener al menos 6 caracteres');
                hasErrors = true;
            }

            if (hasErrors) {
                e.preventDefault();
                return false;
            }

            // Mostrar loading
            showLoading();
        });

        function clearFormErrors() {
            document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
            document.querySelectorAll('.form-input').forEach(el => el.classList.remove('error'));
        }

        function showFieldError(fieldName, message) {
            const field = document.getElementById(fieldName);
            const errorEl = document.getElementById(fieldName + 'Error');
            
            field.classList.add('error');
            if (errorEl) {
                errorEl.textContent = message;
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function showLoading() {
            const button = document.getElementById('loginButton');
            const btnText = button.querySelector('.btn-text');
            const btnLoading = button.querySelector('.btn-loading');
            
            button.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
        }

        function hideLoading() {
            const button = document.getElementById('loginButton');
            const btnText = button.querySelector('.btn-text');
            const btnLoading = button.querySelector('.btn-loading');
            
            button.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }

        // Mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + 'ToggleIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Usuarios demo
        function fillDemoUser(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            // Opcional: enviar automáticamente
            // document.getElementById('loginForm').submit();
        }

        // Auto-focus en el primer campo vacío
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                passwordInput.focus();
            }

            // Ocultar loading si la página se recarga
            hideLoading();
        });

        // Manejar Enter en los campos
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form.checkValidity()) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>