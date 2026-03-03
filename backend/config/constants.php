<?php

return [
    'encrypt_key' => env('encrypt_key', '3xqLmgg5NwYNG1cs'),
    'term_num_days' => env('term_num_days', 90),
    'threshhold_attendance_rate' => env('threshhold_attendance_rate', 65),
    'active_tasks_height' => env('active_tasks_height', 350),
    'guidelines_height' => env('guidelines_height', 515),
    'weekly_border_plus' => env('weekly_border_plus', 120),
     'grant_aided_plus' => env('grant_aided_plus', 200),//job on 20/04/2022
    'max_excel_upload' => env('max_excel_upload', 20000),

    'sys' => [
        'ministry_name' => env('ministry_name'),
        'program_name' => env('program_name'),
        'postal_address' => env('postal_address')
    ],

    'dms' => [
        'dms_url' => env('DMS_URL')
    ],

    'jasper' => [
        'jasper_server_url' => env('JASPER_SERVER_URL', 'http://10.0.0.12:8080/jasperserver'),
        'jasper_server_username' => env('JASPER_SERVER_USERNAME', 'jasperadmin'),
        'jasper_server_password' => env('JASPER_SERVER_PASSWORD', 'jasperadmin')
    ],

    'api' => [
        'external_api_client_id' => env('EXTERNAL_API_CLIENT_ID', 9)
    ],

    'MandE' => [
        'baseline_year' => env('BASELINE_YEAR', 2018)
    ],

    'GRM' => [
        'grm_district_lag' => env('GRM_DISTRICT_LAG', 40),
        'grm_provhq_lag' => env('GRM_PROVHQ_LAG', 40)
    ]

];
