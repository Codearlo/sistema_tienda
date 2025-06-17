<?php
session_start();
require_once 'backend/config/config.php';

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
                $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']); // AGREGADO
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
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex">
    <!-- Panel izquierdo -->
    <div class="hidden lg:flex lg:flex-1 flex-col justify-center p-12 text-white">
        <div class="mb-8">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-6">
                <span class="text-2xl font-bold text-blue-600">30</span>
            </div>
        </div>
        
        <h1 class="text-4xl font-bold mb-8">¿Por qué elegir Treinta?</h1>
        
        <div class="space-y-6">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-star text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Fácil de usar</h3>
                    <p class="text-blue-100">Interfaz intuitiva diseñada para dueños de negocio</p>
                </div>
            </div>
            
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-desktop text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Control total</h3>
                    <p class="text-blue-100">Gestiona ventas, inventario y finanzas en un solo lugar</p>
                </div>
            </div>
            
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Datos seguros</h3>
                    <p class="text-blue-100">Tu información protegida con tecnología de punta</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel derecho con formulario -->
    <div class="w-full lg:w-96 flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-white">30</span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Iniciar Sesión</h2>
                    <p class="text-gray-600 text-sm mt-2">Gestiona tu negocio de manera simple y eficiente</p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="mb-6 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Form -->
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="alejandro.cabanah@gmail.com"
                                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-envelope absolute left-3 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Contraseña</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-lock absolute left-3 top-3.5 text-gray-400"></i>
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-3.5 text-gray-400">
                                <i id="toggleIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center">
                            <input type="checkbox" name="remember" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-gray-600">Recordarme</span>
                        </label>
                        <a href="#" class="text-blue-600 hover:text-blue-500">¿Olvidaste tu contraseña?</a>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Iniciar Sesión
                    </button>
                </form>
                
                <div class="mt-6 text-center text-sm">
                    <span class="text-gray-600">¿No tienes cuenta? </span>
                    <a href="register.php" class="text-blue-600 font-medium hover:text-blue-500">Crear cuenta gratis</a>
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