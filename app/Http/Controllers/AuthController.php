<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Services\SchoolAccountAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register new user with security and validation
     */
    public function register(Request $request)
    {
        $actor = $request->user('sanctum');
        if (! $actor) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is closed. Ask a school administrator to create your account.',
            ], 403);
        }
        $isAdminActor = in_array($actor->role, ['SuperAdmin', 'BranchAdmin'], true);
        $allowedRoles = $isAdminActor
            ? 'SuperAdmin,BranchAdmin,Teacher,Student,Parent,Staff,Driver'
            : 'Student,Parent';

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
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
                ],
                'role' => 'required|in:'.$allowedRoles,
                'branch_id' => 'required|exists:branches,id',
            ], [
                'password.regex' => 'Password must contain uppercase, lowercase, number and special character',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ranks = [
                'SuperAdmin' => 1,
                'BranchAdmin' => 2,
                'Teacher' => 3,
                'Staff' => 3,
                'Driver' => 3,
                'Parent' => 4,
                'Student' => 4,
            ];
            $actorRank = $ranks[$actor->role] ?? 99;
            $requestedRank = $ranks[(string) $request->role] ?? 99;
            if ($requestedRank < $actorRank) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot assign a role above your own level',
                ], 403);
            }

            if (! $this->canAccessBranch($request, (int) $request->branch_id)) {
                return $this->forbiddenResponse('You do not have access to this branch');
            }

            DB::beginTransaction();

            $companyId = DB::table('branches')
                ->join('schools', 'branches.school_id', '=', 'schools.id')
                ->where('branches.id', (int) $request->branch_id)
                ->value('schools.company_id');

            // Sanitize inputs
            $user = User::createExplicit([
                'first_name' => strip_tags($request->first_name),
                'last_name' => strip_tags($request->last_name),
                'email' => filter_var($request->email, FILTER_SANITIZE_EMAIL),
                'phone' => preg_replace('/[^0-9+\-\s()]/', '', $request->phone),
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'user_type' => $request->role === 'Driver' ? 'Driver' : 'SchoolUser',
                'branch_id' => $request->branch_id,
                'company_id' => $companyId ? (int) $companyId : null,
                'is_active' => true,
            ]);

            if ($user->role === 'Driver') {
                $driverRole = \App\Models\Role::where('slug', 'driver')->first();
                if ($driverRole) {
                    $user->roles()->attach($driverRole->id, [
                        'is_primary' => true,
                        'branch_id' => $user->branch_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                \App\Models\TransportDriver::create([
                    'branch_id' => $user->branch_id,
                    'school_id' => DB::table('branches')->where('id', $user->branch_id)->value('school_id'),
                    'user_id' => $user->id,
                    'name' => $user->first_name.' '.$user->last_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'is_active' => true,
                ]);
            }

            $token = $user->createToken('auth_token', ['school-api'], now()->addHours(12))->plainTextToken;

            DB::commit();

            Log::info('User registered successfully', ['user_id' => $user->id, 'email' => $user->email]);

            // Inject effective permissions
            $user->permissions = $user->getPermissionSlugs($user->branch_id);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => '12 hours',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Login with email or phone number
     */
    public function login(Request $request)
    {
        try {
            $loginInput = $request->input('email') ?? $request->input('login');
            $key = 'login:'.Str::lower((string) $loginInput).'|'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);

                return response()->json([
                    'success' => false,
                    'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                ], 429)->header('Retry-After', (string) $seconds);
            }

            $validator = Validator::make([
                'login' => $loginInput,
                'password' => $request->password,
            ], [
                'login' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Sanitize input
            $login = strip_tags($loginInput);

            // Determine if login is email or phone
            $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            // OPTIMIZED: Select only needed columns first
            $user = User::where($loginField, $login)
                ->select('id', $loginField, 'password', 'role', 'branch_id', 'is_active', 'first_name', 'last_name', 'avatar', 'user_type_id', 'user_type', 'company_id')
                ->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 300); // 5 minutes

                Log::warning('Failed login attempt', [
                    'login' => $login,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $accountAccess = app(SchoolAccountAccessService::class);
            if ($accountAccess->isBlocked($user)) {
                return $accountAccess->deniedResponse();
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => $request->password])->save();
            }

            DB::beginTransaction();

            // OPTIMIZED: Update last_login without loading full model again
            DB::table('users')->where('id', $user->id)->update(['last_login' => now()]);

            // Create token
            $token = $user->createToken('auth_token', ['school-api'], now()->addHours(12))->plainTextToken;

            DB::commit();

            RateLimiter::clear($key);

            Log::info('User logged in successfully', ['user_id' => $user->id]);

            // OPTIMIZED: Load branch only when needed
            $user->load('branch');

            if ($user->role === 'Driver') {
                $user->load(['transportDriver.vehicles.route', 'transportDriver.branch']);
            }

            // Inject effective permissions for the frontend
            $user->permissions = $user->getPermissionSlugs($user->branch_id);

            $this->attachStudentProfileAvatar($user);

            $response = response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => '12 hours',
            ]);

            return \App\Support\SessionCookie::attach($response, $request, \App\Support\SessionCookie::SCHOOL, $token, 60 * 12);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Login error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout user and revoke token
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            Log::info('User logged out', ['user_id' => $request->user()->id]);

            $response = response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

            return \App\Support\SessionCookie::clear($response, \App\Support\SessionCookie::SCHOOL);

        } catch (\Exception $e) {
            Log::error('Logout error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
            ], 500);
        }
    }

    /**
     * Forgot password. Always returns the same message so the endpoint cannot be used to enumerate accounts.
     */
    public function forgotPassword(Request $request)
    {
        $generic = [
            'success' => true,
            'message' => 'If the account exists, a reset link was sent.',
        ];

        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = Str::lower((string) filter_var($request->email, FILTER_SANITIZE_EMAIL));
            $user = User::where('email', $email)->first();
            if ($user) {
                $resetToken = Str::random(64);
                Cache::put($this->passwordResetCacheKey($resetToken), $user->id, now()->addMinutes(60));
                try {
                    \Mail::to($user->email)->send(new \App\Mail\ResetPasswordMail($user, $resetToken));
                } catch (\Exception $mailError) {
                    Log::error('Failed to send password reset email', [
                        'user_id' => $user->id,
                        'error' => $mailError->getMessage(),
                    ]);
                }
                Log::info('Password reset requested', ['user_id' => $user->id]);
            }

            return response()->json($generic);
        } catch (\Exception $e) {
            Log::error('Forgot password error', ['error' => $e->getMessage()]);

            return response()->json($generic);
        }
    }

    /**
     * Reset password with a single-use token that expires after 60 minutes.
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
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $cacheKey = $this->passwordResetCacheKey((string) $request->token);
            $userId = Cache::pull($cacheKey);
            $user = $userId ? User::find($userId) : null;

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token',
                ], 400);
            }

            DB::beginTransaction();

            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => null,
            ])->save();
            $user->tokens()->delete();

            DB::commit();

            Log::info('Password reset successfully', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reset password error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
            ], 500);
        }
    }

    private function passwordResetCacheKey(string $token): string
    {
        return 'password-reset:'.hash('sha256', $token);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user()->load('branch');
            if ($user->role === 'Driver') {
                $user->load(['transportDriver.vehicles.route', 'transportDriver.branch']);
            }
            $user->permissions = $user->getPermissionSlugs($user->branch_id);

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            Log::error('Get user error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
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

            $blocked = array_intersect(
                ['role', 'is_active', 'password', 'branch_id', 'company_id', 'user_type', 'email'],
                array_keys($request->all())
            );
            if ($blocked !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'last_name' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'phone' => 'sometimes|string|max:20|unique:users,phone,'.$user->id.'|regex:/^[0-9+\-\s()]+$/',
                'mobile' => 'sometimes|string|max:20|regex:/^[0-9+\-\s()]+$/',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
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
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update profile error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
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
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();

            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }

            DB::beginTransaction();

            $user->forceFill(['password' => Hash::make($request->password)])->save();

            // Revoke all other tokens for security
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

            DB::commit();

            Log::info('Password changed', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Change password error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
            ], 500);
        }
    }

    private function attachStudentProfileAvatar(User $user): void
    {
        if (strcasecmp((string) $user->role, 'student') !== 0) {
            return;
        }

        $profilePath = null;
        if ($user->user_type_id) {
            $profilePath = Student::where('id', $user->user_type_id)->value('profile_picture');
        }
        if (! $profilePath) {
            $profilePath = Student::where('user_id', $user->id)->value('profile_picture');
        }

        $resolved = $this->resolvePublicStorageUrl($profilePath);
        if ($resolved) {
            $user->avatar = $resolved;
        } elseif ($user->avatar) {
            $user->avatar = $this->resolvePublicStorageUrl($user->avatar) ?? $user->avatar;
        }
    }

    private function resolvePublicStorageUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        try {
            if (Storage::disk('public')->exists($value)) {
                return Storage::disk('public')->url($value);
            }
        } catch (\Exception $e) {
            // Fall through.
        }

        if (str_starts_with($value, 'storage/')) {
            return url($value);
        }

        return url('storage/'.ltrim($value, '/'));
    }
}
