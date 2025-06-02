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

try {
    $stmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.image_url, p.stock 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} catch(PDOException $e) {
    $error_message = "Error fetching cart items: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Shopping Cart</h1>
            <a href="buyer_dashboard.php" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
            </a>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="text-center py-16">
                <i class="fas fa-shopping-cart text-6xl text-slate-600 mb-4"></i>
                <h2 class="text-2xl font-semibold text-white mb-2">Your cart is empty</h2>
                <p class="text-slate-400 mb-8">Add some products to your cart and they will appear here</p>
                <a href="buyer_dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex items-center py-4 border-b border-slate-700 last:border-0">
                                <!-- Product Image -->
                                <div class="w-24 h-24 flex-shrink-0">
                                    <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="w-full h-full object-cover rounded-md">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-slate-700 rounded-md flex items-center justify-center">
                                            <i class="fas fa-image text-2xl text-slate-500"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Details -->
                                <div class="flex-1 ml-6">
                                    <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-sm text-slate-400 mb-2">
                                        Size: <?php echo htmlspecialchars($item['size']); ?> | 
                                        Color: <?php echo htmlspecialchars($item['color']); ?>
                                    </p>
                                    <div class="flex items-center">
                                        <div class="flex items-center mr-6">
                                            <button class="quantity-btn minus bg-slate-700 text-white w-8 h-8 rounded-l-md hover:bg-slate-600 transition"
                                                    data-cart-id="<?php echo $item['id']; ?>"
                                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo $item['stock']; ?>"
                                                   class="w-16 h-8 text-center bg-slate-700 text-white border-x border-slate-600"
                                                   data-cart-id="<?php echo $item['id']; ?>">
                                            <button class="quantity-btn plus bg-slate-700 text-white w-8 h-8 rounded-r-md hover:bg-slate-600 transition"
                                                    data-cart-id="<?php echo $item['id']; ?>"
                                                    <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <p class="text-blue-400 font-semibold">₱<?php echo number_format($item['price'], 2); ?></p>
                                    </div>
                                </div>

                                <!-- Remove Button -->
                                <button class="remove-item ml-6 text-red-400 hover:text-red-300 transition"
                                        data-cart-id="<?php echo $item['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                        <h2 class="text-xl font-semibold text-white mb-4">Order Summary</h2>
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Subtotal</span>
                                <span class="text-white">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Shipping</span>
                                <span class="text-white">₱100.00</span>
                            </div>
                            <div class="border-t border-slate-700 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-lg font-semibold text-white">Total</span>
                                    <span class="text-lg font-semibold text-blue-400">₱<?php echo number_format($total + 100, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <button id="checkout-btn" class="w-full bg-blue-600 text-white py-3 rounded-md hover:bg-blue-700 transition">
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle quantity changes
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const cartId = this.dataset.cartId;
                const input = document.querySelector(`input[data-cart-id="${cartId}"]`);
                const currentQty = parseInt(input.value);
                const isPlus = this.classList.contains('plus');
                
                const newQty = isPlus ? currentQty + 1 : currentQty - 1;
                if (newQty < 1 || newQty > parseInt(input.max)) return;
                
                try {
                    const response = await fetch('update_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `cart_id=${cartId}&quantity=${newQty}`
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        input.value = newQty;
                        location.reload(); // Refresh to update totals
                    } else {
                        alert(data.message || 'Failed to update quantity');
                    }
                } catch (error) {
                    alert('Error updating quantity');
                }
            });
        });

        // Handle remove item
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to remove this item?')) return;
                
                const cartId = this.dataset.cartId;
                try {
                    const response = await fetch('remove_from_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `cart_id=${cartId}`
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to remove item');
                    }
                } catch (error) {
                    alert('Error removing item');
                }
            });
        });

        // Handle checkout
        document.getElementById('checkout-btn')?.addEventListener('click', function() {
            window.location.href = 'checkout.php';
        });
    </script>
</body>
</html> 