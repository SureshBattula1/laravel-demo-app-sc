<?php

return [
    /** When false, assignment publish/update does not auto-create inbox rows (use Notify → Assignments campaigns). */
    'assignment_auto_inbox_notify' => (bool) env('ASSIGNMENT_AUTO_INBOX_NOTIFY', false),

    'assignment_list_max_items' => (int) env('NC_ASSIGNMENT_LIST_MAX_ITEMS', 10),
    'assignment_list_separator' => env('NC_ASSIGNMENT_LIST_SEPARATOR', '; '),

    'materialize_chunk_targets' => (int) env('NC_MATERIALIZE_TARGETS_PER_JOB', 1),
    'send_chunk_size' => (int) env('NC_SEND_CHUNK_SIZE', 150),
    'inbox_insert_chunk_size' => (int) env('NC_INBOX_INSERT_CHUNK_SIZE', 200),
    'recipient_insert_chunk_size' => (int) env('NC_RECIPIENT_INSERT_CHUNK_SIZE', 200),

    'queues' => [
        'orchestrator' => env('NC_QUEUE_ORCHESTRATOR', 'campaigns'),
        'materialize' => env('NC_QUEUE_MATERIALIZE', 'campaigns-materialize'),
        'send' => env('NC_QUEUE_SEND', 'campaigns-send'),
    ],

    'max_send_jobs_per_minute' => (int) env('NC_MAX_SEND_JOBS_PER_MINUTE', 120),
    'processing_timeout_minutes' => (int) env('NC_PROCESSING_TIMEOUT_MINUTES', 15),
    'rollup_debounce_seconds' => (int) env('NC_ROLLUP_DEBOUNCE_SECONDS', 30),
];
