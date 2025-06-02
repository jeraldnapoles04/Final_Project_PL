<?php
session_start();
require_once 'config.php';

// Handle Login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $userType = in_array($_POST['userType'], ['buyer', 'seller']) ? $_POST['userType'] : 'buyer';

    try {
        $stmt = $conn->prepare("SELECT id, full_name, password, user_type FROM users WHERE email = ? AND user_type = ?");
        $stmt->execute([$email, $userType]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Redirect based on user type
            if ($user['user_type'] === 'seller') {
                echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'seller_dashboard.php']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'buyer_dashboard.php']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
    }
}

// Handle Registration
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $fullName = htmlspecialchars($_POST['fullName'], ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $userType = in_array($_POST['userType'], ['buyer', 'seller']) ? $_POST['userType'] : 'buyer';

    // Validate password strength
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // If registering as seller, check if a seller already exists
        if ($userType === 'seller') {
            $stmt = $conn->prepare("SELECT COUNT(*) as seller_count FROM users WHERE user_type = 'seller'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['seller_count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'A seller account already exists. Only one seller is allowed.']);
                exit;
            }
        }

        // Hash password and insert into users table
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, user_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullName, $email, $hashedPassword, $userType]);
        $userId = $conn->lastInsertId();

        // If seller, insert additional info
        if ($userType === 'seller' && isset($_POST['businessName'])) {
            $businessName = htmlspecialchars($_POST['businessName'], ENT_QUOTES, 'UTF-8');
            $businessAddress = htmlspecialchars($_POST['businessAddress'], ENT_QUOTES, 'UTF-8');
            
            $stmt = $conn->prepare("INSERT INTO sellers_info (user_id, business_name, business_address) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $businessName, $businessAddress]);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch(PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
}
?> 