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
        
        if (!$cart_id) {
            throw new Exception('Invalid request');
        }
        
        // Verify cart item belongs to user
        $stmt = $conn->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Cart item not found');
        }
        
        // Remove item
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->execute([$cart_id]);
        
        $response['success'] = true;
        $response['message'] = 'Item removed successfully';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} 