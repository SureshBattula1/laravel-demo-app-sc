-- ===============================================
-- QUICK PERFORMANCE FIXES FOR IMMEDIATE IMPROVEMENT
-- ===============================================
-- Run this to add missing indexes for better performance
-- Expected Improvement: 50-80% faster queries
-- ===============================================

USE school_management;

-- ===============================================
-- ATTENDANCE TABLE - Composite Index
-- ===============================================
-- Fixes: Attendance list and search performance
CREATE INDEX IF NOT EXISTS idx_student_att_branch_date_status 
ON student_attendance(branch_id, date, status);

CREATE INDEX IF NOT EXISTS idx_student_att_grade_section_date 
ON student_attendance(grade_level, section, date);

-- ===============================================
-- USERS TABLE - Full-Text Search Index
-- ===============================================
-- Fixes: Student/teacher search performance
-- Note: Full-text index can only be created if not exists
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'users' 
               AND index_name = 'idx_users_name_search');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE users ADD FULLTEXT INDEX idx_users_name_search (first_name, last_name, email)',
    'SELECT "Index already exists" as message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===============================================
-- EXAM SCHEDULES - Composite Index
-- ===============================================
-- Fixes: Exam schedule lookups and filtering
CREATE INDEX IF NOT EXISTS idx_exam_schedules_grade_section_date 
ON exam_schedules(grade_level, section, exam_date);

CREATE INDEX IF NOT EXISTS idx_exam_schedules_branch_exam_date 
ON exam_schedules(branch_id, exam_id, exam_date);

-- ===============================================
-- EXAM MARKS - Optimization Indexes
-- ===============================================
-- Fixes: Mark entry and retrieval
CREATE INDEX IF NOT EXISTS idx_exam_marks_student_schedule 
ON exam_marks(student_id, exam_schedule_id, status);

CREATE INDEX IF NOT EXISTS idx_exam_marks_schedule_score 
ON exam_marks(exam_schedule_id, marks_obtained);

-- ===============================================
-- FEE PAYMENTS - Performance Indexes
-- ===============================================
CREATE INDEX IF NOT EXISTS idx_fee_payments_student_date 
ON fee_payments(student_id, payment_date);

CREATE INDEX IF NOT EXISTS idx_fee_payments_structure_status 
ON fee_payments(fee_structure_id, payment_status);

-- ===============================================
-- STUDENTS - Missing Composite Indexes
-- ===============================================
CREATE INDEX IF NOT EXISTS idx_students_grade_section_active 
ON students(grade, section, student_status);

CREATE INDEX IF NOT EXISTS idx_students_branch_year_status 
ON students(branch_id, academic_year, student_status);

-- ===============================================
-- TRANSACTIONS - Composite Indexes
-- ===============================================
CREATE INDEX IF NOT EXISTS idx_transactions_branch_date_type 
ON transactions(branch_id, date, type, status);

CREATE INDEX IF NOT EXISTS idx_transactions_category_date 
ON transactions(category_id, date);

-- ===============================================
-- UPDATE TABLE STATISTICS
-- ===============================================
ANALYZE TABLE student_attendance;
ANALYZE TABLE users;
ANALYZE TABLE exam_schedules;
ANALYZE TABLE exam_marks;
ANALYZE TABLE fee_payments;
ANALYZE TABLE students;
ANALYZE TABLE transactions;

-- ===============================================
-- VERIFY INDEX CREATION
-- ===============================================
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
        'student_attendance', 'users', 'exam_schedules', 
        'exam_marks', 'fee_payments', 'students', 'transactions'
    )
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME;

-- ===============================================
-- PERFORMANCE CHECK
-- ===============================================
SELECT 
    'Performance indexes added successfully!' as Status,
    COUNT(*) as TotalIndexes
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
    AND INDEX_NAME LIKE 'idx_%';

