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
            
            // Buscar usuario por email
            $user = $db->single(
                "SELECT u.*, b.id as business_id, b.business_name 
                 FROM users u 
                 LEFT JOIN businesses b ON u.id = b.owner_id 
                 WHERE u.email = ? AND u.status = ?", 
                [$email, STATUS_ACTIVE]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['business_id'] = $user['business_id'];
                $_SESSION['business_name'] = $user['business_name'];
                $_SESSION['logged_in_at'] = time();
                
                // Actualizar último login
                $db->update("users", ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
                
                // Manejar "recordarme"
                if ($remember) {
                    $token = generateToken(64);
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Guardar token en BD
                    $db->insert("remember_tokens", [
                        'user_id' => $user['id'],
                        'token' => hash('sha256', $token),
                        'expires_at' => $expires,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Crear cookie
                    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                }
                
                // Log del login
                if (getConfig('enable_logging', true)) {
                    error_log('[' . date('Y-m-d H:i:s') . '] Login exitoso - Usuario: ' . $email . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
                
                // Redirigir según el tipo de usuario o página solicitada
                $redirect = $_GET['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
                exit();
                
            } else {
                $error = 'Email o contraseña incorrectos.';
                
                // Log del intento fallido
                if (getConfig('enable_logging', true)) {
                    error_log('[' . date('Y-m-d H:i:s') . '] Login fallido - Email: ' . $email . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
            }
            
        } catch (Exception $e) {
            $error = 'Error del servidor. Por favor intenta nuevamente.';
            error_log('Error en login: ' . $e->getMessage());
        }
    }
}

// Verificar token de "recordarme"
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        
        $result = $db->single(
            "SELECT u.*, b.id as business_id, b.business_name, rt.id as token_id
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             LEFT JOIN businesses b ON u.id = b.owner_id
             WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = ?",
            [$token_hash, STATUS_ACTIVE]
        );
        
        if ($result) {
            // Auto-login
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['user_email'] = $result['email'];
            $_SESSION['user_name'] = $result['first_name'] . ' ' . $result['last_name'];
            $_SESSION['user_type'] = $result['user_type'];
            $_SESSION['business_id'] = $result['business_id'];
            $_SESSION['business_name'] = $result['business_name'];
            $_SESSION['logged_in_at'] = time();
            
            // Actualizar último login
            $db->update("users", ['last_login' => date('Y-m-d H:i:s')], ['id' => $result['id']]);
            
            header('Location: dashboard.php');
            exit();
        } else {
            // Token inválido, eliminar cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        error_log('Error verificando remember token: ' . $e->getMessage());
    }
}

$flash = showFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Treinta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <svg class="logo-img" viewBox="0 0 100 100" width="60" height="60">
                        <circle cx="50" cy="50" r="45" fill="#2563eb" stroke="#1d4ed8" stroke-width="2"/>
                        <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
                    </svg>
                </div>
                <h2 class="login-title">Iniciar Sesión</h2>
                <p class="login-subtitle">Gestiona tu negocio de manera simple y eficiente</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($flash['type'] === 'error'): ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        <?php elseif ($flash['type'] === 'success'): ?>
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22,4 12,14.01 9,11.01"></polyline>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
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
            
            <form method="POST" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="tu@email.com" value="<?= $_POST['email'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <circle cx="12" cy="16" r="1"></circle>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" class="checkbox">
                        <span class="checkmark"></span>
                        Recordarme
                    </label>
                    <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="login-footer">
                <p>¿No tienes cuenta? <a href="register.php" class="register-link">Crear cuenta gratis</a></p>
            </div>
        </div>
        
        <div class="info-section">
            <h2>¿Por qué elegir Treinta?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <svg class="feature-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    <div>
                        <h3>Fácil de usar</h3>
                        <p>Interfaz intuitiva diseñada para dueños de negocio</p>
                    </div>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <div>
                        <h3>Control total</h3>
                        <p>Gestiona ventas, inventario y finanzas en un solo lugar</p>
                    </div>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"></path>
                        <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                        <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                        <path d="M15 21c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                        <path d="M9 21c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                    </svg>
                    <div>
                        <h3>Datos seguros</h3>
                        <p>Tu información protegida con tecnología de punta</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94L17.94 17.94z"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                    <path d="M10.65 10.65a3 3 0 1 1 4.24 4.24"></path>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Por favor completa todos los campos');
                return;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Por favor ingresa un email válido');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Iniciando sesión...';
            
            // Reset button after form submission
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }, 3000);
        });
        
        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Handle keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const focusedElement = document.activeElement;
                if (focusedElement.id === 'email') {
                    document.getElementById('password').focus();
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>