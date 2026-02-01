<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Twilio settings for sending SMS and WhatsApp
    | messages. These credentials are used by the MessageController to send
    | messages via Twilio's API.
    |
    */

    'sid' => env('TWILIO_SID'),

    'token' => env('TWILIO_TOKEN'),

    'phone' => env('TWILIO_PHONE'),

    'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),

];

