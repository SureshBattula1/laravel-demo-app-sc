-- Script to create a test user with ONLY students.view permission
-- Use this to test that users can ONLY see actions they have permission for

-- First, reset permissions for admin@school.com to only students.view
UPDATE user_roles 
SET role_id = (SELECT id FROM roles WHERE slug = 'super-admin')
WHERE user_id = (SELECT id FROM users WHERE email = 'admin@school.com');

-- Remove all role permissions from super-admin except students.view
DELETE FROM role_permissions 
WHERE role_id = (SELECT id FROM roles WHERE slug = 'super-admin')
AND permission_id NOT IN (SELECT id FROM permissions WHERE slug = 'students.view');

-- Verify the changes
SELECT u.email, p.slug 
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN roles r ON ur.role_id = r.id
JOIN role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE u.email = 'admin@school.com';

