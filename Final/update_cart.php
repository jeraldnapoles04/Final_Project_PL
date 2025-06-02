<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    die(json_encode(['success' => false, 'message' => 'Please log in first']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        
        if (!$cart_id || $quantity < 1) {
            throw new Exception('Invalid request');
        }
        
        // Verify cart item belongs to user
        $stmt = $conn->prepare("
            SELECT c.*, p.stock 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
        $cart_item = $stmt->fetch();
        
        if (!$cart_item) {
            throw new Exception('Cart item not found');
        }
        
        if ($quantity > $cart_item['stock']) {
            throw new Exception('Not enough stock available');
        }
        
        // Update quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $cart_id]);
        
        $response['success'] = true;
        $response['message'] = 'Cart updated successfully';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} 