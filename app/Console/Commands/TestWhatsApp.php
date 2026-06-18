<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class TestWhatsApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:whatsapp {mobile_number} {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending a WhatsApp message via Twilio';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mobileNumber = $this->argument('mobile_number');
        $message = $this->argument('message') ?? 'This is a test message from your school management system.';

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

        $this->info("Sending WhatsApp message to: {$mobileNumber}");
        $this->info("Message: {$message}");
        $this->newLine();

        try {
            $twilioSid = config('twilio.sid');
            $twilioToken = config('twilio.token');
            $twilioWhatsAppFrom = config('twilio.whatsapp_from');

            if (!$twilioSid || !$twilioToken) {
                $this->error('Twilio credentials not configured. Please check your .env file.');
                return 1;
            }

            $this->info('Twilio credentials found. Connecting to Twilio...');

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            // Format mobile number for WhatsApp
            $toNumber = $mobileNumber;
            if (!str_starts_with($toNumber, 'whatsapp:')) {
                $toNumber = 'whatsapp:' . $toNumber;
            }

            $this->info("Sending message from: {$twilioWhatsAppFrom}");
            $this->info("Sending message to: {$toNumber}");
            $this->newLine();

            $messageObj = $twilio->messages->create(
                $toNumber,
                [
                    'from' => $twilioWhatsAppFrom,
                    'body' => $message
                ]
            );

            $this->info('✅ WhatsApp message sent successfully!');
            $this->info("Message SID: {$messageObj->sid}");
            $this->info("Status: {$messageObj->status}");

            Log::info('Test WhatsApp message sent', [
                'to' => $toNumber,
                'message_sid' => $messageObj->sid,
                'status' => $messageObj->status
            ]);

            return 0;

        } catch (TwilioException $e) {
            $this->error('❌ Twilio Error: ' . $e->getMessage());
            $this->error('Error Code: ' . $e->getCode());
            
            Log::error('Test WhatsApp Twilio error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return 1;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            
            Log::error('Test WhatsApp error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}

