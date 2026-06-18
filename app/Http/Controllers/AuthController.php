<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Register new user with security and validation
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'last_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'email' => 'required|string|email|max:255|unique:users',
                'phone' => 'nullable|string|max:20|unique:users|regex:/^[0-9+\-\s()]+$/',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ],
                'role' => 'required|in:SuperAdmin,BranchAdmin,Teacher,Student,Parent,Staff',
                'branch_id' => 'required|exists:branches,id'
            ], [
                'password.regex' => 'Password must contain uppercase, lowercase, number and special character'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Sanitize inputs
            $user = User::create([
                'first_name' => strip_tags($request->first_name),
                'last_name' => strip_tags($request->last_name),
                'email' => filter_var($request->email, FILTER_SANITIZE_EMAIL),
                'phone' => preg_replace('/[^0-9+\-\s()]/', '', $request->phone),
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'branch_id' => $request->branch_id,
                'is_active' => true
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

            DB::commit();

            Log::info('User registered successfully', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => '30 days'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Login with email or phone number
     */
    public function login(Request $request)
    {
        try {
            // Rate limiting
            $key = 'login:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'success' => false,
                    'message' => "Too many login attempts. Please try again in {$seconds} seconds."
                ], 429);
            }

            // Accept both 'email' and 'login' fields for compatibility
            $loginInput = $request->input('email') ?? $request->input('login');

            $validator = Validator::make([
                'login' => $loginInput,
                'password' => $request->password
            ], [
                'login' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Sanitize input
            $login = strip_tags($loginInput);
            
            // Determine if login is email or phone
            $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            
            // OPTIMIZED: Select only needed columns first
            $user = User::where($loginField, $login)
                ->select('id', $loginField, 'password', 'role', 'branch_id', 'is_active', 'first_name', 'last_name', 'avatar')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 300); // 5 minutes
                
                Log::warning('Failed login attempt', [
                    'login' => $login,
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

            // OPTIMIZED: Update last_login without loading full model again
            DB::table('users')->where('id', $user->id)->update(['last_login' => now()]);
            
            // Create token
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

            DB::commit();

            RateLimiter::clear($key);

            Log::info('User logged in successfully', ['user_id' => $user->id]);

            // OPTIMIZED: Load branch only when needed
            $user->load('branch');

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => '30 days'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Login error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Logout user and revoke token
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out', ['user_id' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Forgot password - send reset token
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $email = filter_var($request->email, FILTER_SANITIZE_EMAIL);
            $user = User::where('email', $email)->first();
            $resetToken = Str::random(60);

            $user->update(['remember_token' => $resetToken]);

            DB::commit();
            
            // Verify token was saved
            $user->refresh();
            Log::info('Reset token saved', [
                'user_id' => $user->id,
                'token_length' => strlen($resetToken),
                'token_saved' => $user->remember_token === $resetToken ? 'YES' : 'NO',
                'token_in_db' => substr($user->remember_token, 0, 10) . '...'
            ]);

            // Send password reset email
            try {
                \Mail::to($user->email)->send(new \App\Mail\ResetPasswordMail($user, $resetToken));
                Log::info('Password reset email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'mail_driver' => config('mail.default')
                ]);
            } catch (\Exception $mailError) {
                Log::error('Failed to send password reset email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailError->getMessage(),
                    'trace' => $mailError->getTraceAsString(),
                    'mail_config' => [
                        'driver' => config('mail.default'),
                        'host' => config('mail.mailers.smtp.host'),
                        'port' => config('mail.mailers.smtp.port'),
                        'username' => config('mail.mailers.smtp.username') ? 'SET' : 'NOT SET',
                        'from' => config('mail.from.address')
                    ]
                ]);
                
                // Return error if in local environment so user knows
                if (app()->environment('local')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to send email: ' . $mailError->getMessage(),
                        'reset_token' => $resetToken // Provide token for manual testing
                    ], 500);
                }
                
                // In production, don't reveal email errors but user still has token
            }

            Log::info('Password reset requested', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email',
                'reset_token' => app()->environment('local') ? $resetToken : null // Only show token in development
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Forgot password error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Request failed. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('remember_token', $request->token)->first();

            if (!$user) {
                // Debug: Check if token exists at all
                $tokenExists = User::whereNotNull('remember_token')->count();
                Log::warning('Reset token not found', [
                    'token' => $request->token,
                    'token_length' => strlen($request->token),
                    'users_with_tokens' => $tokenExists
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token',
                    'debug' => app()->environment('local') ? [
                        'token_received' => $request->token,
                        'token_length' => strlen($request->token),
                        'users_with_reset_tokens' => $tokenExists
                    ] : null
                ], 400);
            }

            DB::beginTransaction();

            $user->update([
                'password' => Hash::make($request->password),
                'remember_token' => null
            ]);

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            DB::commit();

            Log::info('Password reset successfully', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reset password error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $request->user()->load('branch')
            ]);
        } catch (\Exception $e) {
            Log::error('Get user error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'last_name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id . '|regex:/^[0-9+\-\s()]+$/',
                'mobile' => 'sometimes|string|max:20|regex:/^[0-9+\-\s()]+$/',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Sanitize inputs
            $updateData = [];
            if ($request->has('first_name')) {
                $updateData['first_name'] = strip_tags($request->first_name);
            }
            if ($request->has('last_name')) {
                $updateData['last_name'] = strip_tags($request->last_name);
            }
            if ($request->has('phone')) {
                $updateData['phone'] = preg_replace('/[^0-9+\-\s()]/', '', $request->phone);
            }
            if ($request->has('mobile')) {
                $updateData['mobile'] = preg_replace('/[^0-9+\-\s()]/', '', $request->mobile);
            }

            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $updateData['avatar'] = $avatarPath;
            }

            $user->update($updateData);

            DB::commit();

            Log::info('Profile updated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update profile error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Change password with current password verification
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'different:current_password',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            DB::beginTransaction();

            $user->update(['password' => Hash::make($request->password)]);

            // Revoke all other tokens for security
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

            DB::commit();

            Log::info('Password changed', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Change password error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
