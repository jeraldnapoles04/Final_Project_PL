<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a buyer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';

// Get cart count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch()['count'];
} catch(PDOException $e) {
    $cart_count = 0;
}

// Handle search and filtering
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';  // Default to newest
$search_results = [];

try {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search_query)) {
        $where_conditions[] = "(name LIKE ? OR brand LIKE ? OR category LIKE ?)";
        $search_param = "%{$search_query}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($category_filter)) {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Add sorting
    $order_by = match($sort_by) {
        'price_low' => "ORDER BY price ASC",
        'price_high' => "ORDER BY price DESC",
        'newest' => "ORDER BY created_at DESC",
        default => "ORDER BY created_at DESC"
    };
    
    $sql = "SELECT * FROM products {$where_clause} {$order_by}";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error performing search: " . $e->getMessage();
    $search_results = [];
}

// Get all available categories
try {
    $stmt = $conn->prepare("SELECT DISTINCT category FROM products ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - NapZon</title>
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
        .sidebar-logo {
            max-width: 100%;
            height: auto;
        }
        .product-sizes label {
            cursor: pointer;
        }
        .product-sizes input[type="radio"]:checked + span {
            background-color: #3b82f6;
            color: white;
        }
        .product-colors label {
            cursor: pointer;
        }
        .product-colors input[type="radio"]:checked + span {
            border-color: #3b82f6;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-300">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-slate-800 text-slate-300 flex flex-col">
            <div class="p-6">
                 <img src="assets/NapZon_Logo.png" alt="NapZon Logo" class="sidebar-logo mb-4">
                <p class="text-sm text-slate-400 mt-1">Welcome, <?php echo htmlspecialchars($full_name); ?></p>
            </div>
            <nav class="mt-10 flex-1 px-4 space-y-2">
                <a href="buyer_dashboard.php" class="flex items-center px-4 py-3 rounded-md sidebar-active-link">
                    <i class="fas fa-home w-6 mr-3"></i>
                    <span>Home</span>
                </a>
                <a href="orders.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-shopping-bag w-6 mr-3"></i>
                    <span>My Orders</span>
                </a>
                <a href="wishlist.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-heart w-6 mr-3"></i>
                    <span>Wishlist</span>
                </a>
                <a href="cart.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md relative">
                    <i class="fas fa-shopping-cart w-6 mr-3"></i>
                    <span>Cart</span>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-2 right-2 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            <?php echo $cart_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-md">
                    <i class="fas fa-user w-6 mr-3"></i>
                    <span>Profile</span>
                </a>
            </nav>
             <div class="p-4 border-t border-slate-700">
                <div class="flex items-center">
                     <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center text-white text-lg font-semibold">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($full_name); ?></p>
                        <p class="text-xs text-slate-400">Buyer</p>
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
            <!-- Top Bar with Search and Filters -->
            <div class="bg-slate-800/50 backdrop-filter backdrop-blur-lg rounded-md shadow-md mb-6 px-6 py-4 flex flex-col md:flex-row justify-between items-center border border-slate-700 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-white">Welcome to NapZon</h2>
                    <p class="text-slate-400 text-sm">Find your perfect pair of shoes</p>
                </div>
                <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                    <form action="" method="GET" class="w-full md:w-96">
                        <div class="relative">
                            <input type="text" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   placeholder="Search for shoes..." 
                                   class="w-full bg-slate-700 text-white rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600"
                            >
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </form>
                    <select name="category" class="bg-slate-700 text-white rounded-lg px-4 py-2 border border-slate-600">
                        <option value="">All Categories</option>
                        <option value="Men" <?php echo isset($_GET['category']) && $_GET['category'] === 'Men' ? 'selected' : ''; ?>>Men</option>
                        <option value="Women" <?php echo isset($_GET['category']) && $_GET['category'] === 'Women' ? 'selected' : ''; ?>>Women</option>
                    </select>
                    <select name="sort" class="bg-slate-700 text-white rounded-lg px-4 py-2 border border-slate-600">
                        <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php
                $products_to_display = !empty($search_results) ? $search_results : (isset($featured_products) ? $featured_products : []);
                
                foreach ($products_to_display as $product):
                    $sizes = explode(',', $product['sizes']);
                    $colors = explode(',', $product['colors']);
                ?>
                    <div class="card-bg rounded-lg shadow-md border border-slate-700 overflow-hidden product-card">
                        <!-- Product Image -->
                        <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="w-full h-48 object-cover cursor-pointer hover:opacity-90 transition"
                                 onclick="showProductDetails(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                        <?php else: ?>
                            <div class="w-full h-48 bg-slate-700 flex items-center justify-center">
                                <i class="fas fa-image text-4xl text-slate-500"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Product Info -->
                        <div class="p-4">
                            <h4 class="text-white font-semibold mb-2 cursor-pointer hover:text-blue-400 transition"
                                onclick="showProductDetails(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h4>
                            <p class="text-slate-400 text-sm mb-2"><?php echo htmlspecialchars($product['brand']); ?></p>
                            <p class="text-blue-400 font-bold">₱<?php echo number_format($product['price'], 2); ?></p>
                            
                            <!-- Quick Add Form -->
                            <form class="mt-3 space-y-3 add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                                <!-- Sizes -->
                                <div class="product-sizes flex flex-wrap gap-2">
                                    <?php foreach ($sizes as $size): ?>
                                        <label class="relative">
                                            <input type="radio" name="size" value="<?php echo htmlspecialchars($size); ?>" class="absolute opacity-0">
                                            <span class="px-2 py-1 border border-slate-600 rounded-md text-sm hover:border-blue-500 transition">
                                                <?php echo htmlspecialchars($size); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Colors -->
                                <div class="product-colors flex flex-wrap gap-2">
                                    <?php foreach ($colors as $color): ?>
                                        <label class="relative">
                                            <input type="radio" name="color" value="<?php echo htmlspecialchars($color); ?>" class="absolute opacity-0">
                                            <span class="block w-6 h-6 rounded-full border-2 border-transparent hover:border-blue-500 transition"
                                                  style="background-color: <?php echo htmlspecialchars(strtolower($color)); ?>">
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-2xl font-bold text-white" id="modalProductName"></h3>
                    <button onclick="closeProductModal()" class="text-slate-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent"></div>
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
        // Handle add to cart forms
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const productId = form.dataset.productId;
                const size = form.querySelector('input[name="size"]:checked')?.value;
                const color = form.querySelector('input[name="color"]:checked')?.value;
                
                if (!size || !color) {
                    alert('Please select both size and color');
                    return;
                }
                
                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&size=${size}&color=${color}&quantity=1`
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        alert('Added to cart successfully!');
                        location.reload(); // Refresh to update cart count
                    } else {
                        alert(data.message || 'Failed to add to cart');
                    }
                } catch (error) {
                    alert('Error adding to cart');
                }
            });
        });

        // Handle filters and sorting
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.querySelector('select[name="category"]');
            const sortSelect = document.querySelector('select[name="sort"]');
            const searchInput = document.querySelector('input[name="search"]');

            if (categorySelect) categorySelect.addEventListener('change', updateFilters);
            if (sortSelect) sortSelect.addEventListener('change', updateFilters);
            if (searchInput) searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    updateFilters();
                }
            });
        });

        function updateFilters() {
            const category = document.querySelector('select[name="category"]')?.value || '';
            const sort = document.querySelector('select[name="sort"]')?.value || '';
            const search = document.querySelector('input[name="search"]')?.value || '';
            
            const params = new URLSearchParams();
            
            if (category) params.set('category', category);
            if (sort) params.set('sort', sort);
            if (search) params.set('search', search);
            
            const queryString = params.toString();
            window.location.href = window.location.pathname + (queryString ? `?${queryString}` : '');
        }

        // Product modal functions
        function showProductDetails(product) {
            document.getElementById('modalProductName').textContent = product.name;
            document.getElementById('modalContent').innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <img src="${product.image_url}" alt="${product.name}" class="w-full rounded-lg">
                    </div>
                    <div>
                        <p class="text-slate-400 mb-2">${product.brand}</p>
                        <p class="text-blue-400 text-2xl font-bold mb-4">₱${parseFloat(product.price).toFixed(2)}</p>
                        <p class="text-slate-300 mb-4">${product.description}</p>
                        <div class="mb-4">
                            <p class="text-slate-400 mb-2">Available Sizes:</p>
                            <div class="flex flex-wrap gap-2">
                                ${product.sizes.split(',').map(size => `
                                    <span class="px-3 py-1 border border-slate-600 rounded-md">${size}</span>
                                `).join('')}
                            </div>
                        </div>
                        <div>
                            <p class="text-slate-400 mb-2">Available Colors:</p>
                            <div class="flex flex-wrap gap-2">
                                ${product.colors.split(',').map(color => `
                                    <span class="px-3 py-1 border border-slate-600 rounded-md">${color}</span>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('productModal').classList.remove('hidden');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProductModal();
            }
        });

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