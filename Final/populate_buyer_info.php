<?php
require_once 'config.php';

try {
    // Select buyer users who do not have a record in buyer_info
    $stmt = $conn->prepare("
        SELECT u.id 
        FROM users u
        LEFT JOIN buyer_info bi ON u.id = bi.user_id
        WHERE u.user_type = 'buyer' AND bi.user_id IS NULL
    ");
    $stmt->execute();
    $buyer_users_to_add = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $added_count = 0;
    
    if (!empty($buyer_users_to_add)) {
        // Prepare the insert statement
        $insert_stmt = $conn->prepare("INSERT INTO buyer_info (user_id, shipping_address, phone_number) VALUES (?, '', '')");

        // Insert records for each buyer user found
        foreach ($buyer_users_to_add as $user_id) {
            $insert_stmt->execute([$user_id]);
            $added_count++;
        }
        echo "Successfully added " . $added_count . " existing buyer records to buyer_info table.";
    } else {
        echo "No existing buyer users found that need to be added to buyer_info table.";
    }

} catch(PDOException $e) {
    echo "Error populating buyer_info table: " . $e->getMessage();
}
?> 