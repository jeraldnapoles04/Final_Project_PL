<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Seller';

// Get seller's business information for sidebar (optional but good for consistency)
try {
    $stmt = $conn->prepare("SELECT business_name FROM sellers_info WHERE user_id = ?");
    $stmt->execute([$seller_id]);
    $business_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_name = isset($business_info['business_name']) ? htmlspecialchars($business_info['business_name']) : 'Your Business';
} catch(PDOException $e) {
    $business_name = 'Your Business';
}

$success_message = '';
$error_message = '';

// Handle product submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation for required fields
    $required_fields = ['name', 'brand', 'category', 'price', 'stock', 'description'];
    $form_valid = true;
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field]))) {
            $error_message = "Please fill in all required fields.";
            $form_valid = false;
            break;
        }
    }

    if ($form_valid) {
        $name = trim($_POST['name']);
        $brand = trim($_POST['brand']);
        $category = trim($_POST['category']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $description = trim($_POST['description']);
        
        // Handle sizes - ensure it's an array before imploding
        $sizes = isset($_POST['sizes']) && is_array($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';
        
        // Handle colors - ensure it's an array before imploding
        $colors = isset($_POST['colors']) && is_array($_POST['colors']) ? implode(',', $_POST['colors']) : '';

        $featured = isset($_POST['featured']) ? 1 : 0;

        // Handle image upload
        $image_url = '';
        $upload_error = false;

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) { // Check if a file was attempted to be uploaded
            $file = $_FILES['image'];
            
            // Check for specific upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = "Uploaded file is too large.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = "The uploaded file was only partially uploaded.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = "Missing a temporary folder for uploads.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = "Failed to write file to disk. Check server permissions.";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = "A PHP extension stopped the file upload.";
                        break;
                    default:
                        $error_message = "Unknown file upload error.";
                        break;
                }
                $upload_error = true;
            } else {
                 $upload_dir = 'uploads/products/';
                
                // Create directories if they don't exist with proper permissions
                if (!file_exists('uploads')) {
                    if (!mkdir('uploads', 0755, true)) {
                        $error_message = "Failed to create main uploads directory. Check server permissions.";
                        $upload_error = true;
                    }
                }
                
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error_message = "Failed to create products upload directory. Check server permissions.";
                        $upload_error = true;
                    }
                }

                // Ensure directory is writable
                if (!is_writable($upload_dir)) {
                    if (!chmod($upload_dir, 0755)) {
                        $error_message = "Upload directory is not writable. Please check permissions.";
                        $upload_error = true;
                    }
                }

                if (!$upload_error) {
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $new_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    // Validate file type and size
                    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    if (!in_array($file_extension, $allowed_types)) {
                        $error_message = "Invalid file type. Only JPG, JPEG, PNG, WEBP are allowed.";
                        $upload_error = true;
                    } elseif ($file['size'] > $max_size) {
                         $error_message = "File size exceeds the maximum limit of 5MB.";
                         $upload_error = true;
                    } elseif (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                         $error_message = "Failed to move uploaded file. Check directory permissions (`{$upload_dir}`).";
                         $upload_error = true;
                    } else {
                        $image_url = $upload_path; // Image upload successful
                    }
                }
            }
        }

        // Only attempt to insert into database if no form or upload errors occurred
        if (!$upload_error && empty($error_message)) {
            try {
                // Insert new product including seller_id
                // NOTE: This query assumes a 'seller_id' column exists in the 'products' table.
                $stmt = $conn->prepare("
                    INSERT INTO products (name, brand, category, price, sizes, colors, stock, description, image_url, featured, seller_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $name, $brand, $category, $price, $sizes, $colors, $stock, $description, $image_url, $featured, $seller_id
                ]);

                $success_message = "Product added successfully!";
                
                 // Clear form fields after successful submission (optional)
                 // $_POST = array();
                 // $_FILES = array( 'image' => ['error' => UPLOAD_ERR_NO_FILE] ); // Reset file input

            } catch(PDOException $e) {
                $error_message = "Failed to add product to database: " . $e->getMessage();
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
    <title>Add New Product - NapZon</title>
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
         .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #334155; /* slate-700 */
            background-color: #1e293b; /* slate-800 */
            color: #f8fafc; /* slate-100 */
            border-radius: 0.375rem; /* rounded-md */
            outline: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
         .form-input:focus {
            border-color: #3b82f6; /* blue-500 */
            box-shadow: 0 0 0 1px #3b82f6;
        }
         .form-label {
            display: block;
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; /* font-medium */
            color: #e2e8f0; /* slate-200 */
            margin-bottom: 0.5rem;
        }
         .checkbox-label {
             color: #e2e8f0; /* slate-200 */
         }
         .form-checkbox {
            background-color: #1e293b; /* slate-800 */
            border: 1px solid #334155; /* slate-700 */
            color: #3b82f6; /* blue-500 */
            border-radius: 0.25rem;
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
         .form-checkbox:checked {
            background-color: #3b82f6; /* blue-500 */
            border-color: #3b82f6; /* blue-500 */
        }
         .form-checkbox:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-slate-800 text-slate-300 flex flex-col">
            <div class="p-6">
                 <h1 class="text-2xl font-bold text-gradient-blue">NapZon Seller</h1>
                <p class="text-sm text-slate-400 mt-1"><?php echo $business_name; ?></p>
            </div>
            <nav class="mt-10 flex-1 px-4 space-y-2">
                <a href="seller_dashboard.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-tachometer-alt w-6 mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_products.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
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
                    <h2 class="text-2xl font-bold text-white">Add New Product</h2>
                    <p class="text-slate-400 text-sm">Fill out the form to add a new product to your store.</p>
                </div>
                 <a href="manage_products.php" class="text-slate-400 hover:text-white transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Products
                </a>
            </div>

            <!-- Add Product Form -->
            <div class="rounded-lg shadow-md card-bg border border-slate-700">
                <div class="p-6">
                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-500/20 border border-green-500 text-green-200 px-4 py-3 rounded mb-4">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-200 px-4 py-3 rounded mb-4">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-4">Basic Information</h3>
                                
                                <div class="mb-4">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" id="name" name="name" required class="form-input">
                                </div>

                                <div class="mb-4">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" id="brand" name="brand" required class="form-input">
                                </div>

                                <div class="mb-4">
                                    <label for="category" class="form-label">Category</label>
                                    <select id="category" name="category" required class="form-input">
                                        <option value="Men">Men</option>
                                        <option value="Women">Women</option>
                                        <option value="Kids">Kids</option>
                                        <option value="Sport">Sport</option>
                                        <option value="Casual">Casual</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="price" class="form-label">Price (₱)</label>
                                    <input type="number" id="price" name="price" step="0.01" required class="form-input">
                                </div>

                                <div class="mb-4">
                                    <label for="stock" class="form-label">Stock</label>
                                    <input type="number" id="stock" name="stock" required class="form-input">
                                </div>
                            </div>

                            <!-- Additional Details -->
                            <div>
                                <h3 class="text-lg font-semibold text-white mb-4">Additional Details</h3>

                                <div class="mb-4">
                                    <label for="image" class="form-label">Product Image</label>
                                    <input type="file" id="image" name="image" accept="image/*" required class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <!-- Image preview can be added here if desired -->
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Available Sizes</label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <?php
                                        $sizes = range(36, 45);
                                        foreach ($sizes as $size) {
                                            echo "<label class='inline-flex items-center'>
                                                <input type='checkbox' name='sizes[]' value='$size' class='form-checkbox'>
                                                <span class='ml-2 checkbox-label'>$size</span>
                                            </label>";
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Available Colors</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <?php
                                        $colors = ['Black', 'White', 'Red', 'Blue', 'Green', 'Gray', 'Brown', 'Pink'];
                                        foreach ($colors as $color) {
                                            echo "<label class='inline-flex items-center'>
                                                <input type='checkbox' name='colors[]' value='$color' class='form-checkbox'>
                                                <span class='ml-2 checkbox-label'>$color</span>
                                            </label>";
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" rows="4" required class="form-input"></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="featured" class="form-checkbox">
                                        <span class="ml-2 checkbox-label">Feature this product on homepage</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                                Add Product
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