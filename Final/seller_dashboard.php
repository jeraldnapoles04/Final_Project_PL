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
    $stmt = $conn->prepare("SELECT business_name, business_address FROM sellers_info WHERE user_id = ?");
    $stmt->execute([$seller_id]);
    $business_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_name = isset($business_info['business_name']) ? htmlspecialchars($business_info['business_name']) : 'Your Business';

    // Get total products for the seller (Requires products table to have seller_id)
    // NOTE: Your current database schema does not link products directly to sellers.
    // This query assumes a 'seller_id' column exists in the 'products' table.
    // To accurately count products per seller, the database schema needs to be updated.
    $stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products WHERE seller_id = ?");
    $stmt->execute([$seller_id]);
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

    // Get total orders for the seller (Orders containing products from this seller)
    // NOTE: This query assumes a 'seller_id' column exists in the 'products' table.
    // To accurately count orders per seller, the database schema needs to be updated.
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT o.id) as total_orders FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ?");
    $stmt->execute([$seller_id]);
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];

    // Get active customers for the seller (Customers who ordered from this seller in last 30 days)
    // NOTE: This query assumes a 'seller_id' column exists in the 'products' table.
    // To accurately count active customers per seller, the database schema needs to be updated.
     $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT u.id) as active_customers 
        FROM users u 
        JOIN orders o ON u.id = o.user_id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? 
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$seller_id]);
    $active_customers = $stmt->fetch(PDO::FETCH_ASSOC)['active_customers'];

    // Get recent orders for the seller (Orders containing products from this seller)
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT o.*, u.full_name,
                   GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products,
                   SUM(oi.quantity * oi.price) as total_amount
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.seller_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$seller_id]);
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $recent_orders = [];
        error_log("Error fetching recent orders: " . $e->getMessage());
    }

} catch(PDOException $e) {
    $business_name = 'NapZon Shoes Store';
    $total_products = 0;
    $total_orders = 0;
    $active_customers = 0;
    $recent_orders = [];
    error_log("Error fetching seller dashboard data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - NapZon</title>
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
         .order-status-pending {
            background-color: #fde68a; /* yellow-200 */
            color: #a16207; /* yellow-800 */
        }
        .order-status-processing {
            background-color: #bfdbfe; /* blue-200 */
            color: #1e40af; /* blue-800 */
        }
        .order-status-shipped {
             background-color: #d8b4fe; /* purple-200 */
            color: #6b21a8; /* purple-800 */
        }
        .order-status-delivered {
            background-color: #a7f3d0; /* green-200 */
            color: #047857; /* green-800 */
        }
        .order-status-cancelled {
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
                <p class="text-sm text-slate-400 mt-1"><?php echo htmlspecialchars($business_name); ?></p>
            </div>
            <nav class="mt-10 flex-1 px-4 space-y-2">
                <a href="seller_dashboard.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
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
                    <h2 class="text-2xl font-bold text-white">Dashboard Overview</h2>
                    <p class="text-slate-400 text-sm">Welcome back! Here's what's happening today.</p>
                </div>
                 <div class="flex items-center space-x-4">
                     <div class="relative">
                        <input type="text" placeholder="    Search..." class="w-64 bg-slate-700 border border-slate-600 rounded-md py-2 px-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-slate-400">
                         <i class="ri-search-line absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                     </div>
                    <div class="relative">
                        <i id="notificationsButton" class="ri-notification-line text-slate-400 text-xl cursor-pointer hover:text-white transition"></i>
                        <span id="notificationCount" class="absolute top-0 right-0 block h-2 w-2 rounded-full ring-2 ring-white bg-red-500 hidden">0</span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue Card (Placeholder) -->
                 <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-600 rounded-full">
                            <i class="fas fa-dollar-sign text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-slate-400 text-sm">Total Revenue (Placeholder)</h3>
                            <p class="text-3xl font-bold text-white">₱24,343<span class="text-green-400 text-lg ml-2">+12.5%</span></p>
                            <p class="text-xs text-slate-500 mt-1">from last month</p>
                        </div>
                    </div>
                </div>
                <!-- Total Orders Card -->
                <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-600 rounded-full">
                            <i class="fas fa-shopping-bag text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-slate-400 text-sm">Total Orders</h3>
                            <p class="text-3xl font-bold text-white"><?php echo $total_orders; ?><span class="text-blue-400 text-lg ml-2">+8.2%</span></p>
                            <p class="text-xs text-slate-500 mt-1">from last month (Placeholder)</p>
                        </div>
                    </div>
                </div>
                <!-- Total Products Card -->
                <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-600 rounded-full">
                            <i class="fas fa-shoe-prints text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-slate-400 text-sm">Total Products</h3>
                            <p class="text-3xl font-bold text-white"><?php echo $total_products; ?><span class="text-yellow-400 text-lg ml-2">+3.1%</span></p>
                            <p class="text-xs text-slate-500 mt-1">from last month (Placeholder)</p>
                        </div>
                    </div>
                </div>
                 <!-- Active Customers Card -->
                <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-600 rounded-full">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-slate-400 text-sm">Active Customers</h3>
                            <p class="text-3xl font-bold text-white"><?php echo $active_customers; ?><span class="text-purple-400 text-lg ml-2">+15.7%</span></p>
                            <p class="text-xs text-slate-500 mt-1">from last month (Placeholder)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Overview Chart (Placeholder) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Revenue Overview</h3>
                    <div class="h-70 bg-slate-700 rounded-md flex items-center justify-center overflow-hidden">
                        <img src="assets/Chart.png" alt="Revenue Chart" class="w-full h-full object-cover">
                    </div>
                </div>

                <!-- Order Status Distribution (Placeholder) -->
                 <div class="rounded-lg shadow-md p-6 card-bg border border-slate-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Order Status Distribution</h3>
                    <div class="h-50 bg-slate-700 rounded-md flex items-center justify-center overflow-hidden">
                        <img src="assets/Chart4.jpg" alt="Order Status Chart" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card-bg rounded-lg shadow-md p-6 border border-slate-700">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Orders</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-slate-700">
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php if (empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-slate-400 py-4">No recent orders found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr class="hover:bg-slate-800">
                                        <td class="px-6 py-4 text-sm text-slate-300">#<?php echo $order['id']; ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-300"><?php echo htmlspecialchars($order['full_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-300"><?php echo htmlspecialchars($order['products']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-300">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php echo match($order['status']) {
                                                    'pending' => 'order-status-pending',
                                                    'processing' => 'order-status-processing',
                                                    'shipped' => 'order-status-shipped',
                                                    'delivered' => 'order-status-delivered',
                                                    'cancelled' => 'order-status-cancelled',
                                                    default => 'bg-gray-600 text-gray-200'
                                                }; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <form method="POST" action="manage_orders.php" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" 
                                                        class="bg-slate-700 border border-slate-600 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-blue-500">
                                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center hidden">
        <div class="bg-slate-800 rounded-lg p-6 w-full max-w-md shadow-xl border border-slate-700">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-white">Notifications</h3>
                <div class="flex items-center space-x-2">
                    <button id="markAllReadButton" class="text-sm text-blue-400 hover:text-blue-300">
                        Mark all as read
                    </button>
                    <button id="closeNotifications" class="text-slate-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="notificationsList" class="space-y-4 max-h-96 overflow-y-auto">
                <!-- Notifications will be dynamically inserted here -->
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

        document.addEventListener('DOMContentLoaded', function() {
            const notificationsButton = document.getElementById('notificationsButton');
            const notificationsModal = document.getElementById('notificationsModal');
            const closeNotifications = document.getElementById('closeNotifications');
            const notificationsList = document.getElementById('notificationsList');
            const notificationCount = document.getElementById('notificationCount');
            const markAllReadButton = document.getElementById('markAllReadButton');

            function updateNotificationCount() {
                fetch('notifications_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_unread_count'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        notificationCount.textContent = data.count;
                        notificationCount.classList.remove('hidden');
                    } else {
                        notificationCount.classList.add('hidden');
                    }
                });
            }

            function loadNotifications() {
                fetch('notifications_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_notifications'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationsList.innerHTML = '';
                        if (data.notifications.length === 0) {
                            notificationsList.innerHTML = '<p class="text-slate-400 text-center py-4">No notifications</p>';
                            return;
                        }
                        data.notifications.forEach(notification => {
                            const notificationElement = document.createElement('div');
                            notificationElement.className = `p-4 rounded-lg ${notification.is_read ? 'bg-slate-700' : 'bg-slate-600'} relative group`;
                            notificationElement.innerHTML = `
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-white">${notification.title}</h4>
                                        <p class="text-sm text-slate-300">${notification.message}</p>
                                        <p class="text-xs text-slate-400 mt-1">${new Date(notification.created_at).toLocaleString()}</p>
                                    </div>
                                    <button class="delete-notification text-slate-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition"
                                            data-id="${notification.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `;
                            if (!notification.is_read) {
                                const markReadButton = document.createElement('button');
                                markReadButton.className = 'text-xs text-blue-400 hover:text-blue-300 mt-2';
                                markReadButton.textContent = 'Mark as read';
                                markReadButton.onclick = () => markNotificationRead(notification.id);
                                notificationElement.appendChild(markReadButton);
                            }
                            notificationsList.appendChild(notificationElement);
                        });

                        // Add event listeners for delete buttons
                        document.querySelectorAll('.delete-notification').forEach(button => {
                            button.addEventListener('click', function() {
                                deleteNotification(this.dataset.id);
                            });
                        });
                    }
                });
            }

            function markNotificationRead(id) {
                fetch('notifications_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_read&notification_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                        updateNotificationCount();
                    }
                });
            }

            function deleteNotification(id) {
                fetch('notifications_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&notification_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                        updateNotificationCount();
                    }
                });
            }

            function markAllNotificationsRead() {
                fetch('notifications_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                        updateNotificationCount();
                    }
                });
            }

            // Event Listeners
            notificationsButton.addEventListener('click', function() {
                notificationsModal.classList.remove('hidden');
                loadNotifications();
            });

            closeNotifications.addEventListener('click', function() {
                notificationsModal.classList.add('hidden');
            });

            markAllReadButton.addEventListener('click', markAllNotificationsRead);

            notificationsModal.addEventListener('click', function(e) {
                if (e.target === notificationsModal) {
                    notificationsModal.classList.add('hidden');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    notificationsModal.classList.add('hidden');
                }
            });

            // Initial load
            updateNotificationCount();
            setInterval(updateNotificationCount, 30000); // Update count every 30 seconds
        });
    </script>
</body>
</html> 