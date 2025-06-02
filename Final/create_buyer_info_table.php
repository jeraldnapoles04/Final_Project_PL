<?php
require_once 'config.php';

try {
    // Create buyer_info table
    $sql = "CREATE TABLE IF NOT EXISTS buyer_info (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        shipping_address TEXT,
        phone_number VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "buyer_info table created successfully";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 