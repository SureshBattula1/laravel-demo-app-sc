<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    | Master switch. When false, IDs are passed through unchanged (raw integers)
    | so the app behaves exactly as before. Flip on once the UI treats IDs as
    | opaque strings.
    */
    'enabled' => env('HASHIDS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Salt / min length / alphabet
    |--------------------------------------------------------------------------
    | The salt is the secret that makes the mapping unguessable - keep it server
    | side and stable (changing it invalidates every existing URL token).
    */
    'salt' => env('HASHIDS_SALT', env('APP_KEY', 'school-management-default-salt')),

    'min_length' => (int) env('HASHIDS_MIN_LENGTH', 12),

    'alphabet' => env(
        'HASHIDS_ALPHABET',
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'
    ),

    /*
    |--------------------------------------------------------------------------
    | ID field detection
    |--------------------------------------------------------------------------
    | A JSON/body field is treated as an ID when its key matches one of these
    | regex patterns AND it is NOT in the denylist. Output: only integer values
    | are encoded. Input: only non-numeric strings that decode cleanly are
    | replaced with their integer. This keeps string columns that merely end in
    | "_id" (tax_id, employee_id, ...) untouched.
    */
    'key_patterns' => [
        '/^id$/',
        '/_id$/',
        '/_by$/',    // created_by, updated_by, approved_by, referred_by (user FKs)
        '/^ids$/',   // bulk: { "ids": [...] }
        '/_ids$/',   // bulk: { "student_ids": [...] }
        '/Ids$/',    // bulk: { "branchIds": [...] }
    ],

    'denylist' => [
        'tax_id',
        'employee_id',
    ],

    /*
    | Route parameter names that carry an ID and should be decoded before
    | controllers run. Matched by exact name or the patterns below.
    */
    'route_param_patterns' => [
        '/^id$/',
        '/Id$/',    // templateId, etc.
        '/_id$/',
    ],

    /*
    | Request headers that carry an ID token and must be decoded to an int
    | (headers bypass the route-param/body decoding).
    */
    'headers' => [
        'X-Academic-Year-Id',
    ],

];
