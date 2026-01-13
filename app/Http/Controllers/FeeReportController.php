<?php

namespace App\Http\Controllers;

use App\Services\FeeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeeReportController extends Controller
{
    protected $reportService;

    public function __construct(FeeReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Generate dues report grouped by fee_type
     */
    public function duesReport(Request $request)
    {
        try {
            $report = $this->reportService->duesReport($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating dues report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating dues report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate promotion-related dues report
     */
    public function promotionDuesReport(Request $request)
    {
        try {
            $report = $this->reportService->promotionDuesReport($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating promotion dues report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating promotion dues report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate collection report by fee_type
     */
    public function collectionReport(Request $request)
    {
        try {
            $report = $this->reportService->collectionReport($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating collection report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating collection report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate overdue report grouped by fee_type
     */
    public function overdueReport(Request $request)
    {
        try {
            $report = $this->reportService->overdueReport($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating overdue report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating overdue report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate student fee statement with fee_type details
     */
    public function studentStatement($studentId, Request $request)
    {
        try {
            $report = $this->reportService->studentStatement($studentId, $request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating student statement', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating student statement',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate aging analysis by fee_type
     */
    public function agingAnalysis(Request $request)
    {
        try {
            $report = $this->reportService->agingAnalysis($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating aging analysis', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error generating aging analysis',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
