<?php

return [
    /**
     * Enable / Disable APM operations
     */
    'active' => env('ELASTIC_APM_ACTIVE', true),

    /**
     * When an error occurred
     */
    'error' => [
        /**
         * Number of files to be included in the backtrace.
         */
        'trace_depth' => env('ELASTIC_APM_TRACE_DEPTH', 30),
    ]
];
