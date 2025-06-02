<?php
session_start();
require_once 'config.php';

// Ensure user is logged in as seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    die("Please log in as a seller first");
}

$seller_id = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/products/';
if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
}
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Product data
$products = [
    // Samba OG
    [
        'name' => 'Adidas Samba OG Cream',
        'brand' => 'Adidas',
        'category' => 'Casual',
        'price' => 4799.99,
        'stock' => 25,
        'description' => 'Classic Adidas Samba OG in elegant cream color. Perfect for everyday wear with timeless style.',
        'image' => 'assets/Assets/Adidas/SambaOG_Cream.jpg',
        'colors' => 'Cream',
        'featured' => true
    ],
    [
        'name' => 'Adidas Samba OG Brown',
        'brand' => 'Adidas',
        'category' => 'Casual',
        'price' => 4799.99,
        'stock' => 20,
        'description' => 'Classic Adidas Samba OG in rich brown color. Versatile and stylish for any occasion.',
        'image' => 'assets/Assets/Adidas/SambaOG_Brown.jpg',
        'colors' => 'Brown',
        'featured' => false
    ],
    // Ultra Boost
    [
        'name' => 'Adidas Ultra Boost Gray',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 8499.99,
        'stock' => 30,
        'description' => 'Premium running shoes with Ultra Boost technology for maximum comfort and performance.',
        'image' => 'assets/Assets/Adidas/UltraBoost_Gray.jpg',
        'colors' => 'Gray',
        'featured' => true
    ],
    [
        'name' => 'Adidas Ultra Boost Multi-Color',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 8999.99,
        'stock' => 15,
        'description' => 'Limited edition Ultra Boost with unique multi-color design for standout style.',
        'image' => 'assets/Assets/Adidas/UltraBoost_CombinedColors1.jpg',
        'colors' => 'Multi',
        'featured' => true
    ],
    // Bounce Legend
    [
        'name' => 'Adidas Bounce Legend White',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 3799.99,
        'stock' => 40,
        'description' => 'Versatile training shoes with Bounce cushioning for all-day comfort.',
        'image' => 'assets/Assets/Adidas/BounceLegend_White.jpg',
        'colors' => 'White',
        'featured' => false
    ],
    [
        'name' => 'Adidas Bounce Legend Violet',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 3999.99,
        'stock' => 20,
        'description' => 'Eye-catching violet Bounce Legend perfect for both training and casual wear.',
        'image' => 'assets/Assets/Adidas/BounceLegend_Violet.jpg',
        'colors' => 'Violet',
        'featured' => true
    ],
    // Adizero
    [
        'name' => 'Adidas Adizero Blue Light',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 6499.99,
        'stock' => 25,
        'description' => 'Lightweight performance running shoes for speed and agility.',
        'image' => 'assets/Assets/Adidas/Adizero_LightBlue.jpg',
        'colors' => 'Light Blue',
        'featured' => true
    ],
    [
        'name' => 'Adidas Adizero Black',
        'brand' => 'Adidas',
        'category' => 'Sport',
        'price' => 6299.99,
        'stock' => 35,
        'description' => 'Classic black Adizero running shoes combining style and performance.',
        'image' => 'assets/Assets/Adidas/Adizero_Black.jpg',
        'colors' => 'Black',
        'featured' => false
    ]
];

// Available sizes for all shoes
$sizes = '36,37,38,39,40,41,42,43,44,45';

// Add each product
foreach ($products as $product) {
    // Copy image to uploads directory
    $source_image = $product['image'];
    $file_extension = pathinfo($source_image, PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $new_filename;
    
    if (copy($source_image, $destination)) {
        try {
            // Insert product into database
            $stmt = $conn->prepare("
                INSERT INTO products (seller_id, name, brand, category, price, sizes, colors, stock, description, image_url, featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $seller_id,
                $product['name'],
                $product['brand'],
                $product['category'],
                $product['price'],
                $sizes,
                $product['colors'],
                $product['stock'],
                $product['description'],
                $destination,
                $product['featured']
            ]);
            
            echo "Added product: " . $product['name'] . "<br>";
        } catch(PDOException $e) {
            echo "Error adding product " . $product['name'] . ": " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Failed to copy image for " . $product['name'] . "<br>";
    }
}

echo "<br>Done adding products! <a href='seller_dashboard.php'>Go to Dashboard</a>";
?> 