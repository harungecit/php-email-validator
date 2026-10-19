<?php

/**
 * CodeIgniter 4 language lines for the EmailValidatorRules rule set.
 *
 * CodeIgniter discovers `Language/<locale>/Validation.php` in every namespace
 * (this package's root is src/) and merges the lines; lines in the
 * application's own app/Language/<locale>/Validation.php take precedence.
 * Because these lines exist, CodeIgniter formats the messages itself, so the
 * {field} / {value} placeholders and per-field custom errors from setRules()
 * work. `verified_email` is intentionally not listed: its default message
 * names the check that failed (add a line in your app to override it).
 *
 * This file is only read by CodeIgniter; other frameworks ignore it.
 */

return [
    'not_disposable_email' => 'The {field} field must not be a disposable (temporary) email address.',
    'email_has_mx' => 'The {field} field must use a domain that can receive email.',
    'not_role_based_email' => 'The {field} field must not be a role-based address such as info@ or admin@.',
    'not_subaddressed_email' => 'The {field} field must not contain a "+" tag.',
    'not_spoofed_email' => 'The {field} field contains mixed-script or invisible characters.',
];
