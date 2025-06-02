-- Add bank account columns to sellers_info table
ALTER TABLE sellers_info
ADD COLUMN bank_account_name VARCHAR(100) DEFAULT NULL,
ADD COLUMN bank_account_number VARCHAR(50) DEFAULT NULL,
ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL; 