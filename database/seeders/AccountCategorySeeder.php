<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AccountCategory;
use Illuminate\Support\Facades\DB;

class AccountCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $categories = [
                // Income Categories (Global - available for all branches)
                [
                    'branch_id' => null, // NULL = Global category
                    'name' => 'Tuition Fees',
                    'code' => 'INC-001',
                    'type' => 'Income',
                    'sub_type' => 'Tuition Fees',
                    'description' => 'Regular tuition fee payments from students',
                    'is_active' => true
                ],
                [
                    'branch_id' => null,
                    'name' => 'Admission Fees',
                    'code' => 'INC-002',
                    'type' => 'Income',
                    'sub_type' => 'Admission Fees',
                    'description' => 'One-time admission fee collected from new students',
                    'is_active' => true
                ],
                [
                    'name' => 'Exam Fees',
                    'code' => 'INC-003',
                    'type' => 'Income',
                    'sub_type' => 'Exam Fees',
                    'description' => 'Examination fees collected from students',
                    'is_active' => true
                ],
                [
                    'name' => 'Library Fees',
                    'code' => 'INC-004',
                    'type' => 'Income',
                    'sub_type' => 'Library Fees',
                    'description' => 'Library membership and usage fees',
                    'is_active' => true
                ],
                [
                    'name' => 'Laboratory Fees',
                    'code' => 'INC-005',
                    'type' => 'Income',
                    'sub_type' => 'Laboratory Fees',
                    'description' => 'Lab usage and equipment fees',
                    'is_active' => true
                ],
                [
                    'name' => 'Sports Fees',
                    'code' => 'INC-006',
                    'type' => 'Income',
                    'sub_type' => 'Sports Fees',
                    'description' => 'Sports facility and activity fees',
                    'is_active' => true
                ],
                [
                    'name' => 'Transport Fees',
                    'code' => 'INC-007',
                    'type' => 'Income',
                    'sub_type' => 'Transport Fees',
                    'description' => 'School transportation fees',
                    'is_active' => true
                ],
                [
                    'name' => 'Donations',
                    'code' => 'INC-008',
                    'type' => 'Income',
                    'sub_type' => 'Donations',
                    'description' => 'Voluntary donations from parents and alumni',
                    'is_active' => true
                ],
                [
                    'name' => 'Grants & Subsidies',
                    'code' => 'INC-009',
                    'type' => 'Income',
                    'sub_type' => 'Grants',
                    'description' => 'Government grants and subsidies',
                    'is_active' => true
                ],
                [
                    'name' => 'Event Revenue',
                    'code' => 'INC-010',
                    'type' => 'Income',
                    'sub_type' => 'Other Income',
                    'description' => 'Revenue from school events and activities',
                    'is_active' => true
                ],

                // Expense Categories
                [
                    'name' => 'Teacher Salaries',
                    'code' => 'EXP-001',
                    'type' => 'Expense',
                    'sub_type' => 'Salaries',
                    'description' => 'Monthly salaries for teaching staff',
                    'is_active' => true
                ],
                [
                    'name' => 'Staff Salaries',
                    'code' => 'EXP-002',
                    'type' => 'Expense',
                    'sub_type' => 'Salaries',
                    'description' => 'Monthly salaries for non-teaching staff',
                    'is_active' => true
                ],
                [
                    'name' => 'Electricity Bills',
                    'code' => 'EXP-003',
                    'type' => 'Expense',
                    'sub_type' => 'Utilities',
                    'description' => 'Monthly electricity expenses',
                    'is_active' => true
                ],
                [
                    'name' => 'Water Bills',
                    'code' => 'EXP-004',
                    'type' => 'Expense',
                    'sub_type' => 'Utilities',
                    'description' => 'Monthly water supply expenses',
                    'is_active' => true
                ],
                [
                    'name' => 'Internet & Phone',
                    'code' => 'EXP-005',
                    'type' => 'Expense',
                    'sub_type' => 'Utilities',
                    'description' => 'Internet and telephone services',
                    'is_active' => true
                ],
                [
                    'name' => 'Building Maintenance',
                    'code' => 'EXP-006',
                    'type' => 'Expense',
                    'sub_type' => 'Maintenance',
                    'description' => 'Building repairs and maintenance',
                    'is_active' => true
                ],
                [
                    'name' => 'Equipment Maintenance',
                    'code' => 'EXP-007',
                    'type' => 'Expense',
                    'sub_type' => 'Maintenance',
                    'description' => 'Equipment and machinery maintenance',
                    'is_active' => true
                ],
                [
                    'name' => 'Office Supplies',
                    'code' => 'EXP-008',
                    'type' => 'Expense',
                    'sub_type' => 'Supplies',
                    'description' => 'Stationery and office supplies',
                    'is_active' => true
                ],
                [
                    'name' => 'Teaching Materials',
                    'code' => 'EXP-009',
                    'type' => 'Expense',
                    'sub_type' => 'Supplies',
                    'description' => 'Books, charts, and teaching aids',
                    'is_active' => true
                ],
                [
                    'name' => 'Lab Equipment',
                    'code' => 'EXP-010',
                    'type' => 'Expense',
                    'sub_type' => 'Equipment',
                    'description' => 'Laboratory equipment and chemicals',
                    'is_active' => true
                ],
                [
                    'name' => 'Computer Equipment',
                    'code' => 'EXP-011',
                    'type' => 'Expense',
                    'sub_type' => 'Equipment',
                    'description' => 'Computers and IT equipment',
                    'is_active' => true
                ],
                [
                    'name' => 'Transportation Costs',
                    'code' => 'EXP-012',
                    'type' => 'Expense',
                    'sub_type' => 'Transportation',
                    'description' => 'School bus fuel and maintenance',
                    'is_active' => true
                ],
                [
                    'name' => 'Marketing & Advertising',
                    'code' => 'EXP-013',
                    'type' => 'Expense',
                    'sub_type' => 'Marketing',
                    'description' => 'Marketing and promotional expenses',
                    'is_active' => true
                ],
                [
                    'name' => 'Staff Training',
                    'code' => 'EXP-014',
                    'type' => 'Expense',
                    'sub_type' => 'Training',
                    'description' => 'Professional development and training',
                    'is_active' => true
                ],
                [
                    'name' => 'Insurance',
                    'code' => 'EXP-015',
                    'type' => 'Expense',
                    'sub_type' => 'Insurance',
                    'description' => 'Building and liability insurance',
                    'is_active' => true
                ],
                [
                    'name' => 'Rent',
                    'code' => 'EXP-016',
                    'type' => 'Expense',
                    'sub_type' => 'Other Expenses',
                    'description' => 'Property rent payments',
                    'is_active' => true
                ],
                [
                    'name' => 'Cleaning & Sanitation',
                    'code' => 'EXP-017',
                    'type' => 'Expense',
                    'sub_type' => 'Maintenance',
                    'description' => 'Cleaning supplies and janitorial services',
                    'is_active' => true
                ],
                [
                    'name' => 'Security Services',
                    'code' => 'EXP-018',
                    'type' => 'Expense',
                    'sub_type' => 'Other Expenses',
                    'description' => 'Security personnel and systems',
                    'is_active' => true
                ],
            ];

            foreach ($categories as $category) {
                // Ensure branch_id is set (null = global)
                if (!isset($category['branch_id'])) {
                    $category['branch_id'] = null;
                }
                AccountCategory::create($category);
            }

            DB::commit();

            $this->command->info('Account categories seeded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to seed account categories: ' . $e->getMessage());
        }
    }
}

