<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Job Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para logging dos jobs
    |
    */

    'process_message_job' => [
        'enabled' => env('JOB_LOGGING_ENABLED', true),
        'level' => env('JOB_LOGGING_LEVEL', 'info'),
        'channel' => env('JOB_LOGGING_CHANNEL', 'daily'),
        'max_files' => env('JOB_LOGGING_MAX_FILES', 30),
    ],

    'channels' => [
        'job_daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/jobs/jobs.log'),
            'level' => env('JOB_LOGGING_LEVEL', 'info'),
            'days' => env('JOB_LOGGING_MAX_FILES', 30),
        ],
        
        'job_single' => [
            'driver' => 'single',
            'path' => storage_path('logs/jobs/process-message.log'),
            'level' => env('JOB_LOGGING_LEVEL', 'info'),
        ],
    ],

    'formats' => [
        'process_message' => [
            'start' => '🚀 PROCESS MESSAGE JOB STARTED',
            'chat_check' => '🔍 Checking if chat exists',
            'chat_found' => '✅ Chat found',
            'chat_not_found' => '❌ Chat not found',
            'user_type_determine' => '👤 Determining user type',
            'user_type_determined' => '✅ User type determined',
            'chat_user_creating' => '🏗️ Creating ChatUser entity',
            'chat_user_created' => '✅ ChatUser entity created',
            'participant_check' => '🔍 Checking if user is participant',
            'participant_found' => '✅ User is participant',
            'participant_not_found' => '❌ User is not participant',
            'message_processing' => '📝 Processing message with use case',
            'message_processed' => '✅ Message processed successfully',
            'job_completed' => '🎉 JOB COMPLETED SUCCESSFULLY',
            'job_failed' => '💥 JOB FAILED',
            'job_retrying' => '🔄 JOB RETRYING',
        ],
    ],
];
