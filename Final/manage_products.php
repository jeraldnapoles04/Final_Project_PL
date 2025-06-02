<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Seller';

// Handle product deletion
if (isset($_POST['delete_product'])) {
    $product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
    try {
        // Ensure the product belongs to the logged-in seller before deleting
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?"); // Assumes seller_id exists in products
        $stmt->execute([$product_id, $seller_id]);
        if ($stmt->rowCount() > 0) {
            $success_message = "Product deleted successfully!";
        } else {
            $error_message = "Product not found or does not belong to you.";
        }
    } catch(PDOException $e) {
        $error_message = "Failed to delete product: " . $e->getMessage();
    }
}

// Fetch products for the logged-in seller
// NOTE: This query assumes a 'seller_id' column exists in the 'products' table.
// To accurately manage products per seller, the database schema needs to be updated.
try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC"); // Assumes seller_id exists in products
    $stmt->execute([$seller_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $products = [];
    $error_message = "Failed to fetch products: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - NapZon</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a; /* slate-900 */
        }
        .sidebar-active-link {
            background-color: #3b82f6; /* blue-500 */
            color: #ffffff; /* white */
        }
        .card-bg {
             background-color: #1e293b; /* slate-800 */
        }
         .text-gradient-blue {
            background-image: linear-gradient(to right, #93c5fd, #e2e8f0, #93c5fd);
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
        }
         table th, table td {
            padding: 1rem;
            text-align: left;
        }
        table th {
            font-size: 0.75rem;
            font-weight: 500;
            color: #94a3b8; /* slate-400 */
            text-transform: uppercase;
        }
         table tbody tr:nth-child(odd) {
            background-color: #1e293b; /* slate-800 */
        }
         table tbody tr:nth-child(even) {
            background-color: #0f172a; /* slate-900 */
        }
         table tbody tr:last-child td {
            border-bottom: none;
        }
        .status-stock {
            background-color: #a7f3d0; /* green-200 */
            color: #047857; /* green-800 */
        }
         .status-out-of-stock {
            background-color: #fecaca; /* red-200 */
            color: #b91c1c; /* red-800 */
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-slate-800 text-slate-300 flex flex-col">
            <div class="p-6">
                 <h1 class="text-2xl font-bold text-gradient-blue">NapZon Seller</h1>
                <p class="text-sm text-slate-400 mt-1"><?php echo htmlspecialchars($business_info['business_name'] ?? 'Your Business'); ?></p>
            </div>
            <nav class="mt-10 flex-1 px-4 space-y-2">
                <a href="seller_dashboard.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-tachometer-alt w-6 mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_products.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
                    <i class="fas fa-shoe-prints w-6 mr-3"></i>
                    <span>Products</span>
                </a>
                <a href="manage_orders.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-shopping-bag w-6 mr-3"></i>
                    <span>Orders</span>
                </a>
                <a href="seller_analytics.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-chart-bar w-6 mr-3"></i>
                    <span>Analytics</span>
                </a>
                 <a href="seller_settings.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-cog w-6 mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>
             <div class="p-4 border-t border-slate-700">
                <div class="flex items-center">
                     <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white text-lg font-semibold">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="text-xs text-slate-400">Seller</p>
                    </div>
                </div>
                 <button id="logoutButton" class="mt-4 w-full text-left text-slate-400 hover:text-white flex items-center">
                    <i class="fas fa-power-off w-6 mr-3"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto bg-slate-900 p-8">
            <!-- Top Bar -->
            <div class="bg-slate-800/50 backdrop-filter backdrop-blur-lg rounded-md shadow-md mb-6 px-6 py-4 flex justify-between items-center border border-slate-700">
                <div>
                    <h2 class="text-2xl font-bold text-white">Manage Products</h2>
                    <p class="text-slate-400 text-sm">View, add, edit, and delete your products.</p>
                </div>
                 <a href="add_product.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Product
                </a>
            </div>

            <!-- Products List -->
            <div class="rounded-lg shadow-md card-bg border border-slate-700">
                <div class="p-6">
                    <?php if (isset($success_message)): ?>
                        <div class="bg-green-500/20 border border-green-500 text-green-200 px-4 py-3 rounded mb-4">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-4">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-slate-700">
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Brand</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php if (!empty($products)): ?>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="w-16 h-16 object-cover rounded">
                                        </td>
                                        <td class="text-slate-300">
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="text-sm text-slate-400"><?php echo htmlspecialchars($product['category']); ?></div>
                                        </td>
                                        <td class="text-slate-300"><?php echo htmlspecialchars($product['brand']); ?></td>
                                        <td class="text-slate-300">₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php echo $product['stock'] > 0 ? 'status-stock' : 'status-out-of-stock'; ?>">
                                                <?php echo $product['stock']; ?> units
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex space-x-3">
                                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                                   class="text-blue-400 hover:text-blue-300 transition">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" name="delete_product" class="text-red-400 hover:text-red-300 transition">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-slate-400 py-4">No products found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden">
        <div class="bg-slate-800 rounded-lg p-6 w-full max-w-sm shadow-xl border border-slate-700">
            <div class="text-center mb-4">
                <i class="fas fa-power-off text-slate-400 text-3xl mb-3"></i>
                <h3 class="text-xl font-semibold text-white">Confirm Logout</h3>
            </div>
            <p class="text-slate-300 text-center mb-6">Are you sure you want to log out?</p>
            <div class="flex justify-center space-x-4">
                <button id="cancelLogout" class="px-4 py-2 bg-slate-600 text-white rounded-md hover:bg-slate-700 transition">
                    Cancel
                </button>
                <a href="logout.php" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
                    Logout
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logoutButton = document.getElementById('logoutButton');
            const logoutModal = document.getElementById('logoutModal');
            const cancelLogout = document.getElementById('cancelLogout');

            if(logoutButton) {
                logoutButton.addEventListener('click', function() {
                    logoutModal.classList.remove('hidden');
                });
            }

            if(cancelLogout) {
                cancelLogout.addEventListener('click', function() {
                    logoutModal.classList.add('hidden');
                });
            }

            // Close modal if clicking outside
            if(logoutModal) {
                 logoutModal.addEventListener('click', function(e) {
                    if (e.target === logoutModal) {
                        logoutModal.classList.add('hidden');
                    }
                });
            }
             // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    logoutModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html> 