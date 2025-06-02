-- Add seller_id column to products table
ALTER TABLE products
ADD COLUMN seller_id INT NOT NULL AFTER id,
ADD FOREIGN KEY (seller_id) REFERENCES users(id),
ADD INDEX idx_seller_id (seller_id); 