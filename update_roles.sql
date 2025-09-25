-- Update existing user roles and add user_type field
-- Run this script to update your existing database

-- Add user_type column if it doesn't exist
ALTER TABLE users ADD COLUMN user_type ENUM('buyer','seller') NOT NULL DEFAULT 'buyer';

-- Update existing users based on their original role
UPDATE users SET role = 'user', user_type = 'buyer' WHERE role = 'buyer';
UPDATE users SET role = 'user', user_type = 'seller' WHERE role = 'seller';

-- Keep admin users as admin with buyer type
UPDATE users SET user_type = 'buyer' WHERE role = 'admin';

-- Verify the changes
SELECT id, username, email, role, user_type FROM users;
1   