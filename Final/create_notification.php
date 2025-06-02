<?php
require_once 'config.php';

function createNotification($user_id, $type, $title, $message) {
    global $conn;
    try {
        // Check user's notification preferences
        $stmt = $conn->prepare("
            SELECT notification_preferences 
            FROM sellers_info 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $preferences = json_decode($stmt->fetch(PDO::FETCH_ASSOC)['notification_preferences'] ?? '{}', true);
        
        // Only create notification if the user has enabled this type
        if (!isset($preferences[$type]) || $preferences[$type]) {
            $stmt = $conn->prepare("CALL create_notification(?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $title, $message]);
            return true;
        }
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
    return false;
}

// Example usage:
// Order notification
function notifyNewOrder($seller_id, $order_id) {
    createNotification(
        $seller_id,
        'order_updates',
        'New Order Received',
        "Order #$order_id has been placed. Check the orders page for details."
    );
}

// Low stock notification
function notifyLowStock($seller_id, $product_name, $current_stock) {
    createNotification(
        $seller_id,
        'low_stock',
        'Low Stock Alert',
        "Product '$product_name' is running low on stock (Current stock: $current_stock)."
    );
}

// New message notification
function notifyNewMessage($seller_id, $from_name) {
    createNotification(
        $seller_id,
        'new_messages',
        'New Message Received',
        "You have received a new message from $from_name."
    );
}

// Promotional notification
function notifyPromotion($seller_id, $title, $message) {
    createNotification(
        $seller_id,
        'promotional_emails',
        $title,
        $message
    );
} 