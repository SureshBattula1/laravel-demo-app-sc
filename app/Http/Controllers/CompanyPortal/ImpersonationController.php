<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImpersonationController extends Controller
{
    /**
     * Start impersonation session
     */
    public function startImpersonation(Request $request, $userId)
    {
        try {
            $companyAdmin = $request->user();
            
            if (!$companyAdmin || !in_array($companyAdmin->user_type, ['CompanyAdmin', 'SupportStaff'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only company admins and support staff can impersonate users.'
                ], 403);
            }

            // Get user to impersonate (remote access: any company admin can impersonate any school user)
            $impersonatedUser = User::with('branch')->findOrFail($userId);

            // Check if user is a company admin (cannot impersonate other company admins)
            if ($impersonatedUser->user_type === 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot impersonate company admin users'
                ], 403);
            }

            // End any existing active session for this admin
            ImpersonationSession::where('company_admin_id', $companyAdmin->id)
                ->where('status', 'Active')
                ->update([
                    'status' => 'Ended',
                    'ended_at' => now()
                ]);

            DB::beginTransaction();

            // Create impersonation session
            $session = ImpersonationSession::create([
                'company_admin_id' => $companyAdmin->id,
                'impersonated_user_id' => $impersonatedUser->id,
                'token' => Str::random(64),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'started_at' => now(),
                'reason' => $request->reason ?? 'Virtual onboarding support',
                'status' => 'Active',
                'actions_log' => []
            ]);

            // Create impersonation token for the session
            $impersonationToken = $impersonatedUser->createToken(
                'impersonation_token',
                ['impersonated'],
                now()->addHours(8) // Impersonation sessions expire in 8 hours
            )->plainTextToken;

            DB::commit();

            // Log the action
            $session->logAction('impersonation_started', [
                'impersonated_user_id' => $impersonatedUser->id,
                'impersonated_user_email' => $impersonatedUser->email,
                'ip_address' => $request->ip()
            ]);

            Log::info('Impersonation session started', [
                'company_admin_id' => $companyAdmin->id,
                'impersonated_user_id' => $impersonatedUser->id,
                'session_id' => $session->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Impersonation session started',
                'data' => [
                    'session' => $session,
                    'impersonated_user' => $impersonatedUser,
                    'impersonation_token' => $impersonationToken,
                    'expires_at' => now()->addHours(8)->toDateTimeString()
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Impersonation start error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to start impersonation session'
            ], 500);
        }
    }

    /**
     * Stop impersonation session
     */
    public function stopImpersonation(Request $request)
    {
        try {
            $companyAdmin = $request->user();
            
            // Find active session
            $session = ImpersonationSession::where('company_admin_id', $companyAdmin->id)
                ->where('status', 'Active')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active impersonation session found'
                ], 404);
            }

            DB::beginTransaction();

            // End the session
            $session->end();

            // Revoke impersonation tokens
            $impersonatedUser = $session->impersonatedUser;
            if ($impersonatedUser) {
                $impersonatedUser->tokens()->where('name', 'impersonation_token')->delete();
            }

            DB::commit();

            // Log the action
            $session->logAction('impersonation_ended', [
                'ended_at' => now()->toDateTimeString()
            ]);

            Log::info('Impersonation session ended', [
                'session_id' => $session->id,
                'company_admin_id' => $companyAdmin->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Impersonation session ended successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Impersonation stop error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop impersonation session'
            ], 500);
        }
    }

    /**
     * Get active impersonation sessions
     */
    public function getActiveSessions(Request $request)
    {
        try {
            $companyAdmin = $request->user();
            
            $sessions = ImpersonationSession::with(['impersonatedUser'])
                ->where('company_admin_id', $companyAdmin->id)
                ->where('status', 'Active')
                ->orderBy('started_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sessions
            ]);
        } catch (\Exception $e) {
            Log::error('Get active sessions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active sessions'
            ], 500);
        }
    }

    /**
     * Get impersonation session history
     */
    public function getSessionHistory(Request $request)
    {
        try {
            $companyAdmin = $request->user();
            
            $perPage = $request->get('per_page', 15);
            
            $sessions = ImpersonationSession::with(['impersonatedUser'])
                ->where('company_admin_id', $companyAdmin->id)
                ->orderBy('started_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $sessions->items(),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get session history error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session history'
            ], 500);
        }
    }

    /**
     * Log action during impersonation
     */
    public function logAction(Request $request)
    {
        try {
            $companyAdmin = $request->user();
            
            $session = ImpersonationSession::where('company_admin_id', $companyAdmin->id)
                ->where('status', 'Active')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active impersonation session'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|string|max:255',
                'details' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $session->logAction($request->action, $request->details ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Action logged successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Log action error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to log action'
            ], 500);
        }
    }
}

