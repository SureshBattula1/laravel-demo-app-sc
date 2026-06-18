<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserPreferenceController extends Controller
{
    /**
     * Get current user's preferences
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            // Get or create preferences
            $preferences = UserPreference::firstOrCreate(
                ['user_id' => $user->id],
                UserPreference::getDefaults()
            );

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user preferences', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preferences'
            ], 500);
        }
    }

    /**
     * Update user preferences
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $user = Auth::user();

            // Validation rules
            $validator = Validator::make($request->all(), [
                'theme' => 'sometimes|string|max:50',
                'dark_mode' => 'sometimes|boolean',
                'language' => 'sometimes|string|max:10',
                'email_notifications' => 'sometimes|boolean',
                'push_notifications' => 'sometimes|boolean',
                'sms_notifications' => 'sometimes|boolean',
                'date_format' => 'sometimes|string|max:20',
                'time_format' => 'sometimes|string|max:20',
                'timezone' => 'sometimes|string|max:50',
                'items_per_page' => 'sometimes|integer|min:5|max:100',
                'dashboard_widgets' => 'sometimes|array',
                'default_view' => 'sometimes|string|max:50',
                'high_contrast' => 'sometimes|boolean',
                'font_size' => 'sometimes|string|in:small,medium,large',
                'reduce_motion' => 'sometimes|boolean',
                'additional_settings' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get or create preferences
            $preferences = UserPreference::firstOrCreate(
                ['user_id' => $user->id],
                UserPreference::getDefaults()
            );

            // Update preferences
            $preferences->updatePreferences($request->all());

            Log::info('User preferences updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($request->all())
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $preferences->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user preferences', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences'
            ], 500);
        }
    }

    /**
     * Update a single preference
     * 
     * @param Request $request
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSingle(Request $request, string $key)
    {
        try {
            $user = Auth::user();

            // Validate the key is allowed
            $allowedKeys = [
                'theme', 'dark_mode', 'language', 'email_notifications',
                'push_notifications', 'sms_notifications', 'date_format',
                'time_format', 'timezone', 'items_per_page', 'dashboard_widgets',
                'default_view', 'high_contrast', 'font_size', 'reduce_motion',
                'additional_settings'
            ];

            if (!in_array($key, $allowedKeys)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid preference key'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'value' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Value is required',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get or create preferences
            $preferences = UserPreference::firstOrCreate(
                ['user_id' => $user->id],
                UserPreference::getDefaults()
            );

            // Update the specific preference
            $preferences->updatePreference($key, $request->value);

            Log::info('User preference updated', [
                'user_id' => $user->id,
                'key' => $key,
                'value' => $request->value
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preference updated successfully',
                'data' => $preferences->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user preference', [
                'user_id' => Auth::id(),
                'key' => $key,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update preference'
            ], 500);
        }
    }

    /**
     * Reset preferences to defaults
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset()
    {
        try {
            $user = Auth::user();

            $preferences = UserPreference::firstOrCreate(
                ['user_id' => $user->id],
                UserPreference::getDefaults()
            );

            // Reset to defaults
            $preferences->update(UserPreference::getDefaults());

            Log::info('User preferences reset to defaults', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults',
                'data' => $preferences->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error resetting user preferences', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences'
            ], 500);
        }
    }
}


