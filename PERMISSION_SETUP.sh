#!/bin/bash

# Permission System Setup Script
# This script sets up the RBAC permission system for the School Management System

echo "🔐 Setting up RBAC Permission System..."
echo "========================================"
echo ""

# Step 1: Run migrations
echo "📊 Step 1: Running database migrations..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "❌ Migration failed! Please check your database connection."
    exit 1
fi

echo "✅ Migrations completed successfully!"
echo ""

# Step 2: Run permission seeder
echo "🌱 Step 2: Seeding roles, modules, and permissions..."
php artisan db:seed --class=PermissionSeeder --force

if [ $? -ne 0 ]; then
    echo "❌ Seeding failed! Please check the seeder file."
    exit 1
fi

echo "✅ Permissions seeded successfully!"
echo ""

# Step 3: Clear cache
echo "🧹 Step 3: Clearing application cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo "✅ Cache cleared!"
echo ""

# Summary
echo "========================================"
echo "✨ RBAC Permission System Setup Complete!"
echo "========================================"
echo ""
echo "What was created:"
echo "  ✅ 6 database tables (roles, modules, permissions, pivots)"
echo "  ✅ 7 system roles (SuperAdmin, BranchAdmin, Teacher, etc.)"
echo "  ✅ 19 modules (Students, Teachers, Attendance, etc.)"
echo "  ✅ 100+ permissions assigned to roles"
echo ""
echo "Next steps:"
echo "  1. Assign roles to your existing users"
echo "  2. Test the permission system with different users"
echo "  3. Customize permissions as needed"
echo ""
echo "Documentation:"
echo "  📖 See RBAC_IMPLEMENTATION_GUIDE.md for complete documentation"
echo ""
echo "API Endpoints:"
echo "  GET  /api/permissions/roles - Get all roles"
echo "  GET  /api/permissions/modules - Get all modules"
echo "  GET  /api/permissions/user/{id}/permissions - Get user permissions"
echo ""
echo "🎉 Happy coding!"

