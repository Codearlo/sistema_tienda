<?php
session_start();
require_once 'backend/config/config.php';

// Si ya está logueado, redirigir al dashboard o onboarding
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
            if (!$db->isConnected()) {
                throw new Exception("Error del servidor. Por favor intenta nuevamente.");
            }
            
            // Buscar usuario por email
            $user = $db->single(
                "SELECT u.*, b.id as business_id, b.business_name 
                 FROM users u 
                 LEFT JOIN businesses b ON u.business_id = b.id 
                 WHERE u.email = ? AND u.status = ?", 
                [$email, STATUS_ACTIVE]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['logged_in_at'] = time();
                
                // Si tiene negocio configurado
                if ($user['business_id']) {
                    $_SESSION['business_id'] = $user['business_id'];
                    $_SESSION['business_name'] = $user['business_name'];
                }
                
                // Actualizar último login
                try {
                    $db->update("users", ['last_login' => date('Y-m-d H:i:s')], "id = ?", [$user['id']]);
                } catch (Exception $updateError) {
                    error_log('Error updating last login: ' . $updateError->getMessage());
                }
                
                // Manejar "recordarme"
                if ($remember) {
                    try {
                        $token = generateToken(64);
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        // Guardar token en BD (si existe la tabla)
                        $db->insert("remember_tokens", [
                            'user_id' => $user['id'],
                            'token' => hash('sha256', $token),
                            'expires_at' => $expires,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Crear cookie
                        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                    } catch (Exception $rememberError) {
                        error_log('Error setting remember token: ' . $rememberError->getMessage());
                        // No fallar el login por esto
                    }
                }
                
                // Log del login
                if (getConfig('enable_logging', true)) {
                    error_log('[' . date('Y-m-d H:i:s') . '] Login exitoso - Usuario: ' . $email . ' - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
                
                // Redirigir según si tiene negocio configurado
                if ($user['business_id']) {
                    $redirect = $_GET['redirect'] ?? 'dashboard.php';
                } else {
                    $redirect = 'onboarding.php';
                }
                
                header("Location: $redirect");
                exit();
                
            } else {
                $error = 'Email o contraseña incorrectos.';
                
                // Registrar intento fallido si el usuario existe
                if ($user) {
                    try {
                        $db->query(
                            "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?",
                            [$user['id']]
                        );
                    } catch (Exception $attemptError) {
                        error_log('Error updating login attempts: ' . $attemptError->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

$page_title = 'Iniciar Sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bg-gradient-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gradient-custom min-h-screen flex">
    <!-- Panel izquierdo con información -->
    <div class="hidden lg:flex lg:w-1/2 xl:w-2/3 relative overflow-hidden">
        <div class="absolute inset-0 bg-blue-600 opacity-75"></div>
        <div class="relative z-10 flex flex-col justify-center px-16 text-white">
            <div class="mb-8">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-6">
                    <span class="text-2xl font-bold text-blue-600">30</span>
                </div>
            </div>
            
            <h1 class="text-4xl font-bold mb-6">¿Por qué elegir Treinta?</h1>
            
            <div class="space-y-8">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold">Fácil de usar</h3>
                        <p class="text-blue-100">Interfaz intuitiva diseñada para dueños de negocio</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-desktop text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold">Control total</h3>
                        <p class="text-blue-100">Gestiona ventas, inventario y finanzas en un solo lugar</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-shield-alt text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold">Datos seguros</h3>
                        <p class="text-blue-100">Tu información protegida con tecnología de punta</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel derecho con formulario -->
    <div class="w-full lg:w-1/2 xl:w-1/3 flex items-center justify-center p-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Logo y título -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-white">30</span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Iniciar Sesión</h2>
                    <p class="text-gray-600 mt-2">Gestiona tu negocio de manera simple y eficiente</p>
                </div>
                
                <!-- Errores -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 border border-red-300 text-red-700 bg-red-50 rounded-lg">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mb-6 p-4 border border-green-300 text-green-700 bg-green-50 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario -->
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email
                        </label>
                        <div class="relative">
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="alejandro.cabanah@gmail.com"
                                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-envelope absolute left-3 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Contraseña
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-lock absolute left-3 top-3.5 text-gray-400"></i>
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                <i id="toggleIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-600">Recordarme</span>
                        </label>
                        <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-500">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    
                    <button type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                        Iniciar Sesión
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        ¿No tienes cuenta? 
                        <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Crear cuenta gratis
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>