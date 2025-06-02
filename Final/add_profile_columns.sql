-- Add profile-related columns to users table
ALTER TABLE users 
ADD COLUMN address TEXT DEFAULT NULL,
ADD COLUMN city VARCHAR(100) DEFAULT NULL,
ADD COLUMN postal_code VARCHAR(20) DEFAULT NULL;

-- Add comment explaining the changes
-- This SQL adds new columns for user profile information:
-- - address: For storing the full address
-- - city: For storing city names
-- - postal_code: For storing postal/ZIP codes
-- Note: phone_number column should already exist 