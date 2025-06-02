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
        $size = isset($_POST['size']) ? $_POST['size'] : '';
        $color = isset($_POST['color']) ? $_POST['color'] : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if (!$product_id || !$size || !$color) {
            throw new Exception('Please select size and color');
        }
        
        // Check if product exists and has stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if ($product['stock'] < $quantity) {
            throw new Exception('Not enough stock available');
        }
        
        // Check if already in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ? AND color = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id, $size, $color]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Update quantity
            $new_quantity = $existing_item['quantity'] + $quantity;
            if ($new_quantity > $product['stock']) {
                throw new Exception('Cannot add more of this item');
            }
            
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing_item['id']]);
        } else {
            // Add new item to cart
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id, $quantity, $size, $color]);
        }
        
        $response['success'] = true;
        $response['message'] = 'Added to cart successfully';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
} 