<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $user_type = filter_var($_POST['user_type'], FILTER_SANITIZE_STRING);

    try {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, user_type) VALUES (?, ?, ?, ?)");
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$full_name, $email, $hashed_password, $user_type]);
        $user_id = $conn->lastInsertId();
        
        // Create appropriate info record based on user type
        if ($user_type === 'seller') {
            $stmt = $conn->prepare("INSERT INTO sellers_info (user_id, business_name, business_address) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $full_name . "'s Store", '']);
        } else {
            // Create buyer_info record
            $stmt = $conn->prepare("INSERT INTO buyer_info (user_id, shipping_address, phone_number) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, '', '']); // Empty default values
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Registration successful! Please login.']);
    } catch(PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("SELECT id, password, user_type, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            
            if ($user['user_type'] === 'seller') {
                header("Location: seller_dashboard.php");
            } else {
                header("Location: buyer_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } catch(PDOException $e) {
        $error = "Login failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - MarketPlace</title>
        
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
                        },
                        borderRadius: {
                            'none': '0px',
                            'sm': '4px',
                            DEFAULT: '8px',
                            'md': '12px',
                            'lg': '16px',
                            'xl': '20px',
                            '2xl': '24px',
                            '3xl': '32px',
                            'full': '9999px',
                            'button': '8px'
                        }
                    }
                }
            }
        </script>

        <!-- Custom Styles -->
        <style>
            :where([class^="ri-"])::before { 
                content: "\f3c2"; 
            }
            
            body {
                font-family: 'Poppins', sans-serif;
            }
            
            .toggle-checkbox:checked {
                right: 0;
                border-color: #4F46E5;
            }
            
            .store-name {
                font-family: 'Playfair Display', serif;
                letter-spacing: 0.05em;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .toggle-checkbox:checked + .toggle-label {
                background-color: #4F46E5;
            }
            
            input[type="number"]::-webkit-inner-spin-button,
            input[type="number"]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            
            input[type="number"] {
                -moz-appearance: textfield;
            }

            #bgVideo {
                position: fixed;
                top: 0;
                left: 0;
                min-width: 100vw;
                min-height: 100vh;
                width: 100vw;
                height: 100vh;
                object-fit: cover;
                z-index: -1;
                pointer-events: none;
            }

            .video-enhanced {
                filter: brightness(0.5) blur(4px);
            }
        </style>
    </head>

    <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
        <!-- Background Video -->
        <video id="bgVideo" autoplay muted loop playsinline class="fixed top-0 left-0 w-full h-full object-cover z-[-1] video-enhanced">
            <source src="assets/bg.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <!-- Login Form Container -->
        <div class="w-full max-w-md bg-slate-900/60 rounded-lg shadow-2xl overflow-hidden border border-slate-800">
            <div class="p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <img src="assets/NapZon_Logo.png" alt="NapZon Logo" class="mx-auto max-h-16 mb-4">
                    <p class="text-slate-400 mt-2 text-lg font-medium">Sign in to your account</p>
                </div>

                <!-- Login Form -->
                <form method="POST" action="">
                    <?php if ($error): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-4">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-1">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-mail-line text-slate-500"></i>
                            </div>
                            <input type="email" id="email" name="email" class="w-full pl-10 pr-3 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent text-base" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-line text-slate-500"></i>
                            </div>
                            <input type="password" id="password" name="password" class="w-full pl-10 pr-10 py-2 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent placeholder:text-slate-500" placeholder="••••••••" required>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 hover:text-gray-500 focus:outline-none w-5 h-5 flex items-center justify-center">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center mb-6">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-slate-600 focus:ring-slate-600 border-slate-700 rounded bg-slate-800">
                        <label for="remember" class="ml-2 block text-sm text-slate-300">Remember me</label>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <button type="submit" name="login"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Sign In
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="forgot_password.php" class="text-sm text-blue-500 hover:text-blue-700">
                            Forgot Password?
                        </a>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-slate-400">
                        Don't have an account?
                        <a href="#" id="switchToRegisterLink" class="text-slate-300 font-medium hover:text-slate-200">Sign up</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-800 px-8 py-4 border-t border-slate-700">
                <div class="text-center text-xs text-slate-400">
                    By signing in, you agree to our
                    Terms of Service and
                    Privacy Policy
                </div>
            </div>
        </div>

        <!-- Registration Form (Hidden by default) -->
        <div id="registrationForm" class="hidden w-full max-w-md bg-slate-900/60 rounded-lg shadow-2xl overflow-hidden border border-slate-800">
            <div class="p-8">
                <!-- Registration Header -->
                <div class="text-center mb-8">
                    <img src="assets/NapZon_Logo.png" alt="NapZon Logo" class="mx-auto max-h-16 mb-4">
                    <p class="text-slate-400 mt-2 text-lg font-medium">Create your account</p>
                </div>

                <!-- Registration Form -->
                <form id="register-form">
                    <div class="mb-4">
                        <label for="register-fullname" class="block text-sm font-semibold text-slate-300 mb-1">Full Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-user-line text-slate-500"></i>
                            </div>
                            <input type="text" id="register-fullname" name="full_name" class="w-full pl-10 pr-3 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent text-base" placeholder="Jerald Napoles" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="register-email" class="block text-sm font-semibold text-slate-300 mb-1">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-mail-line text-slate-500"></i>
                            </div>
                            <input type="email" id="register-email" name="email" class="w-full pl-10 pr-3 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent text-base" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="register-password" class="block text-sm font-semibold text-slate-300 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-line text-slate-500"></i>
                            </div>
                            <input type="password" id="register-password" name="password" class="w-full pl-10 pr-10 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent placeholder:text-slate-500" placeholder="••••••••" required>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" class="toggle-register-password text-gray-400 hover:text-gray-500 focus:outline-none w-5 h-5 flex items-center justify-center">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="register-confirm-password" class="block text-sm font-semibold text-slate-300 mb-1">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-line text-slate-500"></i>
                            </div>
                            <input type="password" id="register-confirm-password" name="confirm_password" class="w-full pl-10 pr-10 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent placeholder:text-slate-500" placeholder="••••••••" required>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" class="toggle-register-confirm-password text-gray-400 hover:text-gray-500 focus:outline-none w-5 h-5 flex items-center justify-center">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="user-type" class="block text-sm font-semibold text-slate-300 mb-1">Register as</label>
                        <select id="user-type" name="user_type" class="w-full pl-10 pr-3 py-2.5 bg-slate-800 border border-slate-700 rounded text-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-600 focus:border-transparent text-base" required>
                            <option value="">Select user type</option>
                            <option value="buyer">Buyer</option>
                            <option value="seller">Seller</option>
                        </select>
                    </div>

                    <div class="flex items-center mb-6">
                        <input id="terms" name="terms" type="checkbox" class="h-4 w-4 text-slate-600 focus:ring-slate-600 border-slate-700 rounded bg-slate-800" required>
                        <label for="terms" class="ml-2 block text-sm text-slate-300">
                            I agree to the <a href="#" class="text-slate-400 hover:text-slate-300">Terms of Service</a> and <a href="#" class="text-slate-400 hover:text-slate-300">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-slate-700 text-white py-2 px-4 rounded-button hover:bg-slate-600 transition duration-200 font-medium whitespace-nowrap">
                        Create Account
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-slate-400">
                        Already have an account?
                        <a href="#" id="switchToLogin" class="text-slate-300 font-medium hover:text-slate-200">Sign in</a>
                    </p>
                </div>
            </div>
            <!-- Footer -->
            <div class="bg-slate-800 px-8 py-4 border-t border-slate-700">
                <div class="text-center text-xs text-slate-400">
                    By signing in, you agree to our
                    <a href="#" class="text-slate-300 hover:text-slate-200">Terms of Service</a> and
                    <a href="#" class="text-slate-300 hover:text-slate-200">Privacy Policy</a>
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get form containers
                const loginContainer = document.querySelector('.w-full.max-w-md');
                const registrationForm = document.getElementById('registrationForm');
                const switchToRegisterLink = document.getElementById('switchToRegisterLink');
                const switchToLogin = document.getElementById('switchToLogin');

                // Function to show messages
                function showMessage(message, type) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `bg-${type === 'success' ? 'green' : 'red'}-500/20 border border-${type === 'success' ? 'green' : 'red'}-500 text-${type === 'success' ? 'green' : 'red'}-200 px-4 py-3 rounded mb-4`;
                    messageDiv.textContent = message;
                    
                    const form = type === 'success' ? loginContainer : registrationForm;
                    form.insertBefore(messageDiv, form.firstChild);
                    
                    setTimeout(() => messageDiv.remove(), 3000);
                }

                // Function to show login form
                function showLoginForm() {
                    registrationForm.classList.add('hidden');
                    loginContainer.classList.remove('hidden');
                }

                // Toggle between login and registration forms
                if (switchToRegisterLink) {
                    switchToRegisterLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        loginContainer.classList.add('hidden');
                        registrationForm.classList.remove('hidden');
                    });
                }

                if (switchToLogin) {
                    switchToLogin.addEventListener('click', function(e) {
                        e.preventDefault();
                        registrationForm.classList.add('hidden');
                        loginContainer.classList.remove('hidden');
                    });
                }

                // Toggle password visibility for login
                const togglePassword = document.getElementById('togglePassword');
                const passwordInput = document.getElementById('password');

                if (togglePassword && passwordInput) {
                    togglePassword.addEventListener('click', function() {
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                        
                        // Toggle icon
                        const icon = togglePassword.querySelector('i');
                        if (type === 'password') {
                            icon.classList.remove('ri-eye-off-line');
                            icon.classList.add('ri-eye-line');
                        } else {
                            icon.classList.remove('ri-eye-line');
                            icon.classList.add('ri-eye-off-line');
                        }
                    });
                }

                // Toggle password visibility for registration
                const toggleRegisterPassword = document.querySelector('.toggle-register-password');
                const registerPasswordInput = document.getElementById('register-password');
                const toggleRegisterConfirmPassword = document.querySelector('.toggle-register-confirm-password');
                const registerConfirmPasswordInput = document.getElementById('register-confirm-password');

                function setupPasswordToggle(toggleButton, passwordInput) {
                    if (toggleButton && passwordInput) {
                        toggleButton.addEventListener('click', function() {
                            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                            passwordInput.setAttribute('type', type);
                            
                            // Toggle icon
                            const icon = toggleButton.querySelector('i');
                            if (type === 'password') {
                                icon.classList.remove('ri-eye-off-line');
                                icon.classList.add('ri-eye-line');
                            } else {
                                icon.classList.remove('ri-eye-line');
                                icon.classList.add('ri-eye-off-line');
                            }
                        });
                    }
                }

                setupPasswordToggle(toggleRegisterPassword, registerPasswordInput);
                setupPasswordToggle(toggleRegisterConfirmPassword, registerConfirmPasswordInput);

                // Handle registration form submission
                const registerForm = document.getElementById('register-form');
                if (registerForm) {
                    registerForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        // Check if passwords match
                        const password = document.getElementById('register-password').value;
                        const confirmPassword = document.getElementById('register-confirm-password').value;
                        
                        if (password !== confirmPassword) {
                            showMessage('Passwords do not match', 'error');
                            return;
                        }

                        const formData = new FormData(this);
                        formData.append('action', 'register');
                        
                        fetch('login.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showMessage(data.message, 'success');
                                setTimeout(() => {
                                    showLoginForm();
                                }, 2000);
                            } else {
                                showMessage(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showMessage('An error occurred. Please try again.', 'error');
                        });
                    });
                }
            });
        </script>
    </body>
</html>