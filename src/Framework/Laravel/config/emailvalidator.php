<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Checks
    |--------------------------------------------------------------------------
    |
    | Configure which validation checks to perform. Each check can be
    | enabled or disabled independently.
    |
    */
    'checks' => [
        'format' => true,
        'mx' => env('EMAIL_VALIDATOR_CHECK_MX', true),
        'disposable' => env('EMAIL_VALIDATOR_CHECK_DISPOSABLE', true),
        'role_based' => env('EMAIL_VALIDATOR_CHECK_ROLE_BASED', false),
        'smtp' => env('EMAIL_VALIDATOR_CHECK_SMTP', false),
        'typo_suggestion' => env('EMAIL_VALIDATOR_CHECK_TYPO', true),
        'subaddress' => env('EMAIL_VALIDATOR_CHECK_SUBADDRESS', false),
        'catch_all' => env('EMAIL_VALIDATOR_CHECK_CATCH_ALL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for email validation results. Using 'laravel' as the
    | driver will use Laravel's cache system.
    |
    | Supported drivers: "laravel", "memory", "file", "redis", "memcached", "null"
    |
    */
    'cache' => [
        'driver' => env('EMAIL_VALIDATOR_CACHE_DRIVER', 'laravel'),
        'ttl' => env('EMAIL_VALIDATOR_CACHE_TTL', 3600),
        'prefix' => 'email_validator_',
        'options' => [
            // File cache options
            // 'directory' => storage_path('framework/cache/email_validator'),

            // Redis/Memcached options (when not using Laravel driver)
            // 'host' => '127.0.0.1',
            // 'port' => 6379,
            // 'password' => null,
            // 'database' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for email validation. This prevents abuse
    | and protects against excessive DNS/SMTP queries.
    |
    */
    'rate_limit' => [
        'enabled' => env('EMAIL_VALIDATOR_RATE_LIMIT_ENABLED', false),
        'max_attempts' => env('EMAIL_VALIDATOR_RATE_LIMIT_MAX', 100),
        'decay_seconds' => env('EMAIL_VALIDATOR_RATE_LIMIT_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Lists
    |--------------------------------------------------------------------------
    |
    | Specify custom paths for blocklist, allowlist, and role-based prefix
    | files. Leave as null to use the package defaults.
    |
    */
    'lists' => [
        'blocklist_path' => null,
        'allowlist_path' => null,
        'role_based_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role-Based Email Detection
    |--------------------------------------------------------------------------
    |
    | Configure role-based email detection. These are generic email addresses
    | like admin@, info@, support@ that are typically not associated with
    | a specific person.
    |
    */
    'role_based' => [
        'prefixes' => [
            'admin', 'info', 'support', 'sales', 'contact', 'noreply',
            'no-reply', 'help', 'webmaster', 'postmaster', 'hostmaster',
            'abuse', 'billing', 'marketing', 'hr', 'jobs', 'careers',
            'press', 'media', 'office', 'team', 'hello', 'enquiries',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typo Correction
    |--------------------------------------------------------------------------
    |
    | Configure the typo correction feature. This helps catch common domain
    | misspellings like "gmial.com" instead of "gmail.com".
    |
    */
    'typo' => [
        'common_domains' => [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'icloud.com', 'aol.com', 'protonmail.com', 'mail.com',
        ],
        'mappings' => [
            // Add custom typo => correct mappings here
            // 'gmial.com' => 'gmail.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMTP Verification
    |--------------------------------------------------------------------------
    |
    | Configure SMTP verification settings. Note that SMTP verification
    | may be blocked by some mail servers or firewalls.
    |
    */
    'smtp' => [
        'timeout' => env('EMAIL_VALIDATOR_SMTP_TIMEOUT', 10),
        'from_email' => env('MAIL_FROM_ADDRESS', 'verify@example.com'),
        'from_domain' => env('MAIL_FROM_NAME', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Settings
    |--------------------------------------------------------------------------
    |
    | Configure DNS lookup settings for MX record validation.
    |
    */
    'dns' => [
        'timeout' => env('EMAIL_VALIDATOR_DNS_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for email validation. When enabled, validation
    | results will be logged using Laravel's logger.
    |
    */
    'logging' => [
        'enabled' => env('EMAIL_VALIDATOR_LOGGING_ENABLED', false),
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],
];
