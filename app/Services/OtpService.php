<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate a random 6-digit numeric OTP
     */
    public function generateOtp(int $length = 6): string
    {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Send OTP via Twilio SMS
     */
    public function sendOtpViaSms(User $user, string $otp): bool
    {
        try {
            $twilioSid = config('twilio.sid');
            $twilioToken = config('twilio.token');
            $twilioPhone = config('twilio.phone');

            if (!$twilioSid || !$twilioToken || !$twilioPhone) {
                Log::error('Twilio credentials not configured', [
                    'user_id' => $user->id
                ]);
                return false;
            }

            // Get user's phone number (prefer mobile, fallback to phone)
            $phoneNumber = $user->mobile ?? $user->phone;

            if (!$phoneNumber) {
                Log::error('User phone number not found', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return false;
            }

            // Format phone number to +91 format if needed
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);

            // Validate phone number format
            if (!preg_match('/^\+91[6-9]\d{9}$/', $formattedPhone)) {
                Log::error('Invalid phone number format', [
                    'user_id' => $user->id,
                    'phone' => $formattedPhone
                ]);
                return false;
            }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            $message = "Your OTP for password change is: {$otp}. Valid for 10 minutes. Do not share this code with anyone.";

            $twilio->messages->create(
                $formattedPhone,
                [
                    'from' => $twilioPhone,
                    'body' => $message
                ]
            );

            Log::info('OTP sent via SMS', [
                'user_id' => $user->id,
                'phone' => $formattedPhone
            ]);

            return true;

        } catch (TwilioException $e) {
            Log::error('Twilio SMS error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Send OTP error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verify OTP and check expiration
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        // Check if user has an OTP
        if (!$user->otp_code) {
            Log::warning('No OTP found for user', [
                'user_id' => $user->id
            ]);
            return false;
        }

        // Check if OTP matches
        if ($user->otp_code !== $otp) {
            Log::warning('OTP mismatch', [
                'user_id' => $user->id
            ]);
            return false;
        }

        // Check if OTP is expired
        if ($user->otp_expires_at) {
            // Ensure it's a Carbon instance
            $expiresAt = $user->otp_expires_at instanceof Carbon 
                ? $user->otp_expires_at 
                : Carbon::parse($user->otp_expires_at);
            
            if ($expiresAt->isPast()) {
                Log::warning('OTP expired', [
                    'user_id' => $user->id,
                    'expires_at' => $user->otp_expires_at
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Clear OTP after successful verification
     */
    public function clearOtp(User $user): void
    {
        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null
        ]);

        Log::info('OTP cleared', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Format phone number to +91 format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If already starts with +91, return as is
        if (str_starts_with($phone, '91') && strlen($phone) === 12) {
            return '+' . $phone;
        }

        // If starts with 0, remove it and add +91
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // If doesn't start with 91, add it
        if (!str_starts_with($phone, '91')) {
            $phone = '91' . $phone;
        }

        return '+' . $phone;
    }
}

