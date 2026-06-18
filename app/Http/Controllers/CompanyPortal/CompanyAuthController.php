<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class CompanyAuthController extends Controller
{
    /**
     * Company Admin Login - Separate from school user login
     */
    public function login(Request $request)
    {
        try {
            // Enhanced rate limiting for company portal
            $key = 'company-portal-login:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Too many login attempts. Please try again in {$seconds} seconds."
                ], 429);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Sanitize input
            $email = filter_var($request->email, FILTER_SANITIZE_EMAIL);
            
            // Find user with company admin type
            $user = User::where('email', $email)
                ->where('user_type', 'CompanyAdmin')
                ->select('id', 'email', 'password', 'role', 'user_type', 'company_id', 'is_active', 'first_name', 'last_name', 'avatar')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 600); // 10 minutes
                
                Log::warning('Company portal login failed', [
                    'email' => $email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact administrator.'
                ], 403);
            }

            DB::beginTransaction();

            // Update last login
            DB::table('users')->where('id', $user->id)->update(['last_login' => now()]);
            
            // Create token with company portal scope
            $token = $user->createToken('company_portal_token', ['company-portal'], now()->addDays(30))->plainTextToken;

            DB::commit();

            RateLimiter::clear($key);

            // Load company relationship
            $user->load('company');

            Log::info('Company admin logged in successfully', ['user_id' => $user->id, 'company_id' => $user->company_id]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'company' => $user->company,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => '30 days'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company portal login error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get current company admin
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user || $user->user_type !== 'CompanyAdmin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user->load('company');

        return response()->json([
            'success' => true,
            'user' => $user,
            'company' => $user->company
        ]);
    }

    /**
     * Logout company admin
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            Log::info('Company admin logged out', ['user_id' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Company portal logout error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Update company admin profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'phone' => 'sometimes|nullable|string|max:20',
                'avatar' => 'sometimes|nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->only(['first_name', 'last_name', 'phone', 'avatar']));

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Company portal profile update error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed'
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ]
            ], [
                'password.regex' => 'Password must contain uppercase, lowercase, number and special character'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            Log::info('Company admin password changed', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Company portal password change error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Password change failed'
            ], 500);
        }
    }
}

