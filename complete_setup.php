<?php
session_start();
require_once 'backend/config/config.php';

// Verificar que el usuario esté logueado y tenga un negocio
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    echo "Error: Debes estar logueado y tener un negocio configurado.";
    exit();
}

echo "<h1>Completando Configuración Inicial</h1>";

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    $user_id = $_SESSION['user_id'];
    
    echo "<h2>Verificando configuraciones existentes...</h2>";
    
    // Verificar qué ya existe
    $existing_settings = $db->fetchAll("SELECT setting_key FROM settings WHERE business_id = ?", [$business_id]);
    $existing_categories = $db->fetchAll("SELECT name FROM categories WHERE business_id = ?", [$business_id]);
    
    echo "Configuraciones existentes: " . count($existing_settings) . "<br>";
    echo "Categorías existentes: " . count($existing_categories) . "<br><br>";
    
    // Insertar configuraciones por defecto si no existen
    if (count($existing_settings) == 0) {
        echo "<h3>Creando configuraciones por defecto...</h3>";
        
        $default_settings = [
            ['business_timezone', 'America/Lima', 'string', 'Zona horaria del negocio'],
            ['default_tax_rate', '18', 'number', 'Tasa de impuesto por defecto'],
            ['currency_symbol', 'S/', 'string', 'Símbolo de moneda'],
            ['low_stock_alert', '1', 'boolean', 'Alertas de stock bajo activadas'],
            ['auto_backup', '1', 'boolean', 'Backup automático activado'],
            ['email_sales_report', '0', 'boolean', 'Reportes de ventas por email'],
            ['email_low_stock', '1', 'boolean', 'Alertas de stock bajo por email'],
            ['low_stock_threshold', '10', 'number', 'Umbral de stock bajo']
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
            echo "✅ Configuración creada: " . $setting[0] . "<br>";
        }
    } else {
        echo "✅ Las configuraciones ya existen.<br>";
    }
    
    // Crear categorías básicas si no existen
    if (count($existing_categories) == 0) {
        echo "<h3>Creando categorías por defecto...</h3>";
        
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
            echo "✅ Categoría creada: " . $category[0] . "<br>";
        }
    } else {
        echo "✅ Las categorías ya existen.<br>";
    }
    
    // Verificar si existe un cliente por defecto
    $existing_customers = $db->fetchAll("SELECT COUNT(*) as total FROM customers WHERE business_id = ?", [$business_id]);
    $customer_count = $existing_customers[0]['total'];
    
    if ($customer_count == 0) {
        echo "<h3>Creando cliente por defecto...</h3>";
        
        $db->insert('customers', [
            'business_id' => $business_id,
            'first_name' => 'Cliente',
            'last_name' => 'General',
            'email' => 'cliente@ejemplo.com',
            'phone' => '999999999',
            'address' => 'Dirección de ejemplo',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Cliente por defecto creado.<br>";
    } else {
        echo "✅ Ya existen clientes.<br>";
    }
    
    // Crear algunos productos de ejemplo si no existen
    $existing_products = $db->fetchAll("SELECT COUNT(*) as total FROM products WHERE business_id = ?", [$business_id]);
    $product_count = $existing_products[0]['total'];
    
    if ($product_count == 0) {
        echo "<h3>Creando productos de ejemplo...</h3>";
        
        // Obtener IDs de categorías
        $categories = $db->fetchAll("SELECT id, name FROM categories WHERE business_id = ? ORDER BY id", [$business_id]);
        
        if (count($categories) > 0) {
            $example_products = [
                ['Coca Cola 500ml', 'Gaseosa Coca Cola de 500ml', 2.50, 4.00, 50, 0],
                ['Pan Integral', 'Pan integral artesanal', 1.20, 2.50, 20, 0],
                ['Cable USB-C', 'Cable USB-C de 1 metro', 15.00, 25.00, 15, 1],
                ['Camiseta Básica', 'Camiseta 100% algodón', 25.00, 45.00, 30, 2]
            ];
            
            foreach ($example_products as $index => $product) {
                $category_index = min($index, count($categories) - 1);
                $category_id = $categories[$category_index]['id'];
                
                $db->insert('products', [
                    'business_id' => $business_id,
                    'category_id' => $category_id,
                    'sku' => 'PROD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'name' => $product[0],
                    'description' => $product[1],
                    'cost_price' => $product[2],
                    'selling_price' => $product[3],
                    'stock_quantity' => $product[4],
                    'min_stock' => 5,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                echo "✅ Producto creado: " . $product[0] . "<br>";
            }
        }
    } else {
        echo "✅ Ya existen productos.<br>";
    }
    
    echo "<h2>✅ Configuración inicial completada exitosamente!</h2>";
    echo "<p><a href='dashboard.php'>Ir al Dashboard</a> | <a href='settings.php'>Ir a Configuración</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Detalles técnicos guardados en el log del servidor.</p>";
    error_log('Error completing setup: ' . $e->getMessage());
}
?>