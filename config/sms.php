<?php

return [

    'bulk' => [

        /*
        | When true, ProcessBulkSmsChunkJob runs in the current request (dispatchSync).
        | Use on XAMPP / local without `php artisan queue:work`. When false, you must run
        | a queue worker for database/redis queue connections.
        |
        | Override with SMS_BULK_DISPATCH_SYNC=true|false in .env
        | Default: true when APP_ENV=local, otherwise false.
        */
        'dispatch_synchronously' => filter_var(
            env(
                'SMS_BULK_DISPATCH_SYNC',
                env('APP_ENV', 'production') === 'local' ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        ),

    ],

];
