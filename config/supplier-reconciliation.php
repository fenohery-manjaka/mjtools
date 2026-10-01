<?php

return [

    /*
    | How long imported data, results and decisions are kept before being
    | deleted automatically. Uploaded files themselves are never stored.
    */
    'retention_hours' => (int) env('SUPPLIER_RECONCILIATION_RETENTION_HOURS', 24),

    'limits' => [
        'max_file_kilobytes' => (int) env('SUPPLIER_RECONCILIATION_MAX_FILE_KB', 10_240),
        'max_rows' => (int) env('SUPPLIER_RECONCILIATION_MAX_ROWS', 5_000),
        'max_columns' => 100,
    ],

    /*
    | "Do this every month?" question shown after a reconciliation, used to
    | measure interest in the paid product before building it (spec §43).
    | The price is only displayed: nothing is sold yet.
    */
    'paid_intent' => [
        'price' => env('SUPPLIER_RECONCILIATION_PRICE_LABEL', '€29 / month'),
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
