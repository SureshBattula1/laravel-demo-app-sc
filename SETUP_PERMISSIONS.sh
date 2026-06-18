#!/bin/bash

echo "🔐 School Management System - Permission Setup"
echo "=============================================="
echo ""

# Step 1: Run migrations
echo "📊 Step 1: Running migrations..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    exit 1
fi
echo "✅ Migrations completed!"
echo ""

# Step 2: Seed permissions
echo "🌱 Step 2: Seeding permissions..."
php artisan db:seed --class=PermissionSeeder --force

if [ $? -ne 0 ]; then
    echo "❌ Permission seeding failed!"
    exit 1
fi
echo "✅ Permissions seeded!"
echo ""

# Step 3: Create test users
echo "👥 Step 3: Creating test users..."
php artisan db:seed --class=TestUserSeeder --force

if [ $? -ne 0 ]; then
    echo "❌ Test user creation failed!"
    exit 1
fi
echo "✅ Test users created!"
echo ""

# Step 4: Clear cache
echo "🧹 Step 4: Clearing cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
echo "✅ Cache cleared!"
echo ""

# Summary
echo "=============================================="
echo "✨ Setup Complete!"
echo "=============================================="
echo ""
echo "🎉 You can now login with these accounts:"
echo ""
echo "  🔑 SuperAdmin:"
echo "     Email: admin@school.com"
echo "     Password: password"
echo "     Access: ALL modules"
echo ""
echo "  🏢 Branch Admin:"
echo "     Email: branchadmin@school.com"
echo "     Password: password"
echo "     Access: ~80% modules"
echo ""
echo "  👨‍🏫 Teacher:"
echo "     Email: teacher@school.com"
echo "     Password: password"
echo "     Access: Students, Attendance, Exams, Grades"
echo ""
echo "  💰 Accountant:"
echo "     Email: accountant@school.com"
echo "     Password: password"
echo "     Access: Accounts, Transactions, Fees, Invoices"
echo ""
echo "  🎓 Student:"
echo "     Email: student@school.com"
echo "     Password: password"
echo "     Access: Dashboard, Attendance, Fees, Holidays"
echo ""
echo "📝 Next steps:"
echo "  1. Clear browser cache (Ctrl+Shift+Delete)"
echo "  2. Clear localStorage in browser console"
echo "  3. Login with any test account above"
echo "  4. Check browser console for permission logs"
echo ""
echo "🐛 Debugging:"
echo "  - Check logs: tail -f storage/logs/laravel.log"
echo "  - See PERMISSION_DEBUG_GUIDE.md for troubleshooting"
echo ""
echo "🎉 Happy testing!"

