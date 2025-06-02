<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = null;
$recent_orders = [];

try {
    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get recent orders
    $stmt = $conn->prepare("
        SELECT o.*, 
               COUNT(oi.id) as total_items,
               GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $postal_code = trim($_POST['postal_code']);
        
        if (empty($full_name) || empty($email)) {
            throw new Exception('Name and email are required');
        }
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET full_name = ?, 
                email = ?, 
                phone_number = ?, 
                address = ?, 
                city = ?, 
                postal_code = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $full_name, 
            $email, 
            $phone, 
            $address, 
            $city, 
            $postal_code, 
            $user_id
        ]);
        
        $_SESSION['full_name'] = $full_name;
        $success_message = 'Profile updated successfully';
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
} catch(Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">My Profile</h1>
            <a href="buyer_dashboard.php" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="bg-green-500/10 border border-green-500 text-green-400 px-4 py-3 rounded-md mb-6">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-400 px-4 py-3 rounded-md mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Information -->
            <div class="lg:col-span-2">
                <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-6">Profile Information</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Full Name</label>
                                <input type="text" 
                                       name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                       required
                                       class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                                <input type="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       required
                                       class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                            <input type="tel" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Address</label>
                            <textarea name="address" 
                                      rows="3"
                                      class="w-full bg-slate-700 border border-slate-600 rounded-md px-4 py-2 text-white focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="lg:col-span-1">
                <div class="bg-slate-800 rounded-lg shadow-md p-6 border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-6">Recent Orders</h2>
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-slate-400 text-center py-4">No orders yet</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="border-b border-slate-700 last:border-0 pb-4 last:pb-0">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h4 class="text-white font-medium">Order #<?php echo $order['id']; ?></h4>
                                            <p class="text-sm text-slate-400">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </p>
                                        </div>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full 
                                                     <?php echo $order['status'] === 'completed' ? 'bg-green-500/10 text-green-400' : 
                                                            ($order['status'] === 'pending' ? 'bg-yellow-500/10 text-yellow-400' : 
                                                             'bg-blue-500/10 text-blue-400'); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-slate-400 mb-2">
                                        <?php echo $order['total_items']; ?> items: 
                                        <?php echo htmlspecialchars($order['product_names']); ?>
                                    </p>
                                    <p class="text-blue-400 font-semibold">
                                        ₱<?php echo number_format($order['total_amount'] + $order['shipping_fee'], 2); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6 text-center">
                            <a href="orders.php" class="text-blue-400 hover:text-blue-300">
                                View All Orders <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 