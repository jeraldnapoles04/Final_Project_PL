<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$wishlist_items = [];

try {
    $stmt = $conn->prepare("
        SELECT w.*, p.name, p.price, p.image_url, p.stock, p.sizes, p.colors
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching wishlist: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">My Wishlist</h1>
            <a href="buyer_dashboard.php" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
            </a>
        </div>

        <?php if (empty($wishlist_items)): ?>
            <div class="text-center py-16">
                <i class="fas fa-heart text-6xl text-slate-600 mb-4"></i>
                <h2 class="text-2xl font-semibold text-white mb-2">Your wishlist is empty</h2>
                <p class="text-slate-400 mb-8">Add items to your wishlist and they will appear here</p>
                <a href="buyer_dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="bg-slate-800 rounded-lg shadow-md border border-slate-700 overflow-hidden">
                        <!-- Product Image -->
                        <div class="relative">
                            <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-slate-700 flex items-center justify-center">
                                    <i class="fas fa-image text-4xl text-slate-500"></i>
                                </div>
                            <?php endif; ?>
                            <button class="remove-from-wishlist absolute top-2 right-2 text-red-400 hover:text-red-300 bg-slate-800/80 rounded-full w-8 h-8 flex items-center justify-center"
                                    data-product-id="<?php echo $item['product_id']; ?>">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Product Info -->
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-white mb-2"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-blue-400 font-bold mb-4">₱<?php echo number_format($item['price'], 2); ?></p>
                            
                            <?php if ($item['stock'] > 0): ?>
                                <form class="space-y-3 add-to-cart-form" data-product-id="<?php echo $item['product_id']; ?>">
                                    <!-- Sizes -->
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (explode(',', $item['sizes']) as $size): ?>
                                            <label class="relative">
                                                <input type="radio" name="size" value="<?php echo htmlspecialchars($size); ?>" class="absolute opacity-0">
                                                <span class="px-2 py-1 border border-slate-600 rounded-md text-sm hover:border-blue-500 transition cursor-pointer">
                                                    <?php echo htmlspecialchars($size); ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Colors -->
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (explode(',', $item['colors']) as $color): ?>
                                            <label class="relative">
                                                <input type="radio" name="color" value="<?php echo htmlspecialchars($color); ?>" class="absolute opacity-0">
                                                <span class="block w-6 h-6 rounded-full border-2 border-transparent hover:border-blue-500 transition cursor-pointer"
                                                      style="background-color: <?php echo htmlspecialchars(strtolower($color)); ?>">
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                        Add to Cart
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-red-400 text-center py-2">Out of Stock</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle add to cart
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const productId = form.dataset.productId;
                const size = form.querySelector('input[name="size"]:checked')?.value;
                const color = form.querySelector('input[name="color"]:checked')?.value;
                
                if (!size || !color) {
                    alert('Please select both size and color');
                    return;
                }
                
                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&size=${size}&color=${color}&quantity=1`
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        alert('Added to cart successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to add to cart');
                    }
                } catch (error) {
                    alert('Error adding to cart');
                }
            });
        });

        // Handle remove from wishlist
        document.querySelectorAll('.remove-from-wishlist').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Remove this item from your wishlist?')) return;
                
                const productId = this.dataset.productId;
                try {
                    const response = await fetch('remove_from_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}`
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
    </script>
</body>
</html> 