<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    die(json_encode(['success' => false, 'message' => 'Please log in first']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            throw new Exception('Invalid request');
        }
        
        // Check if product exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Product not found');
        }
        
        // Add to wishlist (will fail silently if already exists due to UNIQUE constraint)
        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        
        $response['success'] = true;
        $response['message'] = 'Added to wishlist successfully';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} 