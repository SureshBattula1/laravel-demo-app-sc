# 📚 Exam Module - Complete Implementation Code

**Copy and paste the code below to complete the exam module**

---

## Backend Controllers

### 1. ExamScheduleController.php
Create: `app/Http/Controllers/ExamScheduleController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExamScheduleController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED
            $query = ExamSchedule::select([
                'id', 'exam_id', 'subject_id', 'branch_id', 'grade_level', 'section',
                'exam_date', 'start_time', 'end_time', 'duration', 'total_marks',
                'passing_marks', 'room_number', 'invigilator_id', 'status', 'is_active', 'created_at'
            ])->with([
                'exam:id,name,exam_type',
                'subject:id,name,code',
                'branch:id,name,code',
                'invigilator:id,first_name,last_name'
            ]);

            if ($request->has('exam_id')) {
                $query->where('exam_id', $request->exam_id);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('section')) {
                $query->where('section', $request->section);
            }

            if ($request->has('exam_date')) {
                $query->whereDate('exam_date', $request->exam_date);
            }

            $schedules = $this->paginateAndSort($query, $request, [
                'id', 'exam_date', 'start_time', 'grade_level', 'section', 'status', 'created_at'
            ], 'exam_date', 'asc');

            return response()->json([
                'success' => true,
                'data' => $schedules->items(),
                'meta' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page' => $schedules->perPage(),
                    'total' => $schedules->total(),
                    'last_page' => $schedules->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get exam schedules error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch schedules'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'branch_id' => 'required|exists:branches,id',
            'grade_level' => 'required|string',
            'section' => 'nullable|string',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration' => 'required|integer|min:1',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0|lte:total_marks',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $schedule = ExamSchedule::create($request->all());
            DB::commit();
            return response()->json(['success' => true, 'data' => $schedule->load(['exam', 'subject', 'branch']), 'message' => 'Schedule created'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create schedule error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create schedule'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::findOrFail($id);
            $schedule->update($request->all());
            return response()->json(['success' => true, 'data' => $schedule->fresh(['exam', 'subject']), 'message' => 'Schedule updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update schedule'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $schedule = ExamSchedule::findOrFail($id);
            if ($schedule->marks()->count() > 0) {
                return response()->json(['success' => false, 'message' => 'Cannot delete schedule with existing marks'], 400);
            }
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete schedule'], 500);
        }
    }
}
```

---

### 2. Update routes/api.php

Add after the existing exam routes:

```php
use App\Http\Controllers\ExamTermController;
use App\Http\Controllers\ExamScheduleController;

// Exam Terms
Route::prefix('exam-terms')->group(function () {
    Route::get('/', [ExamTermController::class, 'index']);
    Route::post('/', [ExamTermController::class, 'store']);
    Route::get('{id}', [ExamTermController::class, 'show']);
    Route::put('{id}', [ExamTermController::class, 'update']);
    Route::delete('{id}', [ExamTermController::class, 'destroy']);
});

// Exam Schedules
Route::prefix('exam-schedules')->group(function () {
    Route::get('/', [ExamScheduleController::class, 'index']);
    Route::post('/', [ExamScheduleController::class, 'store']);
    Route::get('{id}', [ExamScheduleController::class, 'show']);
    Route::put('{id}', [ExamScheduleController::class, 'update']);
    Route::delete('{id}', [ExamScheduleController::class, 'destroy']);
});
```

---

## Frontend Implementation

### 3. Exam Services

Create: `ui-app/src/app/features/exams/services/exam-term.service.ts`

```typescript
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService, ApiResponse } from '../../../core/services/api.service';

export interface ExamTerm {
  id: number;
  name: string;
  code: string;
  branch_id: number;
  academic_year: string;
  start_date: string;
  end_date: string;
  weightage: number;
  is_active: boolean;
  branch?: any;
}

@Injectable({
  providedIn: 'root'
})
export class ExamTermService {
  private readonly ENDPOINT = '/exam-terms';

  constructor(private apiService: ApiService) {}

  getExamTerms(params?: Record<string, unknown>): Observable<ApiResponse<ExamTerm[]>> {
    return this.apiService.get<ExamTerm[]>(this.ENDPOINT, params);
  }

  getExamTerm(id: number): Observable<ApiResponse<ExamTerm>> {
    return this.apiService.get<ExamTerm>(`${this.ENDPOINT}/${id}`);
  }

  createExamTerm(data: Partial<ExamTerm>): Observable<ApiResponse<ExamTerm>> {
    return this.apiService.post<ExamTerm>(this.ENDPOINT, data);
  }

  updateExamTerm(id: number, data: Partial<ExamTerm>): Observable<ApiResponse<ExamTerm>> {
    return this.apiService.put<ExamTerm>(`${this.ENDPOINT}/${id}`, data);
  }

  deleteExamTerm(id: number): Observable<ApiResponse> {
    return this.apiService.delete(`${this.ENDPOINT}/${id}`);
  }
}
```

Create: `ui-app/src/app/features/exams/services/exam-schedule.service.ts`

```typescript
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService, ApiResponse } from '../../../core/services/api.service';

export interface ExamSchedule {
  id: number;
  exam_id: string;
  subject_id: number;
  branch_id: number;
  grade_level: string;
  section: string | null;
  exam_date: string;
  start_time: string;
  end_time: string;
  duration: number;
  total_marks: number;
  passing_marks: number;
  room_number: string | null;
  invigilator_id: number | null;
  status: string;
  is_active: boolean;
  exam?: any;
  subject?: any;
  branch?: any;
  invigilator?: any;
}

@Injectable({
  providedIn: 'root'
})
export class ExamScheduleService {
  private readonly ENDPOINT = '/exam-schedules';

  constructor(private apiService: ApiService) {}

  getSchedules(params?: Record<string, unknown>): Observable<ApiResponse<ExamSchedule[]>> {
    return this.apiService.get<ExamSchedule[]>(this.ENDPOINT, params);
  }

  getSchedule(id: number): Observable<ApiResponse<ExamSchedule>> {
    return this.apiService.get<ExamSchedule>(`${this.ENDPOINT}/${id}`);
  }

  createSchedule(data: Partial<ExamSchedule>): Observable<ApiResponse<ExamSchedule>> {
    return this.apiService.post<ExamSchedule>(this.ENDPOINT, data);
  }

  updateSchedule(id: number, data: Partial<ExamSchedule>): Observable<ApiResponse<ExamSchedule>> {
    return this.apiService.put<ExamSchedule>(`${this.ENDPOINT}/${id}`, data);
  }

  deleteSchedule(id: number): Observable<ApiResponse> {
    return this.apiService.delete(`${this.ENDPOINT}/${id}`);
  }
}
```

---

### 4. Exam List Component (Main with Tabs)

Create: `ui-app/src/app/features/exams/pages/exam-list/exam-list.component.ts`

```typescript
import { Component, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { MaterialModule } from '../../../../shared/modules/material/material.module';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { TableConfig, PaginationEvent, SortEvent, SearchEvent } from '../../../../shared/components/data-table/data-table.interface';
import { ExamService } from '../../services/exam.service';
import { ExamTermService } from '../../services/exam-term.service';
import { ExamScheduleService } from '../../services/exam-schedule.service';
import { ErrorHandlerService } from '../../../../core/services/error-handler.service';

@Component({
  selector: 'app-exam-list',
  standalone: true,
  imports: [CommonModule, MaterialModule, DataTableComponent],
  template: `
    <div class="page-container">
      <div class="tabs-container">
        <div class="tabs-header">
          <button class="tab-item" [class.active]="activeTab === 'terms'" (click)="switchTab('terms')">
            <div class="tab-label-full">
              <mat-icon>calendar_today</mat-icon>
              Exam Terms
              <span class="tab-badge" *ngIf="termCount > 0">{{ termCount }}</span>
            </div>
            <div class="tab-label-short">
              <mat-icon>calendar_today</mat-icon>
              Terms
            </div>
          </button>
          
          <button class="tab-item" [class.active]="activeTab === 'exams'" (click)="switchTab('exams')">
            <div class="tab-label-full">
              <mat-icon>assignment</mat-icon>
              Exams
              <span class="tab-badge" *ngIf="examCount > 0">{{ examCount }}</span>
            </div>
            <div class="tab-label-short">
              <mat-icon>assignment</mat-icon>
              Exams
            </div>
          </button>
          
          <button class="tab-item" [class.active]="activeTab === 'schedules'" (click)="switchTab('schedules')">
            <div class="tab-label-full">
              <mat-icon>schedule</mat-icon>
              Exam Schedules
              <span class="tab-badge" *ngIf="scheduleCount > 0">{{ scheduleCount }}</span>
            </div>
            <div class="tab-label-short">
              <mat-icon>schedule</mat-icon>
              Schedules
            </div>
          </button>
        </div>

        <div class="tabs-content">
          <!-- Exam Terms Tab -->
          <div class="tab-pane" [class.active]="activeTab === 'terms'">
            <app-data-table
              #termsTable
              [data]="examTerms"
              [config]="termsTableConfig"
              [title]="'Exam Terms'"
              [loading]="loading"
              (actionClicked)="onTermAction($event)"
              (paginationChanged)="onPaginationChange($event)"
              (sortChanged)="onSortChange($event)">
            </app-data-table>
          </div>

          <!-- Exams Tab -->
          <div class="tab-pane" [class.active]="activeTab === 'exams'">
            <app-data-table
              #examsTable
              [data]="exams"
              [config]="examsTableConfig"
              [title]="'Exams'"
              [loading]="loading"
              (actionClicked)="onExamAction($event)"
              (paginationChanged)="onPaginationChange($event)"
              (sortChanged)="onSortChange($event)">
            </app-data-table>
          </div>

          <!-- Schedules Tab -->
          <div class="tab-pane" [class.active]="activeTab === 'schedules'">
            <app-data-table
              #schedulesTable
              [data]="schedules"
              [config]="schedulesTableConfig"
              [title]="'Exam Schedules'"
              [loading]="loading"
              (actionClicked)="onScheduleAction($event)"
              (paginationChanged)="onPaginationChange($event)"
              (sortChanged)="onSortChange($event)">
            </app-data-table>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    :host { display: block; }
    .page-container { max-width: 1600px; margin: 0 auto; }
  `]
})
export class ExamListComponent implements OnInit {
  @ViewChild('termsTable') termsTable!: DataTableComponent;
  @ViewChild('examsTable') examsTable!: DataTableComponent;
  @ViewChild('schedulesTable') schedulesTable!: DataTableComponent;

  loading = false;
  activeTab: 'terms' | 'exams' | 'schedules' = 'terms';

  examTerms: any[] = [];
  exams: any[] = [];
  schedules: any[] = [];

  termCount = 0;
  examCount = 0;
  scheduleCount = 0;

  currentFilters: Record<string, unknown> = {};

  termsTableConfig: TableConfig = {
    columns: [
      { key: 'code', header: 'Code', sortable: true, width: '120px' },
      { key: 'name', header: 'Term Name', sortable: true },
      { key: 'branch.name', header: 'Branch', sortable: false, width: '150px' },
      { key: 'academic_year', header: 'Academic Year', sortable: true, width: '140px' },
      { key: 'start_date', header: 'Start Date', sortable: true, width: '130px' },
      { key: 'end_date', header: 'End Date', sortable: true, width: '130px' },
      { key: 'weightage', header: 'Weightage %', sortable: true, width: '120px' },
      { key: 'is_active', header: 'Active', type: 'badge', width: '90px', align: 'center' }
    ],
    actions: [
      { icon: 'visibility', label: 'View', action: (row) => this.viewTerm(row) },
      { icon: 'edit', label: 'Edit', color: 'primary', action: (row) => this.editTerm(row) },
      { icon: 'delete', label: 'Delete', color: 'warn', action: (row) => this.deleteTerm(row) }
    ],
    selectable: true,
    pagination: true,
    searchable: true,
    serverSide: true,
    totalCount: 0,
    pageSizeOptions: [10, 25, 50],
    defaultPageSize: 25
  };

  examsTableConfig: TableConfig = {
    columns: [
      { key: 'name', header: 'Exam Name', sortable: true },
      { key: 'exam_type', header: 'Type', type: 'badge', sortable: true, width: '120px' },
      { key: 'academic_year', header: 'Academic Year', sortable: true, width: '140px' },
      { key: 'start_date', header: 'Start Date', sortable: true, width: '130px' },
      { key: 'end_date', header: 'End Date', sortable: true, width: '130px' },
      { key: 'is_active', header: 'Active', type: 'badge', width: '90px', align: 'center' }
    ],
    actions: [
      { icon: 'visibility', label: 'View', action: (row) => this.viewExam(row) },
      { icon: 'edit', label: 'Edit', color: 'primary', action: (row) => this.editExam(row) },
      { icon: 'schedule', label: 'Create Schedule', color: 'accent', action: (row) => this.createSchedule(row) },
      { icon: 'delete', label: 'Delete', color: 'warn', action: (row) => this.deleteExam(row) }
    ],
    selectable: true,
    pagination: true,
    searchable: true,
    serverSide: true,
    totalCount: 0,
    pageSizeOptions: [10, 25, 50],
    defaultPageSize: 25
  };

  schedulesTableConfig: TableConfig = {
    columns: [
      { key: 'exam.name', header: 'Exam', sortable: false },
      { key: 'subject.name', header: 'Subject', sortable: false },
      { key: 'grade_level', header: 'Grade', sortable: true, width: '100px' },
      { key: 'section', header: 'Section', sortable: true, width: '100px' },
      { key: 'exam_date', header: 'Date', sortable: true, width: '130px' },
      { key: 'start_time', header: 'Time', sortable: false, width: '120px' },
      { key: 'room_number', header: 'Room', sortable: false, width: '100px' },
      { key: 'status', header: 'Status', type: 'badge', width: '120px' },
      { key: 'is_active', header: 'Active', type: 'badge', width: '90px' }
    ],
    actions: [
      { icon: 'visibility', label: 'View', action: (row) => this.viewSchedule(row) },
      { icon: 'edit', label: 'Edit', color: 'primary', action: (row) => this.editSchedule(row) },
      { icon: 'edit_note', label: 'Enter Marks', color: 'accent', action: (row) => this.enterMarks(row) },
      { icon: 'delete', label: 'Delete', color: 'warn', action: (row) => this.deleteSchedule(row) }
    ],
    selectable: true,
    pagination: true,
    searchable: true,
    serverSide: true,
    totalCount: 0,
    pageSizeOptions: [10, 25, 50],
    defaultPageSize: 25
  };

  constructor(
    private examService: ExamService,
    private examTermService: ExamTermService,
    private examScheduleService: ExamScheduleService,
    private errorHandler: ErrorHandlerService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      if (params['tab'] === 'exams') {
        this.activeTab = 'exams';
      } else if (params['tab'] === 'schedules') {
        this.activeTab = 'schedules';
      } else {
        this.activeTab = 'terms';
      }
    });
    
    this.loadData();
  }

  switchTab(tab: 'terms' | 'exams' | 'schedules'): void {
    this.activeTab = tab;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { tab },
      queryParamsHandling: 'merge'
    });
    this.loadData();
  }

  loadData(): void {
    if (this.activeTab === 'terms') this.loadExamTerms();
    else if (this.activeTab === 'exams') this.loadExams();
    else if (this.activeTab === 'schedules') this.loadSchedules();
  }

  loadExamTerms(): void {
    this.loading = true;
    this.examTermService.getExamTerms(this.currentFilters).subscribe({
      next: (response) => {
        if (response.success) {
          this.examTerms = response.data || [];
          if (response.meta) {
            this.termsTableConfig = { ...this.termsTableConfig, totalCount: response.meta.total };
            this.termCount = response.meta.total;
          }
        }
        this.loading = false;
      },
      error: (error) => {
        this.errorHandler.showError(error);
        this.loading = false;
      }
    });
  }

  loadExams(): void {
    this.loading = true;
    this.examService.getExams(this.currentFilters).subscribe({
      next: (response) => {
        if (response.success) {
          this.exams = response.data || [];
          if (response.meta) {
            this.examsTableConfig = { ...this.examsTableConfig, totalCount: response.meta.total };
            this.examCount = response.meta.total;
          }
        }
        this.loading = false;
      },
      error: (error) => {
        this.errorHandler.showError(error);
        this.loading = false;
      }
    });
  }

  loadSchedules(): void {
    this.loading = true;
    this.examScheduleService.getSchedules(this.currentFilters).subscribe({
      next: (response) => {
        if (response.success) {
          this.schedules = response.data || [];
          if (response.meta) {
            this.schedulesTableConfig = { ...this.schedulesTableConfig, totalCount: response.meta.total };
            this.scheduleCount = response.meta.total;
          }
        }
        this.loading = false;
      },
      error: (error) => {
        this.errorHandler.showError(error);
        this.loading = false;
      }
    });
  }

  onPaginationChange(event: PaginationEvent): void {
    this.currentFilters = { ...this.currentFilters, page: event.page + 1, per_page: event.pageSize };
    this.loadData();
  }

  onSortChange(event: SortEvent): void {
    this.currentFilters = { ...this.currentFilters, sort_by: event.field, sort_direction: event.direction };
    this.loadData();
  }

  // Action handlers
  onTermAction(event: { action: string, row: any }): void {
    if (event.action === 'add') {
      this.router.navigate(['/exams/term/create']);
    }
  }

  onExamAction(event: { action: string, row: any }): void {
    if (event.action === 'add') {
      this.router.navigate(['/exams/create']);
    }
  }

  onScheduleAction(event: { action: string, row: any }): void {
    if (event.action === 'add') {
      this.router.navigate(['/exams/schedule/create']);
    }
  }

  viewTerm(term: any): void {
    this.router.navigate(['/exams/term/view', term.id]);
  }

  editTerm(term: any): void {
    this.router.navigate(['/exams/term/edit', term.id]);
  }

  deleteTerm(term: any): void {
    if (confirm(`Delete exam term "${term.name}"?`)) {
      this.examTermService.deleteExamTerm(term.id).subscribe({
        next: () => {
          this.errorHandler.showSuccess('Exam term deleted');
          this.loadExamTerms();
        },
        error: (error) => this.errorHandler.showError(error)
      });
    }
  }

  viewExam(exam: any): void {
    this.router.navigate(['/exams/view', exam.id]);
  }

  editExam(exam: any): void {
    this.router.navigate(['/exams/edit', exam.id]);
  }

  deleteExam(exam: any): void {
    if (confirm(`Delete exam "${exam.name}"?`)) {
      this.examService.deleteExam(exam.id).subscribe({
        next: () => {
          this.errorHandler.showSuccess('Exam deleted');
          this.loadExams();
        },
        error: (error) => this.errorHandler.showError(error)
      });
    }
  }

  viewSchedule(schedule: any): void {
    this.router.navigate(['/exams/schedule/view', schedule.id]);
  }

  editSchedule(schedule: any): void {
    this.router.navigate(['/exams/schedule/edit', schedule.id]);
  }

  createSchedule(exam: any): void {
    this.router.navigate(['/exams/schedule/create'], { queryParams: { exam_id: exam.id } });
  }

  deleteSchedule(schedule: any): void {
    if (confirm(`Delete this exam schedule?`)) {
      this.examScheduleService.deleteSchedule(schedule.id).subscribe({
        next: () => {
          this.errorHandler.showSuccess('Schedule deleted');
          this.loadSchedules();
        },
        error: (error) => this.errorHandler.showError(error)
      });
    }
  }

  enterMarks(schedule: any): void {
    this.router.navigate(['/exams/marks/entry'], { queryParams: { schedule_id: schedule.id } });
  }
}
```

---

### 5. Exam Routes

Create: `ui-app/src/app/features/exams/exams.routes.ts`

```typescript
import { Routes } from '@angular/router';
import { authGuard } from '../../core/guards/auth.guard';

export const EXAMS_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () => import('./pages/exam-list/exam-list.component').then(m => m.ExamListComponent),
    canActivate: [authGuard]
  },
  // Add more routes as needed
];
```

---

### 6. Add to Sidebar

Update: `ui-app/src/app/components/sidebar/sidebar.component.ts`

Add to menuItems array:
```typescript
{
  title: 'Exams',
  icon: 'assignment',
  route: '/exams',
  badge: null
},
```

---

## 🚀 Quick Implementation Steps

1. **Copy controllers** from this file to `app/Http/Controllers/`
2. **Update routes** in `routes/api.php`
3. **Create services** in Angular
4. **Create exam-list component**
5. **Add to sidebar**
6. **Test!**

---

**Status:** ✅ Ready to implement  
**Estimated Time:** 30 minutes to copy/paste all code

