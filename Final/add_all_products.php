<?php
session_start();
require_once 'config.php';

// Ensure user is logged in as seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    die("Please log in as a seller first");
}

$seller_id = $_SESSION['user_id'];

// Get current directory
$current_dir = dirname(__FILE__);
echo "Current directory: " . $current_dir . "<br>";

// Create uploads directory if it doesn't exist
$upload_dir = $current_dir . '/uploads/products/';
if (!file_exists($current_dir . '/uploads')) {
    mkdir($current_dir . '/uploads', 0755, true);
    echo "Created uploads directory<br>";
}
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    echo "Created products directory<br>";
}

// Get all images from the Adidas folder - CORRECTED PATH
$image_dir = $current_dir . '/assets/Assets/';
echo "Looking for images in: " . $image_dir . "<br>";

// Check if directory exists
if (!is_dir($image_dir)) {
    die("Error: Assets directory not found at: " . $image_dir);
}

// Get all image files recursively from all subdirectories
$images = [];
$it = new RecursiveDirectoryIterator($image_dir);
$it = new RecursiveIteratorIterator($it);
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'jpg') {
        $images[] = $file->getPathname();
    }
}

echo "Found " . count($images) . " images<br>";

if (count($images) === 0) {
    die("No images found in directory. Please check the path is correct.");
}

// Set default prices based on model
$model_prices = [
    'SambaOG' => 4799.99,
    'UltraBoost' => 8499.99,
    'BounceLegend' => 3799.99,
    'Adizero' => 6499.99
];

// Available sizes for all shoes
$sizes = '36,37,38,39,40,41,42,43,44,45';

$added_count = 0;
$error_count = 0;

foreach ($images as $image) {
    echo "<hr>Processing image: " . basename($image) . "<br>";
    
    if (!file_exists($image)) {
        echo "Error: Image file does not exist: " . basename($image) . "<br>";
        $error_count++;
        continue;
    }
    
    $filename = basename($image);
    // Split filename into parts (e.g., "SambaOG_Cream.jpg" -> ["SambaOG", "Cream"])
    $parts = explode('_', pathinfo($filename, PATHINFO_FILENAME));
    $model = $parts[0];
    $color = str_replace('.jpg', '', $parts[1]);
    
    // Format the product name
    $name = 'Adidas ' . implode(' ', [$model, $color]);
    
    // Determine category based on model
    $category = ($model == 'SambaOG') ? 'Casual' : 'Sport';
    
    // Get price for this model
    $price = $model_prices[$model] ?? 4999.99;
    
    // Random stock between 15-40
    $stock = rand(15, 40);
    
    // Copy image to uploads directory
    $new_filename = uniqid() . '.jpg';
    $destination = $upload_dir . $new_filename;
    
    if (copy($image, $destination)) {
        try {
            // Insert product into database
            $stmt = $conn->prepare("
                INSERT INTO products (seller_id, name, brand, category, price, sizes, colors, stock, description, image_url, featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $description = "Adidas {$model} in {$color}. Premium quality footwear for " . 
                          ($category == 'Sport' ? "superior athletic performance." : "everyday style and comfort.");
            
            $stmt->execute([
                $seller_id,
                $name,
                'Adidas',
                $category,
                $price,
                $sizes,
                $color,
                $stock,
                $description,
                'uploads/products/' . $new_filename, // Store relative path in database
                rand(0, 1) // randomly set some products as featured
            ]);
            
            echo "✅ Successfully added: " . $name . "<br>";
            $added_count++;
        } catch(PDOException $e) {
            echo "❌ Database error adding " . $name . ": " . $e->getMessage() . "<br>";
            $error_count++;
        }
    } else {
        echo "❌ Failed to copy image " . basename($image) . "<br>";
        $error_count++;
    }
}

echo "<br><strong>Summary:</strong><br>";
echo "✅ Successfully added: " . $added_count . " products<br>";
if ($error_count > 0) {
    echo "❌ Errors encountered: " . $error_count . "<br>";
}
echo "<br><a href='seller_dashboard.php' class='text-blue-500 hover:underline'>Go to Dashboard</a>";
?> 