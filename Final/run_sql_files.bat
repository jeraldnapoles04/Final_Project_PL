@echo off
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_cart_table.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_shipping_fee.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_seller_id_column.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0create_wishlist_table.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_reset_token_columns.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_bank_info_columns.sql"
"C:\xampp\mysql\bin\mysql" -u root napzon_db < "%~dp0add_profile_columns.sql" 