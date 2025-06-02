<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: buyer_dashboard.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];
$order = null;
$order_items = [];

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: buyer_dashboard.php");
        exit();
    }
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, p.name, p.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Error fetching order details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Success Message -->
            <div class="text-center mb-12">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-500/10 text-green-400 rounded-full mb-4">
                    <i class="fas fa-check text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Order Placed Successfully!</h1>
                <p class="text-slate-400">Thank you for shopping with NapZon</p>
            </div>

            <!-- Order Details -->
            <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700 mb-6">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-white mb-2">Order #<?php echo $order_id; ?></h2>
                        <p class="text-slate-400">Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-slate-400 mb-1">Order Status</p>
                        <span class="inline-block px-3 py-1 bg-yellow-500/10 text-yellow-400 rounded-full text-sm">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Shipping Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-lg font-medium text-white mb-3">Shipping Address</h3>
                        <p class="text-slate-400">
                            <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                            <?php echo htmlspecialchars($order['shipping_city']); ?><br>
                            <?php echo htmlspecialchars($order['shipping_postal_code']); ?>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-white mb-3">Contact Information</h3>
                        <p class="text-slate-400">
                            Phone: <?php echo htmlspecialchars($order['contact_phone']); ?><br>
                            Email: <?php echo htmlspecialchars($order['contact_email']); ?>
                        </p>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="border-t border-slate-700 pt-6">
                    <h3 class="text-lg font-medium text-white mb-4">Order Items</h3>
                    <div class="space-y-4">
                        <?php foreach ($order_items as $item): ?>
                            <div class="flex items-center">
                                <div class="w-16 h-16 flex-shrink-0">
                                    <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="w-full h-full object-cover rounded-md">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-700 rounded-md flex items-center justify-center">
                                            <i class="fas fa-image text-xl text-slate-500"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 ml-4">
                                    <h4 class="text-white font-medium"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p class="text-sm text-slate-400">
                                        Size: <?php echo htmlspecialchars($item['size']); ?> | 
                                        Color: <?php echo htmlspecialchars($item['color']); ?> |
                                        Qty: <?php echo $item['quantity']; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-blue-400 font-semibold">₱<?php echo number_format($item['price_at_time'] * $item['quantity'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="border-t border-slate-700 mt-6 pt-6">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Subtotal</span>
                            <span class="text-white">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Shipping</span>
                            <span class="text-white">₱<?php echo number_format($order['shipping_fee'], 2); ?></span>
                        </div>
                        <div class="border-t border-slate-700 pt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-semibold text-white">Total</span>
                                <span class="text-lg font-semibold text-blue-400">₱<?php echo number_format($order['total_amount'] + $order['shipping_fee'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-center space-x-4">
                <a href="orders.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition">
                    View All Orders
                </a>
                <a href="buyer_dashboard.php" class="bg-slate-700 text-white px-6 py-3 rounded-md hover:bg-slate-600 transition">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</body>
</html> 