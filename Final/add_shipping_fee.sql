-- Add missing columns if they don't exist
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 100.00,
ADD COLUMN IF NOT EXISTS shipping_address TEXT NULL,
ADD COLUMN IF NOT EXISTS shipping_city VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS shipping_postal_code VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS contact_email VARCHAR(100) NULL;

-- Update any existing orders that don't have shipping information
UPDATE orders 
SET shipping_fee = 100.00 WHERE shipping_fee IS NULL;

-- Drop the NOT NULL constraint temporarily if it exists
ALTER TABLE orders
MODIFY shipping_address TEXT NULL,
MODIFY shipping_city VARCHAR(100) NULL,
MODIFY shipping_postal_code VARCHAR(20) NULL,
MODIFY contact_phone VARCHAR(20) NULL,
MODIFY contact_email VARCHAR(100) NULL; 