<?php
session_start();
require_once 'config.php';

if (isset($_POST['reset'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Generate unique reset token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token in database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        
        if ($stmt->execute([$token, $expiry, $email])) {
            // Prepare reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            $success = "Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.";
            $showGmailButton = true;
        } else {
            $error = "Something went wrong! Please try again.";
        }
    } else {
        $error = "No account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NapZon</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dosis:wght@700&family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1E293B',
                        secondary: '#64748B'
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .store-name {
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.05em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <!-- Background Video -->
    <video id="bgVideo" autoplay muted loop playsinline class="fixed top-0 left-0 w-full h-full object-cover z-[-1] opacity-50">
        <source src="assets/bg.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="w-full max-w-md bg-slate-900/60 rounded-lg shadow-2xl overflow-hidden border border-slate-800">
        <div class="p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="font-['Dosis'] text-5xl font-bold bg-gradient-to-r from-indigo-200 via-slate-100 to-indigo-300 text-transparent bg-clip-text mb-2 tracking-wide">
                    NapZon Online
                </h1>
                <p class="text-slate-400 mt-2 text-lg font-medium">Reset Your Password</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-200 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                    <?php if (isset($showGmailButton)): ?>
                        <div class="mt-4 flex flex-col items-center gap-3">
                            <a href="https://mail.google.com" target="_blank" 
                               class="inline-flex items-center gap-2 bg-white text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                                <i class="ri-mail-line"></i>
                                Open Gmail
                            </a>
                            <p class="text-sm text-slate-300">Click the button above to check your email</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-6">
                    <label for="email" class="block text-sm font-semibold text-slate-300 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-mail-line text-slate-500"></i>
                        </div>
                        <input type="email" name="email" id="email" required
                            class="w-full pl-10 pr-3 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent text-base"
                            placeholder="Enter your email">
                    </div>
                </div>

                <div class="mb-6">
                    <button type="submit" name="reset"
                        class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                        Send Reset Instructions
                    </button>
                </div>

                <div class="text-center">
                    <p class="text-sm text-slate-400">
                        Remember your password?
                        <a href="login.php" class="text-slate-300 font-medium hover:text-slate-200">Sign in here</a>
                    </p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="bg-slate-800 px-8 py-4 border-t border-slate-700">
            <div class="text-center text-xs text-slate-400">
                By using our service, you agree to our
                <a href="#" class="text-slate-300 hover:text-slate-200">Terms of Service</a> and
                <a href="#" class="text-slate-300 hover:text-slate-200">Privacy Policy</a>
            </div>
        </div>
    </div>
</body>
</html> 