<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;
$shipping = 100;

try {
    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get cart items
    $stmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.image_url, p.stock 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cart_items)) {
        header("Location: cart.php");
        exit();
    }
    
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    // Handle order submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate form data
        $required_fields = ['full_name', 'email', 'phone', 'address', 'city', 'postal_code', 'payment_method'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $error_message = "Please fill in all required fields: " . implode(", ", $missing_fields);
        } else {
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Create order
                $stmt = $conn->prepare("
                    INSERT INTO orders (
                        user_id, total_amount, shipping_fee, status,
                        shipping_address, shipping_city, shipping_postal_code,
                        payment_method, contact_phone, contact_email
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $user_id,
                    $total,
                    $shipping,
                    'pending',
                    $_POST['address'],
                    $_POST['city'],
                    $_POST['postal_code'],
                    $_POST['payment_method'],
                    $_POST['phone'],
                    $_POST['email']
                ]);
                
                $order_id = $conn->lastInsertId();
                
                // Add order items
                $stmt = $conn->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, quantity, price_at_time,
                        size, color
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($cart_items as $item) {
                    $stmt->execute([
                        $order_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $item['size'],
                        $item['color']
                    ]);
                    
                    // Update product stock
                    $update_stock = $conn->prepare("
                        UPDATE products 
                        SET stock = stock - ? 
                        WHERE id = ? AND stock >= ?
                    ");
                    
                    $result = $update_stock->execute([
                        $item['quantity'],
                        $item['product_id'],
                        $item['quantity']
                    ]);
                    
                    if (!$result) {
                        throw new Exception("Not enough stock for " . $item['name']);
                    }
                }
                
                // Clear cart
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to order confirmation
                header("Location: order_confirmation.php?order_id=" . $order_id);
                exit();
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error_message = $e->getMessage();
            }
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Error processing checkout: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Checkout</h1>
            <a href="cart.php" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cart
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-400 px-4 py-3 rounded-md mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Shipping Information -->
            <div>
                <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-6">Shipping Information</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Full Name</label>
                            <input type="text" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                            <input type="tel" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Address</label>
                            <textarea name="address" 
                                      rows="3"
                                      class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">City</label>
                                <input type="text" 
                                       name="city" 
                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Postal Code</label>
                                <input type="text" 
                                       name="postal_code" 
                                       value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700 mt-6">
                    <h2 class="text-xl font-semibold text-white mb-6">Payment Method</h2>
                    <div class="space-y-4">
                        <label class="flex items-center space-x-3">
                            <input type="radio" name="payment_method" value="cod" checked
                                   class="form-radio text-blue-500 focus:ring-blue-500">
                            <span class="text-white">Cash on Delivery</span>
                        </label>
                        <label class="flex items-center space-x-3">
                            <input type="radio" name="payment_method" value="gcash"
                                   class="form-radio text-blue-500 focus:ring-blue-500">
                            <span class="text-white">GCash</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div>
                <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-6">Order Summary</h2>
                    
                    <!-- Order Items -->
                    <div class="space-y-4 mb-6">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex items-center py-4 border-b border-slate-700 last:border-0">
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
                                    <p class="text-blue-400 font-semibold">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Totals -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Subtotal</span>
                            <span class="text-white">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Shipping</span>
                            <span class="text-white">₱<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="border-t border-slate-700 pt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-semibold text-white">Total</span>
                                <span class="text-lg font-semibold text-blue-400">₱<?php echo number_format($total + $shipping, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-md hover:bg-blue-700 transition">
                        Place Order
                    </button>
                </div>
            </div>
        </form>
    </div>
</body>
</html> 