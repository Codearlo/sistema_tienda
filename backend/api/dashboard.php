<?php
/**
 * API DEL DASHBOARD - CORREGIDA
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
    $this_month = date('Y-m');
    
    // 1. Ventas del día - ORIGINAL
    $sales_today = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $today]
    );
    
    // 2. Ventas del mes - ORIGINAL
    $sales_month = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
        [$business_id]
    );
    
    // 3. Total de productos activos - CORREGIDO
    $total_products = $db->single(
        "SELECT COUNT(*) as count FROM products WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
    // 4. Total de clientes activos - CORREGIDO
    $total_customers = $db->single(
        "SELECT COUNT(*) as count FROM customers WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
    // 5. Productos con stock bajo - CORREGIDO
    $low_stock_products = $db->fetchAll(
        "SELECT id, name, stock_quantity, min_stock, unit
         FROM products 
         WHERE business_id = ? AND stock_quantity <= min_stock AND status = 1 
         ORDER BY (stock_quantity - min_stock) ASC 
         LIMIT 10",
        [$business_id]
    );
    
    // 6. Deudas pendientes
    $pending_debts = $db->single(
        "SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total 
         FROM debts 
         WHERE business_id = ? AND status = 'pending'",
        [$business_id]
    );
    
    // 7. Ventas recientes (últimas 10) - ORIGINAL
    $recent_sales = $db->fetchAll(
        "SELECT 
            s.id,
            s.sale_number,
            s.created_at as sale_date,
            s.total_amount,
            s.payment_status,
            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
         FROM sales s
         LEFT JOIN customers c ON s.customer_id = c.id
         WHERE s.business_id = ?
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 10",
        [$business_id]
    );
    
    // 8. Deudas próximas a vencer (próximos 7 días)
    $upcoming_debts = $db->fetchAll(
        "SELECT 
            d.id,
            d.due_date,
            d.remaining_amount,
            d.status,
            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name
         FROM debts d
         LEFT JOIN customers c ON d.customer_id = c.id
         WHERE d.business_id = ? 
         AND d.status = 'pending'
         AND d.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY d.due_date ASC
         LIMIT 10",
        [$business_id]
    );
    
    // 9. Gráfico de ventas (últimos 7 días) - ORIGINAL
    $sales_chart = $db->fetchAll(
        "SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(total_amount), 0) as total
         FROM sales 
         WHERE business_id = ? 
         AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC",
        [$business_id]
    );
    
    // 10. Productos más vendidos del mes - ORIGINAL
    $top_products = $db->fetchAll(
        "SELECT 
            p.name, 
            SUM(si.quantity) as total_sold, 
            SUM(si.line_total) as revenue
         FROM sale_items si 
         JOIN products p ON si.product_id = p.id 
         JOIN sales s ON si.sale_id = s.id
         WHERE s.business_id = ? 
         AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY p.id, p.name 
         ORDER BY total_sold DESC 
         LIMIT 5",
        [$business_id]
    );
    
    // 11. Comparaciones de rendimiento - ORIGINAL
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $yesterday_sales = $db->single(
        "SELECT COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) = ?",
        [$business_id, $yesterday]
    );
    
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    $last_month_end = date('Y-m-t', strtotime('-1 month'));
    $last_month_sales = $db->single(
        "SELECT COALESCE(SUM(total_amount), 0) as total 
         FROM sales 
         WHERE business_id = ? AND DATE(created_at) BETWEEN ? AND ?",
        [$business_id, $last_month_start, $last_month_end]
    );
    
    // Nuevos clientes esta semana
    $new_customers_week = $db->single(
        "SELECT COUNT(*) as count 
         FROM customers 
         WHERE business_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 1",
        [$business_id]
    );
    
    // Calcular porcentajes de cambio
    $daily_change = 0;
    if ($yesterday_sales['total'] > 0) {
        $daily_change = (($sales_today['total'] - $yesterday_sales['total']) / $yesterday_sales['total']) * 100;
    } elseif ($sales_today['total'] > 0) {
        $daily_change = 100;
    }
    
    $monthly_change = 0;
    if ($last_month_sales['total'] > 0) {
        $monthly_change = (($sales_month['total'] - $last_month_sales['total']) / $last_month_sales['total']) * 100;
    } elseif ($sales_month['total'] > 0) {
        $monthly_change = 100;
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => [
                'sales_today' => $sales_today,
                'sales_month' => $sales_month,
                'total_products' => $total_products,
                'total_customers' => $total_customers,
                'low_stock_products' => $low_stock_products,
                'pending_debts' => $pending_debts,
                'new_customers_week' => $new_customers_week
            ],
            'comparisons' => [
                'daily_change' => round($daily_change, 1),
                'monthly_change' => round($monthly_change, 1),
                'yesterday_total' => $yesterday_sales['total'],
                'last_month_total' => $last_month_sales['total']
            ],
            'recent_sales' => $recent_sales,
            'upcoming_debts' => $upcoming_debts,
            'sales_chart' => $sales_chart,
            'top_products' => $top_products
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