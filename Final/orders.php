<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = [];

try {
    // Get all orders with their items
    $stmt = $conn->prepare("
        SELECT o.*,
               GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items,
               COUNT(DISTINCT oi.id) as total_items,
               GROUP_CONCAT(DISTINCT p.image_url) as product_images
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">My Orders</h1>
            <a href="buyer_dashboard.php" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="text-center py-16">
                <i class="fas fa-shopping-bag text-6xl text-slate-600 mb-4"></i>
                <h2 class="text-2xl font-semibold text-white mb-2">No orders yet</h2>
                <p class="text-slate-400 mb-8">Start shopping to see your orders here</p>
                <a href="buyer_dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                            <div>
                                <div class="flex items-center gap-4 mb-2">
                                    <h3 class="text-xl font-semibold text-white">Order #<?php echo $order['id']; ?></h3>
                                    <span class="inline-block px-3 py-1 text-sm rounded-full 
                                        <?php echo $order['status'] === 'completed' ? 'bg-green-500/10 text-green-400' : 
                                               ($order['status'] === 'pending' ? 'bg-yellow-500/10 text-yellow-400' : 
                                                'bg-blue-500/10 text-blue-400'); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                <p class="text-slate-400">
                                    Placed on <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0 text-right">
                                <p class="text-lg font-semibold text-blue-400">
                                    ₱<?php echo number_format($order['total_amount'] + $order['shipping_fee'], 2); ?>
                                </p>
                                <p class="text-sm text-slate-400">
                                    <?php echo $order['total_items']; ?> items
                                </p>
                            </div>
                        </div>

                        <!-- Order Details -->
                        <div class="border-t border-slate-700 pt-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-white mb-2">Shipping Information</h4>
                                    <p class="text-slate-400">
                                        <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                        <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                                        <?php echo htmlspecialchars($order['shipping_postal_code']); ?><br>
                                        Phone: <?php echo htmlspecialchars($order['contact_phone']); ?><br>
                                        Email: <?php echo htmlspecialchars($order['contact_email']); ?>
                                    </p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-white mb-2">Payment Details</h4>
                                    <p class="text-slate-400">
                                        Method: <?php echo ucfirst($order['payment_method']); ?><br>
                                        Subtotal: ₱<?php echo number_format($order['total_amount'], 2); ?><br>
                                        Shipping Fee: ₱<?php echo number_format($order['shipping_fee'], 2); ?><br>
                                        <span class="text-white font-medium">
                                            Total: ₱<?php echo number_format($order['total_amount'] + $order['shipping_fee'], 2); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div class="mt-6">
                                <h4 class="font-medium text-white mb-4">Order Items</h4>
                                <p class="text-slate-400">
                                    <?php echo htmlspecialchars($order['items']); ?>
                                </p>
                            </div>

                            <?php if ($order['status'] === 'pending'): ?>
                                <div class="mt-6 flex justify-end">
                                    <button class="text-red-400 hover:text-red-300 cancel-order" 
                                            data-order-id="<?php echo $order['id']; ?>">
                                        <i class="fas fa-times mr-2"></i> Cancel Order
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle order cancellation
        document.querySelectorAll('.cancel-order').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to cancel this order?')) return;
                
                const orderId = this.dataset.orderId;
                try {
                    const response = await fetch('cancel_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `order_id=${orderId}`
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel order');
                    }
                } catch (error) {
                    alert('Error canceling order');
                }
            });
        });
    </script>
</body>
</html> 