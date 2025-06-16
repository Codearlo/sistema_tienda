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
        $db->update("users", ['business_id' => $business_id], "id = ?", [$user['id']]);
        
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
        redirectWithMessage('dashboard.php?welcome=1', '¡Bienvenido! Tu negocio ha sido configurado exitosamente.', 'success');
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
        error_log('Error en onboarding: ' . $e->getMessage());
    }
}

$page_title = 'Configurar Negocio';
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
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-600">
                    <i class="fas fa-store text-white text-xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    ¡Configura tu negocio!
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Hola <?= htmlspecialchars($user['first_name'] ?? '') ?>, necesitamos algunos datos para configurar tu negocio
                </p>
            </div>

            <!-- Formulario -->
            <div class="bg-white shadow-xl rounded-lg p-8">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 border border-red-300 text-red-700 bg-red-50 rounded-md">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="business_name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-store mr-2"></i>Nombre del negocio *
                            </label>
                            <input type="text" id="business_name" name="business_name" required
                                   value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>"
                                   placeholder="Ej: Tienda La Esperanza"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="business_type" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tags mr-2"></i>Tipo de negocio *
                            </label>
                            <select id="business_type" name="business_type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Seleccionar...</option>
                                <option value="retail" <?= ($_POST['business_type'] ?? '') == 'retail' ? 'selected' : '' ?>>Tienda/Retail</option>
                                <option value="restaurant" <?= ($_POST['business_type'] ?? '') == 'restaurant' ? 'selected' : '' ?>>Restaurante</option>
                                <option value="service" <?= ($_POST['business_type'] ?? '') == 'service' ? 'selected' : '' ?>>Servicios</option>
                                <option value="pharmacy" <?= ($_POST['business_type'] ?? '') == 'pharmacy' ? 'selected' : '' ?>>Farmacia</option>
                                <option value="grocery" <?= ($_POST['business_type'] ?? '') == 'grocery' ? 'selected' : '' ?>>Abarrotes</option>
                                <option value="other" <?= ($_POST['business_type'] ?? '') == 'other' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>

                        <div>
                            <label for="ruc" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-2"></i>RUC (opcional)
                            </label>
                            <input type="text" id="ruc" name="ruc"
                                   value="<?= htmlspecialchars($_POST['ruc'] ?? '') ?>"
                                   placeholder="20123456789"
                                   maxlength="11"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-2"></i>Dirección
                            </label>
                            <textarea id="address" name="address" rows="3"
                                      placeholder="Ej: Av. Los Pinos 123, San Isidro, Lima"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone mr-2"></i>Teléfono
                            </label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   placeholder="987654321"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>Persona de contacto
                            </label>
                            <input type="text" id="contact_person" name="contact_person"
                                   value="<?= htmlspecialchars($_POST['contact_person'] ?? $user['first_name'] . ' ' . $user['last_name']) ?>"
                                   placeholder="Nombre del responsable"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-6">
                        <a href="backend/auth/logout.php" class="text-sm text-gray-600 hover:text-gray-900">
                            <i class="fas fa-sign-out-alt mr-1"></i>
                            Cerrar sesión
                        </a>
                        
                        <button type="submit" 
                                class="flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-check mr-2"></i>
                            Configurar negocio
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center text-sm text-gray-500">
                <p>Podrás modificar esta información más tarde en configuración</p>
            </div>
        </div>
    </div>

    <script>
        // Validación de RUC
        document.getElementById('ruc').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });

        // Validación de teléfono
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
    </script>
</body>
</html>