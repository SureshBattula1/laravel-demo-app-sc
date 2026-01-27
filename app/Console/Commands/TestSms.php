<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class TestSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sms {mobile_number} {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending an SMS message via Twilio';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mobileNumber = $this->argument('mobile_number');
        $message = $this->argument('message') ?? 'This is a test SMS message from your school management system.';

        // Format mobile number to +91 format if needed
        if (!str_starts_with($mobileNumber, '+91')) {
            if (str_starts_with($mobileNumber, '91')) {
                $mobileNumber = '+' . $mobileNumber;
            } elseif (str_starts_with($mobileNumber, '0')) {
                $mobileNumber = '+91' . substr($mobileNumber, 1);
            } else {
                $mobileNumber = '+91' . $mobileNumber;
            }
        }

        // Validate mobile number format
        if (!preg_match('/^\+91[6-9]\d{9}$/', $mobileNumber)) {
            $this->error('Invalid mobile number format. Please use +91XXXXXXXXXX format (e.g., +919640028933)');
            return 1;
        }

        $this->info("Sending SMS to: {$mobileNumber}");
        $this->info("Message: {$message}");
        $this->newLine();

        try {
            $twilioSid = config('twilio.sid');
            $twilioToken = config('twilio.token');
            $twilioPhone = config('twilio.phone');

            if (!$twilioSid || !$twilioToken || !$twilioPhone) {
                $this->error('Twilio credentials not configured. Please check your .env file.');
                return 1;
            }

            $this->info('Twilio credentials found. Connecting to Twilio...');

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            $this->info("Sending SMS from: {$twilioPhone}");
            $this->info("Sending SMS to: {$mobileNumber}");
            $this->newLine();

            $messageObj = $twilio->messages->create(
                $mobileNumber,
                [
                    'from' => $twilioPhone,
                    'body' => $message
                ]
            );

            $this->info('✅ SMS sent successfully!');
            $this->info("Message SID: {$messageObj->sid}");
            $this->info("Status: {$messageObj->status}");

            Log::info('Test SMS sent', [
                'to' => $mobileNumber,
                'message_sid' => $messageObj->sid,
                'status' => $messageObj->status
            ]);

            return 0;

        } catch (TwilioException $e) {
            $this->error('❌ Twilio Error: ' . $e->getMessage());
            $this->error('Error Code: ' . $e->getCode());
            
            Log::error('Test SMS Twilio error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return 1;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            
            Log::error('Test SMS error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}

