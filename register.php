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
    
    // Validaciones
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios.';
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
                
                // CORRECCIÓN: Crear usuario con la sintaxis correcta
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
                
                $db->commit();
                
                // Crear sesión automáticamente
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_type'] = USER_TYPE_OWNER;
                $_SESSION['logged_in_at'] = time();
                
                redirectWithMessage('onboarding.php', '¡Cuenta creada exitosamente! Ahora configura tu negocio.', 'success');
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error al crear la cuenta. Por favor intenta nuevamente.';
            error_log('Error en registro: ' . $e->getMessage());
        }
    }
}

$page_title = 'Registro';
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
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Crear cuenta
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                ¿Ya tienes cuenta? 
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                    Inicia sesión aquí
                </a>
            </p>
        </div>
        
        <div class="bg-white shadow-xl rounded-lg p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 border border-red-300 text-red-700 bg-red-50 rounded-md">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mb-6 p-4 border border-green-300 text-green-700 bg-green-50 rounded-md">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Apellido
                        </label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email
                    </label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Contraseña
                    </label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500">Mínimo 6 caracteres</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                        Confirmar contraseña
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-user-plus mr-2"></i>
                    Crear cuenta
                </button>
                
                <div class="text-center">
                    <a href="index.php" class="text-sm text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Volver al inicio
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>