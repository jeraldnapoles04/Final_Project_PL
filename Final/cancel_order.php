<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    die(json_encode(['success' => false, 'message' => 'Please log in first']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            throw new Exception('Invalid request');
        }
        
        // Verify order belongs to user and is pending
        $stmt = $conn->prepare("
            SELECT id, status 
            FROM orders 
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Order not found or cannot be cancelled');
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Get order items to restore stock
            $stmt = $conn->prepare("
                SELECT product_id, quantity 
                FROM order_items 
                WHERE order_id = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();
            
            // Restore stock for each item
            $stmt = $conn->prepare("
                UPDATE products 
                SET stock = stock + ? 
                WHERE id = ?
            ");
            
            foreach ($items as $item) {
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Update order status
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Order cancelled successfully';
            
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} 