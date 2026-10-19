<?php

use HarunGecit\EmailValidator\Config\Configuration;

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
        // Detect mixed-script / invisible characters (homograph attacks). Requires ext-intl.
        'spoof' => env('EMAIL_VALIDATOR_CHECK_SPOOF', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Validation
    |--------------------------------------------------------------------------
    |
    | How the address format is validated:
    |
    | "filter"         PHP's FILTER_VALIDATE_EMAIL (default, ASCII only)
    | "filter_unicode" FILTER_VALIDATE_EMAIL with internationalized local parts
    | "rfc"            RFC 5322 parsing (requires egulias/email-validator)
    | "strict"         RFC 5322 without warnings (requires egulias/email-validator)
    |
    */
    'format' => [
        'mode' => env('EMAIL_VALIDATOR_FORMAT_MODE', 'filter'),
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
    | Disposable Domain Lists
    |--------------------------------------------------------------------------
    |
    | blocklist_path / allowlist_path / role_based_path replace the bundled
    | lists (null = use the package defaults). The custom_* keys add domains
    | on top of them: inline arrays or files with one domain per line (or a
    | JSON array). The allowlist always wins over the blocklists.
    |
    | synced_blocklist_path is the file written by
    | `php artisan email-validator:sync`; its domains are blocked in addition
    | to the bundled list. Set it to null to disable the synced list.
    |
    | block_subdomains also blocks subdomains of listed domains
    | (e.g. "eu.mailinator.com" when "mailinator.com" is listed).
    |
    */
    'lists' => [
        'blocklist_path' => null,
        'allowlist_path' => null,
        'role_based_path' => null,

        'custom_blocklist' => [
            // 'my-throwaway-domain.com',
        ],
        'custom_allowlist' => [
            // 'my-company.com',
        ],
        'custom_blocklist_paths' => [
            // storage_path('app/email-validator/blocklist.txt'),
        ],
        'custom_allowlist_paths' => [
            // storage_path('app/email-validator/allowlist.txt'),
        ],

        'synced_blocklist_path' => env(
            'EMAIL_VALIDATOR_SYNCED_BLOCKLIST_PATH',
            storage_path('app/email-validator/synced-blocklist.txt')
        ),

        'block_subdomains' => env('EMAIL_VALIDATOR_BLOCK_SUBDOMAINS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote List Synchronization
    |--------------------------------------------------------------------------
    |
    | `php artisan email-validator:sync` downloads these lists (plain text or
    | JSON) into lists.synced_blocklist_path, so new disposable domains are
    | blocked without waiting for a package release. The default source is
    | this package's own list, refreshed daily. Override with a comma-separated
    | EMAIL_VALIDATOR_SYNC_URLS.
    |
    | schedule: null (disabled), "hourly", "daily", "weekly", "monthly" or a
    | cron expression. When set, the sync command is registered on Laravel's
    | scheduler automatically (make sure `php artisan schedule:run` runs).
    |
    */
    'sync' => [
        'urls' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('EMAIL_VALIDATOR_SYNC_URLS', Configuration::DEFAULT_SYNC_URL))
        ))),
        'timeout' => env('EMAIL_VALIDATOR_SYNC_TIMEOUT', 30),
        'schedule' => env('EMAIL_VALIDATOR_SYNC_SCHEDULE'),
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
            'admin', 'administrator', 'info', 'support', 'sales', 'contact',
            'noreply', 'no-reply', 'donotreply', 'do-not-reply',
            'help', 'webmaster', 'postmaster', 'hostmaster',
            'abuse', 'billing', 'marketing', 'hr', 'jobs', 'careers',
            'press', 'media', 'office', 'team', 'hello', 'enquiries',
            'enquiry', 'feedback', 'newsletter', 'subscribe', 'unsubscribe',
            'security', 'privacy', 'legal', 'compliance', 'notifications',
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
            'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'icloud.com', 'aol.com', 'protonmail.com', 'proton.me', 'pm.me',
            'mail.com', 'yandex.com', 'yandex.ru', 'zoho.com', 'zohomail.com',
            'gmx.com', 'gmx.de', 'gmx.net', 'fastmail.com',
            'live.com', 'msn.com', 'me.com', 'mac.com',
            'hey.com', 'tuta.com', 'tutanota.com',
            'yahoo.co.uk', 'hotmail.co.uk', 'btinternet.com',
            'yahoo.fr', 'orange.fr', 'free.fr',
            'web.de', 't-online.de',
            'yahoo.de', 'yahoo.es', 'yahoo.it',
            'hotmail.fr', 'hotmail.de', 'hotmail.es', 'hotmail.it',
            'outlook.fr', 'outlook.de', 'outlook.es', 'outlook.it',
        ],
        'mappings' => [
            // Additional typo => correct mappings (merged with the built-in ones)
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
        'from_domain' => env('EMAIL_VALIDATOR_SMTP_FROM_DOMAIN'),
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
