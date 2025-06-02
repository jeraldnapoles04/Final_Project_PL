<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['token'])) {
    header("Location: login.php");
    exit();
}

$token = $_GET['token'];
$current_time = date('Y-m-d H:i:s');

// Verify token and check if it's not expired
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > ?");
$stmt->bind_param("ss", $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Invalid or expired reset link. Please request a new one.";
} else {
    $user = $result->fetch_assoc();
    
    if (isset($_POST['update'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($stmt->execute()) {
                $success = "Password has been reset successfully! You can now login with your new password.";
            } else {
                $error = "Something went wrong! Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NapZon</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Reset Password</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                    <p class="mt-2">
                        <a href="login.php" class="text-green-700 underline">Click here to login</a>
                    </p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="mb-4 relative">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            New Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline pr-10"
                                placeholder="Enter new password">
                            <span class="absolute right-2 top-2 cursor-pointer">
                                <i class="fas fa-eye-slash toggle-password" data-target="password"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-6 relative">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline pr-10"
                                placeholder="Confirm new password">
                            <span class="absolute right-2 top-2 cursor-pointer">
                                <i class="fas fa-eye-slash toggle-password" data-target="confirm_password"></i>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="submit" name="update"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Update Password
                        </button>
                    </div>
                </form>

                <script>
                document.querySelectorAll('.toggle-password').forEach(icon => {
                    icon.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const input = document.getElementById(targetId);
                        
                        if (input.type === 'password') {
                            input.type = 'text';
                            this.classList.remove('fa-eye-slash');
                            this.classList.add('fa-eye');
                            
                            // Auto-hide after 5 seconds
                            setTimeout(() => {
                                input.type = 'password';
                                this.classList.remove('fa-eye');
                                this.classList.add('fa-eye-slash');
                            }, 5000);
                        } else {
                            input.type = 'password';
                            this.classList.remove('fa-eye');
                            this.classList.add('fa-eye-slash');
                        }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 