# Academic Year Filtering Analysis - Attendance, Leaves, Exams

## Overview

This document analyzes where academic year filtering is missing for **Attendance**, **Leaves**, and **Exams** data, similar to the fee issue that was fixed.

## Current Status

### ✅ Fees - FIXED
- Fee payments now filter by academic year
- Fee carry-forward works correctly per academic year

### ❌ Attendance - NEEDS FIX
- `student_attendance` table HAS `academic_year` field
- Queries do NOT filter by academic year
- Shows attendance from all academic years

### ❌ Leaves - NEEDS FIX
- `student_leaves` table does NOT have `academic_year` field
- Need to add field OR filter by date range based on student's academic year

### ❌ Exams - NEEDS FIX
- Exams have `academic_year` field
- Student marks/results queries do NOT filter by academic year
- Shows exam results from all academic years

---

## 1. ATTENDANCE - Issues & Fixes

### Database Structure
**Table**: `student_attendance`
- ✅ HAS `academic_year` field (line 25 in migration)
- ✅ Indexed on `date` and `status`

### Issues Found

#### Issue 1: `getStudentAttendance()` - No Academic Year Filter
**File**: `app/Http/Controllers/AttendanceController.php` (Line 465-520)

**Current Code**:
```php
$baseQuery = DB::table('student_attendance')
    ->where('student_id', $studentId);

if (request()->has('from_date')) {
    $baseQuery->whereDate('date', '>=', request('from_date'));
}

if (request()->has('to_date')) {
    $baseQuery->whereDate('date', '<=', request('to_date'));
}
```

**Problem**:
- ❌ No academic year filter
- ❌ Returns attendance from ALL academic years
- ❌ Summary calculations include old academic year data

**Fix Required**:
```php
// Get student's current academic year
$student = Student::where('user_id', $studentId)->first();
$academicYear = $student->academic_year ?? request('academic_year');

$baseQuery = DB::table('student_attendance')
    ->where('student_id', $studentId)
    ->where('academic_year', $academicYear);  // ✅ ADD THIS
```

---

#### Issue 2: `index()` - No Academic Year Filter
**File**: `app/Http/Controllers/AttendanceController.php` (Line 25-44)

**Current Code**:
```php
$query = DB::table('student_attendance')
    ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
    // ... no academic_year filter
```

**Problem**:
- ❌ No academic year filter in main listing
- ❌ Shows attendance from all years

**Fix Required**: Add academic year filter option

---

## 2. LEAVES - Issues & Fixes

### Database Structure
**Table**: `student_leaves`
- ❌ Does NOT have `academic_year` field
- ✅ Has `from_date` and `to_date` fields

### Issues Found

#### Issue 1: `getStudentLeaves()` - No Academic Year Filter
**File**: `app/Http/Controllers/LeaveController.php` (Line 418-466)

**Current Code**:
```php
$baseQuery = DB::table('student_leaves')
    ->where('student_id', $studentId);

if (request()->has('from_date')) {
    $baseQuery->whereDate('from_date', '>=', request('from_date'));
}

if (request()->has('to_date')) {
    $baseQuery->whereDate('to_date', '<=', request('to_date'));
}
```

**Problem**:
- ❌ No academic year field in table
- ❌ No date range filtering based on academic year
- ❌ Returns leaves from all years

**Solution Options**:

**Option A: Add academic_year field to table** (Recommended)
- Create migration to add `academic_year` column
- Update existing records based on `from_date`
- Filter queries by `academic_year`

**Option B: Filter by date range** (Quick fix)
- Calculate academic year date range from student's `academic_year`
- Filter leaves where `from_date` falls within academic year range

---

## 3. EXAMS - Issues & Fixes

### Database Structure
**Tables**:
- ✅ `exams` table HAS `academic_year` field
- ✅ `exam_terms` table HAS `academic_year` field
- ✅ `exam_schedules` table HAS `academic_year` field
- ❌ `exam_marks` table does NOT have direct `academic_year` field (but can join)

### Issues Found

#### Issue 1: `getStudentMarks()` - No Academic Year Filter
**File**: `app/Http/Controllers/ExamMarkController.php` (Line 90-131)

**Current Code**:
```php
$marks = ExamMark::where('student_id', $studentId)->get();
```

**Problem**:
- ❌ No academic year filter
- ❌ Returns marks from ALL academic years
- ❌ Shows old exam results

**Fix Required**:
```php
// Join with exam_schedules and exams to filter by academic_year
$marks = ExamMark::where('exam_marks.student_id', $studentId)
    ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
    ->join('exams', 'exam_schedules.exam_id', '=', 'exams.id')
    ->where('exams.academic_year', $academicYear)  // ✅ ADD THIS
    ->select('exam_marks.*')
    ->get();
```

---

#### Issue 2: `getStudentResults()` - No Academic Year Filter
**File**: `app/Http/Controllers/ExamController.php` (Line 457-499)

**Current Code**:
```php
$results = \App\Models\ExamResult::where('student_id', $studentId)
    ->with(['exam', 'subject'])
    ->orderBy('created_at', 'desc')
    ->get();
```

**Problem**:
- ❌ No academic year filter
- ❌ Returns results from ALL academic years

**Fix Required**: Filter by exam's academic_year

---

## Implementation Plan

### Phase 1: Quick Fixes (No Database Changes)

1. **Attendance**: Add academic year filter to queries
2. **Exams**: Add academic year filter via joins
3. **Leaves**: Filter by date range based on academic year

### Phase 2: Database Enhancements (Recommended)

1. **Leaves**: Add `academic_year` field to `student_leaves` and `teacher_leaves` tables
2. **Exam Marks**: Consider adding `academic_year` field for direct filtering

---

## Files to Modify

### Immediate Fixes (No DB Changes)

1. `app/Http/Controllers/AttendanceController.php`
   - `getStudentAttendance()` - Add academic year filter
   - `index()` - Add academic year filter option

2. `app/Http/Controllers/ExamMarkController.php`
   - `getStudentMarks()` - Join and filter by academic year

3. `app/Http/Controllers/ExamController.php`
   - `getStudentResults()` - Filter by exam academic year

4. `app/Http/Controllers/LeaveController.php`
   - `getStudentLeaves()` - Filter by date range based on academic year

### Database Changes (Recommended)

1. Create migration: `add_academic_year_to_leaves_tables.php`
2. Update `StudentLeave` and `TeacherLeave` models

---

## Testing Checklist

After fixes:

1. ✅ **Attendance**: Only current academic year attendance shown
2. ✅ **Leaves**: Only current academic year leaves shown
3. ✅ **Exams**: Only current academic year exam results shown
4. ✅ **Student View**: All tabs show current academic year data only
5. ✅ **Promotion**: After promotion, new academic year data appears correctly


