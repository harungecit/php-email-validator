<?php

/*
|--------------------------------------------------------------------------
| Email Validator Validation Messages
|--------------------------------------------------------------------------
|
| Used by the rule objects (NotDisposableEmail, ValidEmail, NotRoleBasedEmail)
| and the string rules (valid_email, not_disposable, ...). Override them by
| publishing the translations:
|
|   php artisan vendor:publish --tag=emailvalidator-translations
|
| which copies this file to lang/vendor/emailvalidator/en/validation.php.
| String rules can also be overridden per rule in lang/{locale}/validation.php
| (e.g. 'not_disposable' => '...').
|
*/

return [
    'valid_email' => 'The :attribute must be a valid email address.',
    'not_disposable' => 'The :attribute must not be a disposable email address.',
    'not_role_based' => 'The :attribute must not be a role-based email address.',
    'has_mx' => 'The :attribute domain must have valid MX records.',
    'not_subaddressed' => 'The :attribute must not contain a plus sign (+).',
    'not_spoofed' => 'The :attribute must not contain mixed-script or invisible characters.',
    'smtp' => 'The :attribute mailbox could not be verified.',
    'catch_all' => 'The :attribute domain accepts every address (catch-all) and cannot be verified.',
];
