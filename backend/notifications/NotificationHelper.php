<?php
/**
 * Helper para crear y gestionar notificaciones
 * Archivo: backend/notifications/NotificationHelper.php
 */

class NotificationHelper {
    private $db;
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            // Si no se pasa database, intentar obtenerlo
            if (function_exists('getDB')) {
                $this->db = getDB();
            } else {
                throw new Exception('No se pudo obtener conexión a la base de datos');
            }
        }
    }
    
    /**
     * Crear una nueva notificación
     */
    public function create($business_id, $type, $title, $message, $priority = 'medium', $data = null) {
        try {
            $notification_id = $this->db->insert('notifications', [
                'business_id' => $business_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'data' => $data ? json_encode($data) : null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $notification_id;
        } catch (Exception $e) {
            error_log('Error creating notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notificación de venta realizada
     */
    public function saleCompleted($business_id, $sale_amount, $customer_name = null) {
        $message = $customer_name 
            ? "Venta de S/{$sale_amount} completada para {$customer_name}"
            : "Nueva venta de S/{$sale_amount} registrada";
            
        return $this->create(
            $business_id,
            'sale',
            'Venta Completada',
            $message,
            'medium',
            ['amount' => $sale_amount, 'customer' => $customer_name]
        );
    }
    
    /**
     * Notificación de stock bajo
     */
    public function lowStock($business_id, $product_name, $current_stock, $min_stock) {
        $message = "El producto '{$product_name}' tiene stock bajo: {$current_stock} unidades (mínimo: {$min_stock})";
        
        return $this->create(
            $business_id,
            'stock_low',
            'Stock Bajo',
            $message,
            'high',
            ['product' => $product_name, 'current_stock' => $current_stock, 'min_stock' => $min_stock]
        );
    }
    
    /**
     * Notificación de producto agotado
     */
    public function stockOut($business_id, $product_name) {
        $message = "El producto '{$product_name}' se ha agotado";
        
        return $this->create(
            $business_id,
            'stock_out',
            'Producto Agotado',
            $message,
            'high',
            ['product' => $product_name]
        );
    }
    
    /**
     * Notificación de nuevo producto agregado
     */
    public function productAdded($business_id, $product_name, $user_name) {
        $message = "Nuevo producto '{$product_name}' agregado por {$user_name}";
        
        return $this->create(
            $business_id,
            'product_added',
            'Producto Agregado',
            $message,
            'medium',
            ['product' => $product_name, 'user' => $user_name]
        );
    }
    
    /**
     * Notificación de pago recibido
     */
    public function paymentReceived($business_id, $amount, $customer_name = null) {
        $message = $customer_name
            ? "Pago de S/{$amount} recibido de {$customer_name}"
            : "Pago de S/{$amount} recibido";
            
        return $this->create(
            $business_id,
            'payment',
            'Pago Recibido',
            $message,
            'medium',
            ['amount' => $amount, 'customer' => $customer_name]
        );
    }
    
    /**
     * Notificación de backup completado
     */
    public function backupCompleted($business_id) {
        return $this->create(
            $business_id,
            'system',
            'Backup Completado',
            'El respaldo de datos se completó exitosamente',
            'low'
        );
    }
    
    /**
     * Notificación de error del sistema
     */
    public function systemError($business_id, $error_message) {
        return $this->create(
            $business_id,
            'error',
            'Error del Sistema',
            $error_message,
            'high'
        );
    }
    
    /**
     * Marcar notificación como leída
     */
    public function markAsRead($notification_id) {
        try {
            return $this->db->update(
                'notifications',
                ['is_read' => 1],
                'id = ?',
                [$notification_id]
            );
        } catch (Exception $e) {
            error_log('Error marking notification as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener notificaciones no leídas
     */
    public function getUnread($business_id, $limit = 10) {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM notifications 
                 WHERE business_id = ? AND is_read = 0 
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$business_id, $limit]
            );
        } catch (Exception $e) {
            error_log('Error getting unread notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener todas las notificaciones
     */
    public function getAll($business_id, $limit = 50) {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM notifications 
                 WHERE business_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$business_id, $limit]
            );
        } catch (Exception $e) {
            error_log('Error getting notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpiar notificaciones antiguas
     */
    public function cleanup($business_id, $days_old = 30) {
        try {
            $date_limit = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
            
            return $this->db->query(
                "DELETE FROM notifications 
                 WHERE business_id = ? AND created_at < ?",
                [$business_id, $date_limit]
            );
        } catch (Exception $e) {
            error_log('Error cleaning up notifications: ' . $e->getMessage());
            return false;
        }
    }
}

// Función global para facilitar el uso
if (!function_exists('createNotification')) {
    function createNotification($business_id, $type, $title, $message, $priority = 'medium', $data = null) {
        try {
            $helper = new NotificationHelper();
            return $helper->create($business_id, $type, $title, $message, $priority, $data);
        } catch (Exception $e) {
            error_log('Error in createNotification function: ' . $e->getMessage());
            return false;
        }
    }
}
?>