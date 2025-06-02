<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current seller information
try {
    $stmt = $conn->prepare("
        SELECT s.*, u.email, u.full_name, u.phone_number 
        FROM sellers_info s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $seller_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Failed to fetch seller information: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            // Start transaction
            $conn->beginTransaction();

            // Update users table
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = ?, phone_number = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['full_name'],
                $_POST['phone_number'],
                $user_id
            ]);

            // Update sellers_info table
            $stmt = $conn->prepare("
                UPDATE sellers_info 
                SET business_name = ?,
                    business_address = ?,
                    bank_account_name = ?,
                    bank_account_number = ?,
                    bank_name = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['business_name'],
                $_POST['business_address'],
                $_POST['bank_account_name'],
                $_POST['bank_account_number'],
                $_POST['bank_name'],
                $user_id
            ]);

            $conn->commit();
            $success_message = "Profile updated successfully!";
            
            // Refresh seller info
            $stmt = $conn->prepare("
                SELECT s.*, u.email, u.full_name, u.phone_number 
                FROM sellers_info s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $seller_info = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $conn->rollBack();
            $error_message = "Failed to update profile: " . $e->getMessage();
        }
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect!";
                }
            } catch(PDOException $e) {
                $error_message = "Failed to change password: " . $e->getMessage();
            }
        }
    }

    // Handle notification preferences
    if (isset($_POST['update_notifications'])) {
        try {
            $notifications = array(
                'order_updates' => isset($_POST['order_updates']) ? 1 : 0,
                'low_stock' => isset($_POST['low_stock']) ? 1 : 0,
                'new_messages' => isset($_POST['new_messages']) ? 1 : 0,
                'promotional_emails' => isset($_POST['promotional_emails']) ? 1 : 0
            );
            
            $notification_json = json_encode($notifications);
            
            $stmt = $conn->prepare("
                UPDATE sellers_info 
                SET notification_preferences = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$notification_json, $user_id]);
            $success_message = "Notification preferences updated successfully!";

            // Refresh seller info
            $stmt = $conn->prepare("
                SELECT s.*, u.email, u.full_name, u.phone_number 
                FROM sellers_info s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $seller_info = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $error_message = "Failed to update notification preferences: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NapZon Seller</title>
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
    </style>
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-slate-800 text-slate-300 flex flex-col">
            <div class="p-6">
                <h1 class="text-2xl font-bold text-gradient-blue">NapZon Seller</h1>
                <p class="text-sm text-slate-400 mt-1"><?php echo htmlspecialchars($seller_info['business_name']); ?></p>
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
                <a href="seller_analytics.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-chart-bar w-6 mr-3"></i>
                    <span>Analytics</span>
                </a>
                <a href="seller_settings.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
                    <i class="fas fa-cog w-6 mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <div class="flex items-center">
                     <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white text-lg font-semibold">
                        <?php echo strtoupper(substr($seller_info['full_name'], 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($seller_info['full_name']); ?></p>
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
                <?php if ($success_message): ?>
                    <div class="bg-green-500 text-white px-4 py-3 rounded mb-4">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="bg-red-500 text-white px-4 py-3 rounded mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Settings -->
                <div class="card-bg rounded-lg p-6 mb-6 shadow-lg border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-4">Profile Settings</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($seller_info['full_name']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Email</label>
                                <input type="email" value="<?php echo htmlspecialchars($seller_info['email']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-slate-400" disabled>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Phone Number</label>
                                <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($seller_info['phone_number']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Business Name</label>
                                <input type="text" name="business_name" value="<?php echo htmlspecialchars($seller_info['business_name']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">Business Address</label>
                            <textarea name="business_address" rows="2" 
                                      class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($seller_info['business_address']); ?></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Bank Account Name</label>
                                <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($seller_info['bank_account_name']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Bank Account Number</label>
                                <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($seller_info['bank_account_number']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Bank Name</label>
                                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($seller_info['bank_name']); ?>" 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="card-bg rounded-lg p-6 mb-6 shadow-lg border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-4">Change Password</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Current Password</label>
                                <input type="password" name="current_password" required 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">New Password</label>
                                <input type="password" name="new_password" required 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">Confirm New Password</label>
                                <input type="password" name="confirm_password" required 
                                       class="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notification Preferences -->
                <div class="card-bg rounded-lg p-6 mb-6 shadow-lg border border-slate-700">
                    <h2 class="text-xl font-semibold text-white mb-4">Notification Preferences</h2>
                    <form method="POST" class="space-y-4">
                        <?php 
                        $notifications = json_decode($seller_info['notification_preferences'] ?? '{}', true);
                        $notifications = is_array($notifications) ? $notifications : array();
                        ?>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="order_updates" value="1" 
                                       <?php echo isset($notifications['order_updates']) && $notifications['order_updates'] ? 'checked' : ''; ?>
                                       class="form-checkbox h-5 w-5 text-blue-600">
                                <span class="ml-2 text-slate-300">Order Updates</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="low_stock" value="1" 
                                       <?php echo isset($notifications['low_stock']) && $notifications['low_stock'] ? 'checked' : ''; ?>
                                       class="form-checkbox h-5 w-5 text-blue-600">
                                <span class="ml-2 text-slate-300">Low Stock Alerts</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="new_messages" value="1" 
                                       <?php echo isset($notifications['new_messages']) && $notifications['new_messages'] ? 'checked' : ''; ?>
                                       class="form-checkbox h-5 w-5 text-blue-600">
                                <span class="ml-2 text-slate-300">New Messages</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="promotional_emails" value="1" 
                                       <?php echo isset($notifications['promotional_emails']) && $notifications['promotional_emails'] ? 'checked' : ''; ?>
                                       class="form-checkbox h-5 w-5 text-blue-600">
                                <span class="ml-2 text-slate-300">Promotional Emails</span>
                            </label>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_notifications" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Save Preferences
                            </button>
                        </div>
                    </form>
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