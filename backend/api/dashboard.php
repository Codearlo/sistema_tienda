<?php
/**
 * API DEL DASHBOARD
 * Archivo: backend/api/dashboard.php
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$business_id = $_SESSION['business_id'];

try {
    $db = getDB();
    $today = date('Y-m-d');
    
    // 1. Ventas del día
    $sales_today = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(sale_date) = ? AND status = 1",
        [$business_id, $today]
    );
    
    // 2. Productos vendidos hoy
    $products_sold_today = $db->single(
        "SELECT COALESCE(SUM(si.quantity), 0) as total
         FROM sale_items si
         JOIN sales s ON si.sale_id = s.id
         WHERE s.business_id = ? AND DATE(s.sale_date) = ? AND s.status = 1",
        [$business_id, $today]
    );
    
    // 3. Productos con stock bajo
    $low_stock_products = $db->fetchAll(
        "SELECT id, name, stock_quantity, min_stock, unit
         FROM products 
         WHERE business_id = ? AND stock_quantity <= min_stock AND status = 1 
         ORDER BY (stock_quantity - min_stock) ASC 
         LIMIT 10",
        [$business_id]
    );
    
    // 4. Deudas pendientes
    $pending_debts = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total 
         FROM debts 
         WHERE business_id = ? AND status = 'pending'",
        [$business_id]
    );
    
    // 5. Ventas recientes (últimas 10)
    $recent_sales = $db->fetchAll(
        "SELECT 
            s.id,
            s.sale_number,
            s.sale_date,
            s.total_amount,
            s.payment_status,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
         FROM sales s
         LEFT JOIN customers c ON s.customer_id = c.id
         WHERE s.business_id = ? AND s.status = 1
         ORDER BY s.sale_date DESC
         LIMIT 10",
        [$business_id]
    );
    
    // 6. Deudas próximas a vencer (próximos 7 días)
    $upcoming_debts = $db->fetchAll(
        "SELECT 
            d.id,
            d.due_date,
            d.remaining_amount,
            d.status,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name
         FROM debts d
         LEFT JOIN customers c ON d.customer_id = c.id
         WHERE d.business_id = ? 
         AND d.status = 'pending'
         AND d.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY d.due_date ASC
         LIMIT 10",
        [$business_id]
    );
    
    // 7. Gráfico de ventas (últimos 7 días)
    $sales_chart = $db->fetchAll(
        "SELECT 
            DATE(sale_date) as date,
            COALESCE(SUM(total_amount), 0) as total
         FROM sales 
         WHERE business_id = ? 
         AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         AND status = 1
         GROUP BY DATE(sale_date)
         ORDER BY date ASC",
        [$business_id]
    );
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => [
                'sales_today' => $sales_today,
                'products_sold_today' => $products_sold_today,
                'low_stock_products' => $low_stock_products,
                'pending_debts' => $pending_debts
            ],
            'recent_sales' => $recent_sales,
            'upcoming_debts' => $upcoming_debts,
            'sales_chart' => $sales_chart
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en dashboard.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al cargar datos del dashboard'
    ]);
}
?>