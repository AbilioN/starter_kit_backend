<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default destination
    |--------------------------------------------------------------------------
    |
    | Where backups go when neither the tenant nor its plan names a `backup`
    | InfrastructureProvider. This is the end of the resolution chain, not an
    | optional extra: with nothing here and no provider assigned, the run fails
    | loudly rather than skipping (see BackupDestinationException).
    |
    | Keep this bucket separate from the uploads bucket. They have different
    | lifecycles, different access patterns and very different blast radius —
    | and a backup sitting in the bucket it is meant to protect is not a backup.
    |
    */

    'default_disk' => env('BACKUP_DISK', 'backup'),

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | A dump is every row of a customer's database in one file, and it is
    | copied off-host on purpose. It is encrypted before upload.
    |
    | BACKUP_ENCRYPTION_KEY is deliberately NOT APP_KEY: sharing them means the
    | incident that takes out the application also takes out the ability to
    | read its backups. A key that exists only in the .env of the machine that
    | died is not a key — where this is escrowed has to be written down
    | somewhere other than this repository.
    |
    */

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'cipher' => 'aes-256-cbc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan policy defaults
    |--------------------------------------------------------------------------
    |
    | Used for a tenant whose plan does not set the corresponding `limits.*`
    | key, and for the landlord (which has no plan at all).
    |
    | `frequency_hours` null would mean "never" for a tenant; it is not
    | permitted as a default here for the same reason the destination chain
    | cannot end in null.
    |
    */

    'defaults' => [
        'frequency_hours' => (int) env('BACKUP_DEFAULT_FREQUENCY_HOURS', 24),
        'retention_days' => (int) env('BACKUP_DEFAULT_RETENTION_DAYS', 30),
        'max_total_mb' => env('BACKUP_DEFAULT_MAX_TOTAL_MB') !== null
            ? (int) env('BACKUP_DEFAULT_MAX_TOTAL_MB')
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health
    |--------------------------------------------------------------------------
    |
    | How far past its own schedule a tenant's last successful backup may fall
    | before `health:check` calls it stale. A multiple of the frequency, not an
    | absolute number of hours: a weekly plan is not late after 25 hours.
    |
    | This is the check that makes the difference between a backup system and a
    | directory of old files.
    |
    */

    'staleness_factor' => (float) env('BACKUP_STALENESS_FACTOR', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Dump
    |--------------------------------------------------------------------------
    |
    | mysqldump is NOT in the base php:8.2-fpm image — `default-mysql-client` is
    | installed by the Dockerfile for this. Adding it means the app, horizon and
    | scheduler images must be REBUILT, not merely restarted.
    |
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('BACKUP_MYSQL_PATH', 'mysql'),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 1800),

    /*
    |--------------------------------------------------------------------------
    | Stuck runs
    |--------------------------------------------------------------------------
    |
    | A ledger row is written `running` before the dump starts, so a process
    | killed mid-dump leaves evidence instead of nothing. Past this age, such a
    | row is a crash, not a long run.
    |
    */

    'running_timeout_minutes' => (int) env('BACKUP_RUNNING_TIMEOUT_MINUTES', 120),

];
