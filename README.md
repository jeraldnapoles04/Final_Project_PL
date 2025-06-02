![alt text](https://github.com/jeraldnapoles04/Final_Project_PL/blob/master/Final/assets/NapZon_Logo.png?raw=true)
# NapZon E-commerce Platform Documentation

1. Project Overview

NapZon is a PHP-based e-commerce platform designed for online product sales. It provides a comprehensive system for both buyers and sellers to manage products, orders, and user information.

Key Features: 

*   User authentication and authorization (Buyer & Seller roles)
*   Product browsing and management
*   Shopping cart functionality
*   Order processing and management
*   User profile management
*   Seller dashboard with order analytics

2. Tech Stack

Backend: 

*   PHP 8.x
*   MySQL Database
*   PDO (PHP Data Objects) for database interaction

Frontend: 

*   HTML
*   CSS (Tailwind CSS)
*   JavaScript

3. Installation Instructions

Prerequisites: 

*   Web server with PHP support (like Apache via XAMPP)
*   MySQL Database

Setup Steps: 

1.  Place the `Napzon_Proj-main` project folder within your web server's document root (e.g., `htdocs` for XAMPP).

2.  Database Setup: 
    *   Open your MySQL management tool (e.g., phpMyAdmin).
    *   Create a new database named `napzon_db`.
    *   Import the main database schema:

        ```bash
        mysql -u your_username -p napzon_db < Final/database.sql
        ```
        (Replace `your_username` with your MySQL username. You will be prompted for the password.)

    *   Run the additional SQL files in the following specific order:

        ```bash
        mysql -u your_username -p napzon_db < Final/add_cart_table.sql
        mysql -u your_username -p napzon_db < Final/add_shipping_fee.sql
        mysql -u your_username -p napzon_db < Final/add_seller_id_column.sql
        mysql -u your_username -p napzon_db < Final/create_wishlist_table.sql
        mysql -u your_username -p napzon_db < Final/add_reset_token_columns.sql
        mysql -u your_username -p napzon_db < Final/add_bank_info_columns.sql
        mysql -u your_username -p napzon_db < Final/add_profile_columns.sql
        mysql -u your_username -p napzon_db < Final/add_phone_number_column.sql
        mysql -u your_username -p napzon_db < Final/notifications.sql
        ```
        (Make sure to run these commands from the `Napzon_Proj-main` directory or adjust the paths accordingly).

3.  Configuration: 
    *   Update the database connection settings in `Final/config.php` if your database username or password differs from the default (usually `root` with no password for XAMPP).

  4. Usage Guide

 Running the Application: 

*   Start your web server and MySQL database.
*   Access the application through your web browser, typically at `http://localhost/Napzon_Proj-main/Final/`.

Important Pages (Relative to `http://localhost/Napzon_Proj-main/Final/`): 

*   Home: `index.php`
*   Buyer Login: `login.php`
*   Buyer Signup: `signup.php`
*   Seller Login: `login.php` (Use seller credentials after creating a seller account)
*   Seller Dashboard: `seller_dashboard.php`
*   Manage Orders (Seller): `manage_orders.php`
*   Product Listing: `products.php`
*   Cart: `cart.php`
*   Checkout: `checkout.php`

 Default Login Credentials: 

*(Note: Default accounts are not provided. Please create new accounts via the signup page.)*

5. Project Structure

Key Directories: 

*   `Final/`: Contains the core PHP application files.
*   `Final/assets/`: Contains static assets like images and potentially CSS/JS files.
*   `database/`: (If exists) Database schema and migration files.
*   `config/`: Application configuration files.

  6. Important Notes

*   Ensure all SQL migration files are run in the specified order for the database to be set up correctly.
*   Test the system by creating both buyer and seller accounts to explore the different functionalities.

For any issues, please contact the project maintainers.
