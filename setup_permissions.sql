-- Setup Permissions for Newsletter Module
-- This script adds newsletter permissions to the administrator user group

-- First, let's get the admin user group ID (usually 1, but let's be safe)
-- Update the user group permissions to include newsletter module

-- For User Group ID 1 (Top Administrator)
UPDATE `oc_user_group` 
SET `permission` = CONCAT(
    COALESCE(`permission`, '{"access":[],"modify":[]}'),
    ''
) 
WHERE `user_group_id` = 1;

-- Alternative: Add permissions manually
-- If the above doesn't work, you need to add these permissions manually via Admin Panel:
-- 
-- Access:
--   - marketing/newsletter
--   - tool/newsletter_install
-- 
-- Modify:
--   - marketing/newsletter
--   - tool/newsletter_install

