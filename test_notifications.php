<?php
session_start();
require_once 'backend/config/database.php';
require_once 'backend/notifications/NotificationHelper.php';

if (!isset($_SESSION['business_id'])) {
    die('Debes estar logueado para probar notificaciones');
}

$business_id = $_SESSION['business_id'];
$helper = new NotificationHelper();

echo "<h1>Test de Notificaciones en Tiempo Real</h1>";
echo "<p>Business ID: $business_id</p>";

if ($_POST['action'] ?? '') {
    switch ($_POST['action']) {
        case 'sale':
            $helper->saleCompleted($business_id, 150.50, 'Juan Pérez');
            echo "✅ Notificación de venta enviada<br>";
            break;
            
        case 'stock':
            $helper->lowStock($business_id, 'Coca Cola 500ml', 3, 10);
            echo "⚠️ Notificación de stock bajo enviada<br>";
            break;
            
        case 'payment':
            $helper->paymentReceived($business_id, 75.00, 'María García');
            echo "💰 Notificación de pago enviada<br>";
            break;
            
        case 'product':
            $helper->productAdded($business_id, 'Nuevo Producto Test', $_SESSION['user_name'] ?? 'Usuario');
            echo "📦 Notificación de producto enviada<br>";
            break;
            
        case 'error':
            $helper->systemError($business_id, 'Error de prueba del sistema');
            echo "❌ Notificación de error enviada<br>";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notificaciones</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/notifications.js"></script>
</head>
<body class="dashboard-page">
    <div style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h2>Probar Notificaciones</h2>
        <p>Haz clic en los botones para enviar diferentes tipos de notificaciones:</p>
        
        <div style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin: 30px 0;">
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="sale">
                <button type="submit" class="btn btn-success">
                    💰 Simular Venta
                </button>
            </form>
            
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="stock">
                <button type="submit" class="btn btn-warning">
                    ⚠️ Stock Bajo
                </button>
            </form>
            
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="payment">
                <button type="submit" class="btn btn-primary">
                    💸 Simular Pago
                </button>
            </form>
            
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="product">
                <button type="submit" class="btn btn-gray">
                    📦 Nuevo Producto
                </button>
            </form>
            
            <form method="POST" style="display: contents;">
                <input type="hidden" name="action" value="error">
                <button type="submit" class="btn btn-error">
                    ❌ Error Sistema
                </button>
            </form>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin: 30px 0;">
            <h3>📡 Estado de Conexión</h3>
            <p>Mira la esquina inferior derecha para ver el indicador de conexión:</p>
            <ul>
                <li>🟢 Verde = Conectado a notificaciones en tiempo real</li>
                <li>🔴 Rojo parpadeando = Desconectado</li>
            </ul>
        </div>
        
        <div style="background: #e3f2fd; padding: 20px; border-radius: 12px; margin: 30px 0;">
            <h3>🔔 Cómo Funciona</h3>
            <ol>
                <li><strong>Server-Sent Events (SSE):</strong> Conexión en tiempo real con el servidor</li>
                <li><strong>Notificaciones automáticas:</strong> Aparecen en la esquina superior derecha</li>
                <li><strong>Notificaciones del navegador:</strong> Si das permisos, también aparecen como notificaciones nativas</li>
                <li><strong>Reconexión automática:</strong> Si se pierde la conexión, se reconecta automáticamente</li>
            </ol>
        </div>
        
        <div style="background: #fff3cd; padding: 20px; border-radius: 12px; margin: 30px 0;">
            <h3>⚙️ Configuración</h3>
            <p>Para usar en tu aplicación:</p>
            <pre style="background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto;"><code>// En cualquier archivo PHP donde quieras enviar notificaciones:
require_once 'backend/notifications/NotificationHelper.php';
$helper = new NotificationHelper();

// Ejemplos:
$helper->saleCompleted($business_id, 150.50, 'Cliente XYZ');
$helper->lowStock($business_id, 'Producto ABC', 2, 10);
$helper->paymentReceived($business_id, 75.00);

// O usando la función global:
createNotification($business_id, 'info', 'Título', 'Mensaje');</code></pre>
        </div>
        
        <div style="display: flex; gap: 15px; margin: 30px 0;">
            <a href="dashboard.php" class="btn btn-primary">
                🏠 Ir al Dashboard
            </a>
            <a href="settings.php" class="btn btn-gray">
                ⚙️ Configuración
            </a>
            <button onclick="testBrowserNotification()" class="btn btn-warning">
                🔔 Test Notificación Navegador
            </button>
        </div>
    </div>
    
    <script>
        function testBrowserNotification() {
            if ('Notification' in window) {
                if (Notification.permission === 'granted') {
                    new Notification('Test Treinta', {
                        body: 'Las notificaciones del navegador están funcionando correctamente',
                        icon: '/favicon.ico'
                    });
                } else if (Notification.permission === 'default') {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            new Notification('Test Treinta', {
                                body: 'Permisos concedidos. Las notificaciones están activadas.',
                                icon: '/favicon.ico'
                            });
                        }
                    });
                } else {
                    alert('Las notificaciones están bloqueadas. Actívalas en la configuración del navegador.');
                }
            } else {
                alert('Tu navegador no soporta notificaciones.');
            }
        }
        
        // Auto-test de conexión al cargar
        setTimeout(() => {
            if (window.notificationSystem && window.notificationSystem.isConnectedToNotifications()) {
                console.log('✅ Sistema de notificaciones funcionando correctamente');
            } else {
                console.warn('⚠️ Sistema de notificaciones no conectado');
            }
        }, 2000);
    </script>
</body>
</html>