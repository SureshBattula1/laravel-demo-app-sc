<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class MessageController extends Controller
{
    /**
     * Send WhatsApp message using Twilio
     */
    public function sendWhatsApp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mobile_number' => 'required|string|regex:/^\+91[6-9]\d{9}$/',
                'message' => 'required|string|min:1|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $twilioSid = config('twilio.sid');
            $twilioToken = config('twilio.token');
            $twilioWhatsAppFrom = config('twilio.whatsapp_from');

            if (!$twilioSid || !$twilioToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured'
                ], 500);
            }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            // Format mobile number for WhatsApp (ensure it starts with whatsapp:)
            $toNumber = $request->mobile_number;
            if (!str_starts_with($toNumber, 'whatsapp:')) {
                $toNumber = 'whatsapp:' . $toNumber;
            }

            $message = $twilio->messages->create(
                $toNumber,
                [
                    'from' => $twilioWhatsAppFrom,
                    'body' => $request->message
                ]
            );

            Log::info('WhatsApp message sent', [
                'to' => $toNumber,
                'message_sid' => $message->sid
            ]);

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'WhatsApp message sent successfully',
                'data' => [
                    'message_sid' => $message->sid,
                    'status' => $message->status
                ]
            ]);

        } catch (TwilioException $e) {
            Log::error('Twilio WhatsApp error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Failed to send WhatsApp message: ' . $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            Log::error('Send WhatsApp error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'An error occurred while sending WhatsApp message'
            ], 500);
        }
    }

    /**
     * Send SMS message using Twilio
     */
    public function sendSms(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mobile_number' => 'required|string|regex:/^\+91[6-9]\d{9}$/',
                'message' => 'required|string|min:1|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $twilioSid = config('twilio.sid');
            $twilioToken = config('twilio.token');
            $twilioPhone = config('twilio.phone');

            if (!$twilioSid || !$twilioToken || !$twilioPhone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio credentials not configured'
                ], 500);
            }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            $message = $twilio->messages->create(
                $request->mobile_number,
                [
                    'from' => $twilioPhone,
                    'body' => $request->message
                ]
            );

            Log::info('SMS message sent', [
                'to' => $request->mobile_number,
                'message_sid' => $message->sid
            ]);

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'message_sid' => $message->sid,
                    'status' => $message->status
                ]
            ]);

        } catch (TwilioException $e) {
            Log::error('Twilio SMS error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            Log::error('Send SMS error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'An error occurred while sending SMS'
            ], 500);
        }
    }

    /**
     * Send both WhatsApp and SMS
     */
    public function sendBoth(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mobile_number' => 'required|string|regex:/^\+91[6-9]\d{9}$/',
                'message' => 'required|string|min:1|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [
                'whatsapp' => null,
                'sms' => null
            ];

            $errors = [];

            // Send WhatsApp
            try {
                $whatsappResponse = $this->sendWhatsApp($request);
                $whatsappData = json_decode($whatsappResponse->getContent(), true);
                $results['whatsapp'] = $whatsappData['success'] ?? false;
                if (!$results['whatsapp']) {
                    $errors[] = 'WhatsApp: ' . ($whatsappData['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $results['whatsapp'] = false;
                $errors[] = 'WhatsApp: ' . $e->getMessage();
            }

            // Send SMS
            try {
                $smsResponse = $this->sendSms($request);
                $smsData = json_decode($smsResponse->getContent(), true);
                $results['sms'] = $smsData['success'] ?? false;
                if (!$results['sms']) {
                    $errors[] = 'SMS: ' . ($smsData['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $results['sms'] = false;
                $errors[] = 'SMS: ' . $e->getMessage();
            }

            // Determine overall success
            $allSuccess = $results['whatsapp'] && $results['sms'];
            $partialSuccess = $results['whatsapp'] || $results['sms'];

            if ($allSuccess) {
                return response()->json([
                    'success' => true,
                    'status' => true,
                    'message' => 'Both WhatsApp and SMS sent successfully',
                    'data' => $results
                ]);
            } elseif ($partialSuccess) {
                return response()->json([
                    'success' => true,
                    'status' => true,
                    'message' => 'Messages sent with some errors: ' . implode(', ', $errors),
                    'data' => $results,
                    'warnings' => $errors
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Failed to send messages: ' . implode(', ', $errors),
                    'data' => $results,
                    'errors' => $errors
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Send both messages error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'An error occurred while sending messages'
            ], 500);
        }
    }
}

