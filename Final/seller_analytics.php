<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

// Get seller's full name from session, initialize if not set
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Seller';
$seller_id = $_SESSION['user_id'];

// Get seller's business information
try {
    $stmt = $conn->prepare("SELECT business_name FROM sellers_info WHERE user_id = ?");
    $stmt->execute([$seller_id]);
    $business_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch business info: " . $e->getMessage();
}

// Get total sales for this seller
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_orders,
               SUM(oi.quantity * oi.price_at_time) as total_revenue,
               COUNT(DISTINCT o.user_id) as unique_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.status != 'cancelled'
    ");
    $stmt->execute([$seller_id]);
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch overall statistics: " . $e->getMessage();
}

// Get sales by category for this seller
try {
    $stmt = $conn->prepare("
        SELECT p.category,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity) as items_sold,
               SUM(oi.quantity * oi.price_at_time) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.seller_id = ? AND o.status != 'cancelled'
        GROUP BY p.category
        ORDER BY revenue DESC
    ");
    $stmt->execute([$seller_id]);
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch category statistics: " . $e->getMessage();
}

// Get monthly sales for the past 12 months
try {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity * oi.price_at_time) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND o.status != 'cancelled'
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute([$seller_id]);
    $monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch monthly sales: " . $e->getMessage();
}

// Get top selling products
$top_products = []; // Initialize $top_products
try {
    $stmt = $conn->prepare("
        SELECT p.name, p.brand,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity) as quantity_sold,
               SUM(oi.quantity * oi.price_at_time) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE p.seller_id = ? AND o.status != 'cancelled'
        GROUP BY p.id
        ORDER BY quantity_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$seller_id]);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch top products: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - NapZon Seller</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a;
        }
        .sidebar-active-link {
            background-color: #3b82f6;
            color: #ffffff;
        }
        .text-gradient-blue {
            background-image: linear-gradient(to right, #93c5fd, #e2e8f0, #93c5fd);
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
        }
        .card-bg {
            background-color: #1e293b;
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
                <a href="manage_products.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-shoe-prints w-6 mr-3"></i>
                    <span>Products</span>
                </a>
                <a href="manage_orders.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-shopping-bag w-6 mr-3"></i>
                    <span>Orders</span>
                </a>
                <a href="seller_analytics.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
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
        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-500 text-white px-4 py-3 rounded mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Overall Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-600 rounded-full">
                                <i class="fas fa-dollar-sign text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-slate-400 text-sm">Total Revenue</h3>
                                <p class="text-3xl font-bold text-white">₱<?php echo number_format($overall_stats['total_revenue'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-600 rounded-full">
                                <i class="fas fa-shopping-bag text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-slate-400 text-sm">Total Orders</h3>
                                <p class="text-3xl font-bold text-white"><?php echo number_format($overall_stats['total_orders'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-600 rounded-full">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-slate-400 text-sm">Unique Customers</h3>
                                <p class="text-3xl font-bold text-white"><?php echo number_format($overall_stats['unique_customers'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Monthly Sales Chart -->
                    <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                        <h3 class="text-lg font-semibold text-white mb-4">Monthly Sales</h3>
                        <canvas id="monthlySalesChart"></canvas>
                    </div>

                    <!-- Category Distribution Chart -->
                    <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                        <h3 class="text-lg font-semibold text-white mb-4">Sales by Category</h3>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Top Products Table -->
                <div class="card-bg rounded-lg shadow-lg p-6 border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Top Selling Products</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Quantity Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                                <?php foreach ($top_products as $product): ?>
                                    <tr class="hover:bg-slate-800">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="text-sm text-slate-400"><?php echo htmlspecialchars($product['brand']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-300">
                                            <?php echo number_format($product['order_count']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-300">
                                            <?php echo number_format($product['quantity_sold']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-300">
                                            ₱<?php echo number_format($product['revenue'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_products)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-slate-400">No products found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Sales Chart
        const monthlySalesData = <?php echo json_encode($monthly_sales); ?>;
        const monthlyLabels = monthlySalesData.map(item => {
            const [year, month] = item.month.split('-');
            return new Date(year, month - 1).toLocaleDateString('default', { month: 'short', year: 'numeric' });
        });
        const monthlyRevenue = monthlySalesData.map(item => item.revenue);

        new Chart(document.getElementById('monthlySalesChart'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Revenue',
                    data: monthlyRevenue,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryData = <?php echo json_encode($category_stats); ?>;
        const categoryLabels = categoryData.map(item => item.category);
        const categoryRevenue = categoryData.map(item => item.revenue);

        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryRevenue,
                    backgroundColor: [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b',
                        '#10b981'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });

        // Logout Modal functionality
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
</body>
</html> 