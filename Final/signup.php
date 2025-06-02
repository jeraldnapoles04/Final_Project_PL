<?php
session_start();
require_once 'config/database.php';

if (isset($_POST['signup'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check if email already exists
        $check_email = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (full_name, email, password, user_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $user_type);
            
            if ($stmt->execute()) {
                if ($user_type == 'seller') {
                    $user_id = $stmt->insert_id;
                    $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
                    $business_address = mysqli_real_escape_string($conn, $_POST['business_address']);
                    
                    $sql = "INSERT INTO sellers_info (user_id, business_name, business_address) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $user_id, $business_name, $business_address);
                    $stmt->execute();
                }
                
                $_SESSION['success'] = "Registration successful! Please login.";
                header("Location: login.php");
                exit();
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
    <title>Sign Up - NapZon</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .password-toggle {
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        .password-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        .password-visible {
            color: #3B82F6;
        }
        .password-container {
            position: relative;
        }
        .password-strength {
            height: 3px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .strength-weak { background-color: #EF4444; width: 33%; }
        .strength-medium { background-color: #F59E0B; width: 66%; }
        .strength-strong { background-color: #10B981; width: 100%; }
        .password-requirements {
            font-size: 0.75rem;
            color: #6B7280;
            margin-top: 0.5rem;
        }
        .requirement-met {
            color: #10B981;
        }
        .requirement-unmet {
            color: #6B7280;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Create your NapZon Account</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="signupForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="user_type">
                        Account Type
                    </label>
                    <select name="user_type" id="user_type" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        onchange="toggleBusinessFields()">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">
                        Full Name
                    </label>
                    <input type="text" name="full_name" id="full_name" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Enter your full name">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                        Email Address
                    </label>
                    <input type="email" name="email" id="email" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Enter your email">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        Password
                    </label>
                    <div class="password-container">
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline pr-10"
                                placeholder="Enter your password">
                            <span class="absolute right-2 top-2 password-toggle" onclick="togglePassword('password', this)">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <div class="password-strength"></div>
                        <div class="password-requirements mt-2">
                            <p class="requirement" id="length">• At least 8 characters</p>
                            <p class="requirement" id="uppercase">• At least one uppercase letter</p>
                            <p class="requirement" id="lowercase">• At least one lowercase letter</p>
                            <p class="requirement" id="number">• At least one number</p>
                            <p class="requirement" id="special">• At least one special character</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 password-hint hidden">
                            Password is currently visible
                        </p>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                        Confirm Password
                    </label>
                    <div class="password-container">
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline pr-10"
                                placeholder="Confirm your password">
                            <span class="absolute right-2 top-2 password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 password-hint hidden">
                            Password is currently visible
                        </p>
                    </div>
                </div>

                <!-- Business Fields (Hidden by default) -->
                <div id="business_fields" class="hidden">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="business_name">
                            Business Name
                        </label>
                        <input type="text" name="business_name" id="business_name"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            placeholder="Enter your business name">
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="business_address">
                            Business Address
                        </label>
                        <textarea name="business_address" id="business_address"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            placeholder="Enter your business address"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <button type="submit" name="signup" id="signupButton"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full opacity-50 cursor-not-allowed">
                        Sign Up
                    </button>
                </div>

                <p class="text-center text-gray-600 text-sm">
                    Already have an account? 
                    <a href="login.php" class="text-blue-500 hover:text-blue-700">Sign in here</a>
                </p>
            </form>
        </div>
    </div>

    <script>
    function togglePassword(inputId, toggleElement) {
        const passwordInput = document.getElementById(inputId);
        const icon = toggleElement.querySelector('i');
        const hint = toggleElement.closest('.password-container').querySelector('.password-hint');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            toggleElement.classList.add('password-visible');
            hint.classList.remove('hidden');
            
            // Auto-hide password after 5 seconds
            setTimeout(() => {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                toggleElement.classList.remove('password-visible');
                hint.classList.add('hidden');
            }, 5000);
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            toggleElement.classList.remove('password-visible');
            hint.classList.add('hidden');
        }
    }

    function toggleBusinessFields() {
        const userType = document.getElementById('user_type').value;
        const businessFields = document.getElementById('business_fields');
        const businessName = document.getElementById('business_name');
        const businessAddress = document.getElementById('business_address');
        
        if (userType === 'seller') {
            businessFields.classList.remove('hidden');
            businessName.required = true;
            businessAddress.required = true;
        } else {
            businessFields.classList.add('hidden');
            businessName.required = false;
            businessAddress.required = false;
        }
    }

    // Password strength and requirements checker
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const requirements = {
        length: /.{8,}/,
        uppercase: /[A-Z]/,
        lowercase: /[a-z]/,
        number: /[0-9]/,
        special: /[^A-Za-z0-9]/
    };

    function checkPasswordStrength(password) {
        let strength = 0;
        Object.values(requirements).forEach(regex => {
            if (regex.test(password)) strength++;
        });
        return strength;
    }

    function updateRequirements(password) {
        Object.entries(requirements).forEach(([requirement, regex]) => {
            const element = document.getElementById(requirement);
            if (regex.test(password)) {
                element.classList.add('requirement-met');
                element.classList.remove('requirement-unmet');
            } else {
                element.classList.add('requirement-unmet');
                element.classList.remove('requirement-met');
            }
        });
    }

    function updatePasswordStrength(password) {
        const strengthBar = document.querySelector('.password-strength');
        const strength = checkPasswordStrength(password);
        
        strengthBar.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
        
        if (password.length === 0) {
            strengthBar.style.width = '0';
            return;
        }
        
        if (strength <= 2) {
            strengthBar.classList.add('strength-weak');
        } else if (strength <= 4) {
            strengthBar.classList.add('strength-medium');
        } else {
            strengthBar.classList.add('strength-strong');
        }
    }

    // Enable/disable signup button based on password requirements
    function updateSignupButton() {
        const signupButton = document.getElementById('signupButton');
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const strength = checkPasswordStrength(password);
        
        if (strength >= 4 && password === confirmPassword && password.length > 0) {
            signupButton.classList.remove('opacity-50', 'cursor-not-allowed');
            signupButton.disabled = false;
        } else {
            signupButton.classList.add('opacity-50', 'cursor-not-allowed');
            signupButton.disabled = true;
        }
    }

    // Event listeners
    password.addEventListener('input', function(e) {
        const value = e.target.value;
        updatePasswordStrength(value);
        updateRequirements(value);
        updateSignupButton();
    });

    confirmPassword.addEventListener('input', updateSignupButton);
    </script>
</body>
</html> 