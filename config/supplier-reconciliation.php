<?php

return [

    /*
    | How long imported data, results and decisions are kept before being
    | deleted automatically. Uploaded files themselves are never stored.
    */
    'retention_hours' => (int) env('SUPPLIER_RECONCILIATION_RETENTION_HOURS', 24),

    'limits' => [
        'max_file_kilobytes' => (int) env('SUPPLIER_RECONCILIATION_MAX_FILE_KB', 10_240),
        'max_rows' => (int) env('SUPPLIER_RECONCILIATION_MAX_ROWS', 10_000),
        'max_columns' => 100,
    ],

    /*
    | Matching thresholds (see MatchingPolicy). Prudent defaults; to be
    | calibrated with real data.
    */
    'matching' => [
        'certain_max_date_days' => 14,
        'leading_zeros_max_date_days' => 7,
        'amount_only_max_date_days' => 7,
        'different_reference_max_date_days' => 3,
        'timing_window_days' => 5,
        'max_group_size' => 5,
    ],

];
