<?php

// Add these routes to your main api.php file inside the auth:sanctum middleware group

use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\StudentGroupController;

// Class & Section Routes
Route::prefix('classes')->group(function () {
    Route::get('/', [ClassSectionController::class, 'index']);
    Route::get('grades', [ClassSectionController::class, 'getGrades']);
    Route::get('{grade}/sections', [ClassSectionController::class, 'getSections']);
    Route::get('{grade}/{section}/students', [ClassSectionController::class, 'getClassStudents']);
});

// Student Group Routes
Route::apiResource('student-groups', StudentGroupController::class);
Route::post('student-groups/{id}/add-member', [StudentGroupController::class, 'addMember']);
Route::delete('student-groups/{id}/members/{studentId}', [StudentGroupController::class, 'removeMember']);

