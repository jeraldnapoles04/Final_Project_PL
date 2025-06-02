<?php
session_start();
require_once 'config.php';

// Add this at the top after session_start() and require_once
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Handle order status updates or approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $response = ['success' => false, 'message' => ''];

    try {
        // First verify that this order belongs to products sold by this seller
        $stmt = $conn->prepare("
            SELECT DISTINCT o.*, u.full_name, u.email
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            JOIN users u ON o.user_id = u.id
            WHERE o.id = :order_id AND p.seller_id = :seller_id
        ");
        $stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id', $seller_id, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Start transaction
            $conn->beginTransaction();

            $new_status = null;
            $notification_message = null;

            // Handle status dropdown update
            if (isset($_POST['status'])) {
                $new_status = $_POST['status'];
                $notification_message = "Your order #{$order_id} has been updated to " . ucfirst($new_status);
            }

            // Handle approval buttons (Accept/Decline)
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
                if ($action === 'accept' && $order['status'] === 'pending') {
                    $new_status = 'processing';
                    $notification_message = "Your order #{$order_id} has been accepted and is now processing.";
                } elseif ($action === 'decline' && $order['status'] === 'pending') {
                    $new_status = 'cancelled';
                    $notification_message = "Your order #{$order_id} has been declined.";
                }
            }

            // Only update if a new status is determined
            if ($new_status !== null && $new_status !== $order['status']) {
                // Update the order status
                $update_stmt = $conn->prepare("
                    UPDATE orders 
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = :order_id
                ");
                $update_stmt->bindValue(':status', $new_status, PDO::PARAM_STR);
                $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
                $result = $update_stmt->execute();

                if (!$result) {
                    throw new PDOException("Failed to update order status");
                }

                // Create notification for the buyer if message is set
                if ($notification_message !== null) {
                    $notify_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, message, created_at)
                        VALUES (:user_id, 'order_status', :message, CURRENT_TIMESTAMP)
                    ");
                    $notify_stmt->bindValue(':user_id', $order['user_id'], PDO::PARAM_INT);
                    $notify_stmt->bindValue(':message', $notification_message, PDO::PARAM_STR);
                    $notify_stmt->execute();
                }

                // Commit transaction
                $conn->commit();

                // Set success response
                $response = [
                    'success' => true,
                    'message' => 'Order status updated successfully',
                    'new_status' => $new_status
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'No status change needed'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'You don\'t have permission to update this order.'
            ];
        }
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error updating order status: " . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Failed to update order status: ' . $e->getMessage()
        ];
    }

    // If this is an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // For regular form submissions, set session message and redirect
    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }

    // Redirect back to the same page to prevent form resubmission
    $redirect_url = $_SERVER['PHP_SELF'];
    $query_params = $_GET;

    if (isset($new_status)) {
        $query_params['status'] = $new_status;
    }

    $query_string = http_build_query($query_params);
    if (!empty($query_string)) {
        $redirect_url .= '?' . $query_string;
    }

    header("Location: " . $redirect_url);
    exit();
}

// Display messages at the top of the page
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get filter parameters - move this near the top after session checks
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Debug filters
error_log("Status Filter: " . $status_filter);
error_log("Date Filter: " . $date_filter);
error_log("Search: " . $search);

// Build the base query for orders
$base_query = "
    SELECT DISTINCT o.*, u.full_name, u.email,
           GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products,
           SUM(oi.quantity * oi.price_at_time) as total_amount
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = :seller_id
";

$params = ['seller_id' => $seller_id];

// Add status filter
if (!empty($status_filter)) {
    $base_query .= " AND o.status = :status";
    $params['status'] = $status_filter;
}

// Add date filter
if (!empty($date_filter)) {
    $base_query .= " AND DATE(o.created_at) = :date";
    $params['date'] = $date_filter;
}

// Add search filter
if (!empty($search)) {
    $base_query .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR CAST(o.id AS CHAR) LIKE :search)";
    $params['search'] = "%$search%";
}

$base_query .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Fetch orders
try {
    $stmt = $conn->prepare($base_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
}

// Get order statistics
try {
    $stats_query = "
        SELECT 
            o.status,
            COUNT(DISTINCT o.id) as count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = :seller_id
        GROUP BY o.status
    ";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bindValue(":seller_id", $seller_id);
    $stmt->execute();
    
    // Initialize all status counts to 0
    $order_stats = [
        'pending_count' => 0,
        'processing_count' => 0,
        'shipped_count' => 0,
        'delivered_count' => 0,
        'cancelled_count' => 0
    ];
    
    // Fill in actual counts
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_key = $row['status'] . '_count';
        if (array_key_exists($status_key, $order_stats)) {
            $order_stats[$status_key] = $row['count'];
        }
    }
} catch(PDOException $e) {
    error_log("Error fetching order statistics: " . $e->getMessage());
    $order_stats = [
        'pending_count' => 0,
        'processing_count' => 0,
        'shipped_count' => 0,
        'delivered_count' => 0,
        'cancelled_count' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - NapZon Seller</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .status-pending {
            background-color: #fde68a;
            color: #92400e;
        }
        .status-processing {
            background-color: #93c5fd;
            color: #1e40af;
        }
        .status-shipped {
            background-color: #c4b5fd;
            color: #5b21b6;
        }
        .status-delivered {
            background-color: #86efac;
            color: #166534;
        }
        .status-cancelled {
            background-color: #fca5a5;
            color: #991b1b;
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
                <a href="manage_orders.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
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
        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <?php if (isset($success_message)): ?>
                    <div class="bg-green-500 text-white px-4 py-3 rounded mb-4">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-500 text-white px-4 py-3 rounded mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Order Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                    <a href="?status=pending<?php echo $date_filter ? '&date='.$date_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" 
                       class="card-bg rounded-lg p-4 text-center hover:bg-slate-700 transition-colors <?php echo $status_filter === 'pending' ? 'ring-2 ring-yellow-500' : ''; ?>">
                        <div class="text-yellow-400 text-2xl mb-1"><?php echo $order_stats['pending_count']; ?></div>
                        <div class="text-sm text-slate-400">Pending</div>
                    </a>
                    <a href="?status=processing<?php echo $date_filter ? '&date='.$date_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" 
                       class="card-bg rounded-lg p-4 text-center hover:bg-slate-700 transition-colors <?php echo $status_filter === 'processing' ? 'ring-2 ring-blue-500' : ''; ?>">
                        <div class="text-blue-400 text-2xl mb-1"><?php echo $order_stats['processing_count']; ?></div>
                        <div class="text-sm text-slate-400">Processing</div>
                    </a>
                    <a href="?status=shipped<?php echo $date_filter ? '&date='.$date_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" 
                       class="card-bg rounded-lg p-4 text-center hover:bg-slate-700 transition-colors <?php echo $status_filter === 'shipped' ? 'ring-2 ring-purple-500' : ''; ?>">
                        <div class="text-purple-400 text-2xl mb-1"><?php echo $order_stats['shipped_count']; ?></div>
                        <div class="text-sm text-slate-400">Shipped</div>
                    </a>
                    <a href="?status=delivered<?php echo $date_filter ? '&date='.$date_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" 
                       class="card-bg rounded-lg p-4 text-center hover:bg-slate-700 transition-colors <?php echo $status_filter === 'delivered' ? 'ring-2 ring-green-500' : ''; ?>">
                        <div class="text-green-400 text-2xl mb-1"><?php echo $order_stats['delivered_count']; ?></div>
                        <div class="text-sm text-slate-400">Delivered</div>
                    </a>
                    <a href="?status=cancelled<?php echo $date_filter ? '&date='.$date_filter : ''; ?><?php echo $search ? '&search='.$search : ''; ?>" 
                       class="card-bg rounded-lg p-4 text-center hover:bg-slate-700 transition-colors <?php echo $status_filter === 'cancelled' ? 'ring-2 ring-red-500' : ''; ?>">
                        <div class="text-red-400 text-2xl mb-1"><?php echo $order_stats['cancelled_count']; ?></div>
                        <div class="text-sm text-slate-400">Cancelled</div>
                    </a>
                </div>

                <!-- Add a clear filters link if any filter is active -->
                <?php if ($status_filter || $date_filter || $search): ?>
                    <div class="mb-4">
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-blue-400 hover:text-blue-300">
                            <i class="fas fa-times-circle mr-2"></i>Clear all filters
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card-bg rounded-lg p-6 mb-8">
                    <form method="GET" class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-sm text-slate-400 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                                   placeholder="Search by order ID, customer name, or email">
                        </div>
                        <div class="w-48">
                            <label class="block text-sm text-slate-400 mb-2">Status</label>
                            <select name="status" onchange="this.form.submit()" 
                                    class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="w-48">
                            <label class="block text-sm text-slate-400 mb-2">Date</label>
                            <input type="date" name="date" value="<?php echo $date_filter; ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <?php if ($status_filter || $date_filter || $search): ?>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="bg-slate-600 text-white px-4 py-2 rounded hover:bg-slate-700">
                                    <i class="fas fa-times mr-2"></i>Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Orders Table -->
                <div class="card-bg rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-700">
                        <thead class="bg-slate-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Approval</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-slate-400">No orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-slate-800">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-white">#<?php echo $order['id']; ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-white"><?php echo htmlspecialchars($order['full_name']); ?></div>
                                            <div class="text-sm text-slate-400"><?php echo htmlspecialchars($order['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-slate-300"><?php echo htmlspecialchars($order['products']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-white">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-300">
                                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                            </div>
                                            <div class="text-xs text-slate-400">
                                                <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <span class="approval-action cursor-pointer px-2 py-1 text-xs font-semibold rounded-full bg-green-500/10 text-green-400 mr-2" data-order-id="<?php echo $order['id']; ?>" data-action="accept">Accept</span>
                                                <span class="approval-action cursor-pointer px-2 py-1 text-xs font-semibold rounded-full bg-red-500/10 text-red-400" data-order-id="<?php echo $order['id']; ?>" data-action="decline">Decline</span>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" class="inline-block" id="statusForm_<?php echo $order['id']; ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="status" 
                                                        onchange="updateOrderStatus(this, <?php echo $order['id']; ?>)" 
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

    <script>
    // Add this before the existing updateOrderStatus function
    document.addEventListener('DOMContentLoaded', function() {

        // Logout Modal functionality
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

        // Enable debug mode for testing
        <?php $_SESSION['debug'] = true; ?>
        
        // Handle status filter clicks
        const statusLinks = document.querySelectorAll('.card-bg[href*="status="]');
        statusLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                
                // Get the status from the href
                const newStatus = this.href.split('status=')[1];
                
                // Update or remove status parameter
                if (urlParams.get('status') === newStatus) {
                    urlParams.delete('status'); // Toggle off if already selected
                } else {
                    urlParams.set('status', newStatus);
                }
                
                // Keep other filters (date and search)
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.location.href = newUrl;
            });
        });
        
        // Add active class to current filter
        const currentStatus = new URLSearchParams(window.location.search).get('status');
        if (currentStatus) {
            const activeCard = document.querySelector(`[href*="status=${currentStatus}"]`);
            if (activeCard) {
                activeCard.classList.add('ring-2', `ring-${getStatusColor(currentStatus)}-500`);
            }
        }
    });

    function getStatusColor(status) {
        const colors = {
            'pending': 'yellow',
            'processing': 'blue',
            'shipped': 'purple',
            'delivered': 'green',
            'cancelled': 'red'
        };
        return colors[status] || 'gray';
    }

    // New JavaScript for Approval Actions
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded fired. Setting up approval button listeners.');
        const approvalButtons = document.querySelectorAll('.approval-action');

        approvalButtons.forEach(button => {
            console.log('Adding click listener to button:', button);
            button.addEventListener('click', function(e) {
                console.log('Approval button clicked!', this);
                e.preventDefault();
                const orderId = this.dataset.orderId;
                const action = this.dataset.action;

                // Determine the new status based on the action
                let newStatus = '';
                if (action === 'accept') {
                    newStatus = 'processing';
                } else if (action === 'decline') {
                    newStatus = 'cancelled';
                }

                if (newStatus) {
                    // Show a temporary indicator (optional)
                    button.textContent = '...'; // Indicate loading
                    button.style.opacity = '0.5';

                    // Send an asynchronous request to update the status
                    const formData = new FormData();
                    formData.append('order_id', orderId);
                    formData.append('action', action);

                    fetch('manage_orders.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Update the status badge
                            const row = button.closest('tr');
                            const statusCell = row.querySelector('td:nth-child(5) span');
                            if (statusCell) {
                                // Remove old status classes
                                const allStatusClasses = ['status-pending', 'status-processing', 'status-shipped', 'status-delivered', 'status-cancelled'];
                                allStatusClasses.forEach(cls => {
                                    statusCell.classList.remove(cls);
                                    const color = getStatusColor(cls.replace('status-', ''));
                                    if (color !== 'gray') statusCell.classList.remove(`status-${color}-500`);
                                });

                                // Add new status class and text
                                const newStatusColor = getStatusColor(newStatus);
                                statusCell.classList.add(`status-${newStatus}`, `status-${newStatusColor}-500`);
                                statusCell.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                            }

                            // Replace the approval buttons with a placeholder
                            button.parentElement.innerHTML = '<span class="text-slate-400">-</span>';

                            // Update the counts at the top
                            const pendingCount = document.querySelector('a[href*="status=pending"] .text-yellow-400');
                            const processingCount = document.querySelector('a[href*="status=processing"] .text-blue-400');
                            const cancelledCount = document.querySelector('a[href*="status=cancelled"] .text-red-400');

                            if (pendingCount && processingCount && cancelledCount) {
                                const currentPending = parseInt(pendingCount.textContent);
                                const currentProcessing = parseInt(processingCount.textContent);
                                const currentCancelled = parseInt(cancelledCount.textContent);

                                if (action === 'accept') {
                                    pendingCount.textContent = currentPending - 1;
                                    processingCount.textContent = currentProcessing + 1;
                                } else if (action === 'decline') {
                                    pendingCount.textContent = currentPending - 1;
                                    cancelledCount.textContent = currentCancelled + 1;
                                }
                            }

                            // Update the dropdown to reflect the new status
                            const statusSelect = row.querySelector('select[name="status"]');
                            if (statusSelect) {
                                statusSelect.value = newStatus;
                            }

                            console.log(`Order ${orderId} status updated successfully to ${newStatus}`);
                        } else {
                            throw new Error(data.message || 'Failed to update order status');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        button.textContent = (action === 'accept') ? 'Accept' : 'Decline'; // Revert text
                        button.style.opacity = '1'; // Revert opacity
                        alert(error.message || 'Failed to update order status. Please try again.');
                    });
                }
            });
        });
    });

    function getStatusColor(status) {
        const colors = {
            'pending': 'yellow',
            'processing': 'blue',
            'shipped': 'purple',
            'delivered': 'green',
            'cancelled': 'red'
        };
        return colors[status] || 'gray';
    }

    // Existing updateOrderStatus function (for the dropdown, still causes full reload)
    function updateOrderStatus(selectElement, orderId) {
        const form = selectElement.closest('form');
        // Submit the form (handles full page reload and filtering)
        form.submit();
    }

    // Show success/error messages with fade out
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('.bg-green-500, .bg-red-500');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 3000);
        });
    });

    // Add this to show any errors in the console
    window.onerror = function(msg, url, line) {
        console.error(`Error: ${msg}\nURL: ${url}\nLine: ${line}`);
        return false;
    };
    </script>

    <!-- Replace the debug information section at the bottom -->
    <?php if (isset($_SESSION['debug'])): ?>
        <div class="fixed bottom-4 right-4 bg-slate-800 p-4 rounded-lg shadow-lg max-w-lg overflow-auto" style="max-height: 300px; z-index: 50;">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-white font-bold">Current Filters</h3>
                <button onclick="this.parentElement.parentElement.style.display='none'" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="text-slate-300 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div class="font-semibold">Active Status Filter:</div>
                    <div><?php echo $status_filter ? ucfirst($status_filter) : 'All Orders'; ?></div>
                    
                    <div class="font-semibold">Orders Shown:</div>
                    <div><?php echo count($orders); ?> orders</div>
                    
                    <div class="font-semibold">Order Counts:</div>
                    <div>
                        Pending: <?php echo $order_stats['pending_count']; ?><br>
                        Processing: <?php echo $order_stats['processing_count']; ?><br>
                        Shipped: <?php echo $order_stats['shipped_count']; ?><br>
                        Delivered: <?php echo $order_stats['delivered_count']; ?><br>
                        Cancelled: <?php echo $order_stats['cancelled_count']; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html> 