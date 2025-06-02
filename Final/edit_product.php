<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header("Location: login.php");
    exit();
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $description = trim($_POST['description']);
    $sizes = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';
    $colors = isset($_POST['colors']) ? implode(',', $_POST['colors']) : '';
    $featured = isset($_POST['featured']) ? 1 : 0;

    try {
        // Start transaction
        $conn->beginTransaction();

        // Handle new image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            // Validate file type
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($file_extension, $allowed_types) && move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Update image URL in database
                $stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE id = ?");
                $stmt->execute([$upload_path, $product_id]);
            }
        }

        // Update other product details
        $stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, brand = ?, category = ?, price = ?, sizes = ?, 
                colors = ?, stock = ?, description = ?, featured = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $brand, $category, $price, $sizes, $colors, 
            $stock, $description, $featured, $product_id
        ]);

        $conn->commit();
        $success_message = "Product updated successfully!";
    } catch(PDOException $e) {
        $conn->rollBack();
        $error_message = "Failed to update product: " . $e->getMessage();
    }
}

// Fetch product data
try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: manage_products.php");
        exit();
    }

    // Convert sizes and colors back to arrays
    $product['sizes'] = explode(',', $product['sizes']);
    $product['colors'] = explode(',', $product['colors']);

} catch(PDOException $e) {
    $error_message = "Failed to fetch product details.";
    $product = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - NapZon Shoes</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a href="manage_products.php" class="flex items-center px-4 py-3 bg-gray-800 text-white">
                    <i class="fas fa-shoe-prints w-6"></i>
                    <span>Products</span>
                </a>
                <a href="manage_orders.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white">
                    <i class="fas fa-shopping-bag w-6"></i>
                    <span>Orders</span>
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white">
                    <i class="fas fa-chart-bar w-6"></i>
                    <span>Analytics</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Bar -->
            <div class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-8 py-4">
                    <h2 class="text-xl font-semibold">Edit Product</h2>
                    <a href="manage_products.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Products
                    </a>
                </div>
            </div>

            <!-- Edit Product Form -->
            <div class="p-8">
                <?php if (isset($success_message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-sm p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Product Name</label>
                                <input type="text" name="name" required value="<?php echo htmlspecialchars($product['name']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                                <input type="text" name="brand" required value="<?php echo htmlspecialchars($product['brand']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <?php
                                    $categories = ['Men', 'Women', 'Kids', 'Sport', 'Casual'];
                                    foreach ($categories as $cat) {
                                        $selected = ($cat === $product['category']) ? 'selected' : '';
                                        echo "<option value='$cat' $selected>$cat</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Price (₱)</label>
                                <input type="number" name="price" step="0.01" required value="<?php echo $product['price']; ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stock</label>
                                <input type="number" name="stock" required value="<?php echo $product['stock']; ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Additional Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Additional Details</h3>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-32 h-32 object-cover rounded mb-2">
                                <input type="file" name="image" accept="image/*"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">Leave empty to keep current image</p>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Available Sizes</label>
                                <div class="grid grid-cols-4 gap-2">
                                    <?php
                                    $sizes = range(36, 45);
                                    foreach ($sizes as $size) {
                                        $checked = in_array($size, $product['sizes']) ? 'checked' : '';
                                        echo "<label class='inline-flex items-center'>
                                            <input type='checkbox' name='sizes[]' value='$size' class='form-checkbox' $checked>
                                            <span class='ml-2'>$size</span>
                                        </label>";
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Available Colors</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <?php
                                    $colors = ['Black', 'White', 'Red', 'Blue', 'Green', 'Gray', 'Brown', 'Pink'];
                                    foreach ($colors as $color) {
                                        $checked = in_array($color, $product['colors']) ? 'checked' : '';
                                        echo "<label class='inline-flex items-center'>
                                            <input type='checkbox' name='colors[]' value='$color' class='form-checkbox' $checked>
                                            <span class='ml-2'>$color</span>
                                        </label>";
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" rows="4" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                ><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="featured" class="form-checkbox" 
                                           <?php echo $product['featured'] ? 'checked' : ''; ?>>
                                    <span class="ml-2">Feature this product on homepage</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 