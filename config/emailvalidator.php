<?php

/**
 * Email Validator Configuration
 *
 * This is the default configuration file for the email validator.
 * Copy this file to your project and modify as needed.
 *
 * Usage:
 *   $validator = EmailValidator::fromConfigFile('path/to/emailvalidator.php');
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Checks
    |--------------------------------------------------------------------------
    |
    | Configure which validation checks to perform.
    |
    */
    'checks' => [
        'format' => true,           // Validate email format
        'mx' => true,               // Check MX records
        'disposable' => true,       // Block disposable emails
        'role_based' => false,      // Block role-based emails (admin@, info@)
        'smtp' => false,            // SMTP verification (slow, may be blocked)
        'typo_suggestion' => true,  // Suggest corrections for typos
        'subaddress' => false,      // Detect plus-addressing (user+tag@)
        'catch_all' => false,       // Detect catch-all domains
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for validation results.
    |
    | Supported drivers: "memory", "file", "redis", "memcached", "null", "psr16"
    |
    */
    'cache' => [
        'driver' => 'memory',
        'ttl' => 3600,              // Cache TTL in seconds
        'prefix' => 'email_validator_',
        'options' => [
            // File cache options
            // 'directory' => '/tmp',

            // Redis options
            // 'host' => '127.0.0.1',
            // 'port' => 6379,
            // 'password' => null,
            // 'database' => 0,

            // Memcached options
            // 'host' => '127.0.0.1',
            // 'port' => 11211,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limit validation attempts to prevent abuse.
    |
    */
    'rate_limit' => [
        'enabled' => false,
        'max_attempts' => 100,      // Maximum attempts
        'decay_seconds' => 60,      // Time window in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Lists
    |--------------------------------------------------------------------------
    |
    | Specify custom paths for blocklist and allowlist files.
    | Leave as null to use package defaults.
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
    | Configure prefixes considered as role-based emails.
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
    | Configure typo detection and correction.
    |
    */
    'typo' => [
        'common_domains' => [
            'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'icloud.com', 'aol.com', 'protonmail.com', 'proton.me', 'pm.me',
            'mail.com', 'yandex.com', 'zoho.com', 'gmx.com', 'fastmail.com',
            'live.com', 'msn.com', 'me.com', 'mac.com',
            'hey.com', 'tuta.com', 'tutanota.com',
        ],
        'mappings' => [
            // Custom typo => correct domain mappings
            // 'gmial.com' => 'gmail.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMTP Verification
    |--------------------------------------------------------------------------
    |
    | Configure SMTP verification settings.
    | Warning: May be slow or blocked by some mail servers.
    |
    */
    'smtp' => [
        'timeout' => 10,            // Connection timeout in seconds
        'from_email' => null,       // Email for MAIL FROM (null = verify@example.com)
        'from_domain' => null,      // Domain for HELO (null = auto-detect)
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Settings
    |--------------------------------------------------------------------------
    |
    | Configure DNS lookup settings.
    |
    */
    'dns' => [
        'timeout' => 5,             // DNS timeout in seconds
    ],
];
