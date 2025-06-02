<?php
// Generate a hashed password
$new_password = "password123"; // This will be the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hashed_password;
?> 