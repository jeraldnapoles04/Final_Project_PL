<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

// Get total sales
try {
    $stmt = $conn->query("
        SELECT COUNT(*) as total_orders,
               SUM(oi.quantity * oi.price) as total_revenue,
               COUNT(DISTINCT o.user_id) as unique_customers
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status != 'cancelled'
    ");
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch overall statistics: " . $e->getMessage();
}

// Get sales by category
try {
    $stmt = $conn->query("
        SELECT p.category,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity) as items_sold,
               SUM(oi.quantity * oi.price) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.category
        ORDER BY revenue DESC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch category statistics: " . $e->getMessage();
}

// Get monthly sales for the past 12 months
try {
    $stmt = $conn->query("
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity * oi.price) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status != 'cancelled'
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch monthly sales: " . $e->getMessage();
}

// Get top selling products
try {
    $stmt = $conn->query("
        SELECT p.name, p.brand,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity) as quantity_sold,
               SUM(oi.quantity * oi.price) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.id
        ORDER BY quantity_sold DESC
        LIMIT 5
    ");
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
    <title>Analytics - NapZon Shoes</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-900 text-white">
            <div class="p-4">
                <h1 class="text-2xl font-bold">NapZon Admin</h1>
            </div>
            <nav class="mt-8">
                <a href="seller_dashboard.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_products.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white">
                    <i class="fas fa-shoe-prints w-6"></i>
                    <span>Products</span>
                </a>
                <a href="manage_orders.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white">
                    <i class="fas fa-shopping-bag w-6"></i>
                    <span>Orders</span>
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-3 bg-gray-800 text-white">
                    <i class="fas fa-chart-bar w-6"></i>
                    <span>Analytics</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Bar -->
            <div class="bg-white shadow-sm">
                <div class="px-8 py-4">
                    <h2 class="text-xl font-semibold">Analytics Dashboard</h2>
                </div>
            </div>

            <div class="p-8">
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Overall Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="text-gray-500 text-sm mb-2">Total Revenue</div>
                        <div class="text-2xl font-bold">₱<?php echo number_format($overall_stats['total_revenue'], 2); ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="text-gray-500 text-sm mb-2">Total Orders</div>
                        <div class="text-2xl font-bold"><?php echo number_format($overall_stats['total_orders']); ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="text-gray-500 text-sm mb-2">Unique Customers</div>
                        <div class="text-2xl font-bold"><?php echo number_format($overall_stats['unique_customers']); ?></div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Monthly Sales Chart -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4">Monthly Sales</h3>
                        <canvas id="monthlySalesChart"></canvas>
                    </div>

                    <!-- Category Distribution Chart -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4">Sales by Category</h3>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Top Products Table -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Top Selling Products</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['brand']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($product['order_count']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($product['quantity_sold']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            ₱<?php echo number_format($product['revenue'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Sales Chart
        const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
        new Chart(monthlySalesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_sales, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_sales, 'revenue')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($category_stats, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_stats, 'revenue')); ?>,
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(139, 92, 246)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 