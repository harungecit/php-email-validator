<?php

/**
 * PHP Email Validator - Usage Examples
 *
 * This file demonstrates how to use the email validation library.
 *
 * @package HarunGecit\EmailValidator
 * @author Harun Geçit <info@harungecit.com>
 */

require __DIR__ . '/../vendor/autoload.php';

use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Fetcher;

echo "===========================================\n";
echo "   PHP Email Validator - Usage Examples\n";
echo "===========================================\n\n";

// ================================================
// Method 1: Using the static factory method
// ================================================
echo "1. QUICK START (Factory Method)\n";
echo "--------------------------------\n";

$validator = EmailValidator::create();

$email = "user@example.com";
echo "Testing: {$email}\n";
echo "Is valid: " . ($validator->isValid($email, false) ? "Yes" : "No") . "\n\n";

// ================================================
// Method 2: Manual initialization
// ================================================
echo "2. MANUAL INITIALIZATION\n";
echo "-------------------------\n";

$blocklist = Fetcher::loadBlocklist();
$allowlist = Fetcher::loadAllowlist();
$validator = new EmailValidator($blocklist, $allowlist);

echo "Blocklist domains: " . count($blocklist) . "\n";
echo "Allowlist domains: " . count($allowlist) . "\n\n";

// ================================================
// Single Email Validation
// ================================================
echo "3. SINGLE EMAIL VALIDATION\n";
echo "---------------------------\n";

$testEmails = [
    'valid@gmail.com',
    'test@mailinator.com',
    'invalid-email',
    'user@nonexistent-domain-12345.com',
];

foreach ($testEmails as $email) {
    echo "\nEmail: {$email}\n";
    echo "  Format valid: " . ($validator->isValidFormat($email) ? "Yes" : "No") . "\n";
    echo "  Is disposable: " . ($validator->isDisposable($email) ? "Yes" : "No") . "\n";

    if ($validator->isValidFormat($email)) {
        echo "  Domain: " . $validator->extractDomain($email) . "\n";
        echo "  Local part: " . $validator->extractLocalPart($email) . "\n";
    }
}

// ================================================
// Detailed Validation
// ================================================
echo "\n\n4. DETAILED VALIDATION\n";
echo "----------------------\n";

$email = "test@mailinator.com";
$result = $validator->validateWithDetails($email, false);

echo "Email: {$email}\n";
echo "Valid: " . ($result['valid'] ? "Yes" : "No") . "\n";
echo "Format OK: " . ($result['format'] ? "Yes" : "No") . "\n";
echo "Disposable: " . ($result['disposable'] ? "Yes" : "No") . "\n";
echo "Domain: " . ($result['domain'] ?? 'N/A') . "\n";

if (!empty($result['errors'])) {
    echo "Errors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

// ================================================
// Batch Validation
// ================================================
echo "\n\n5. BATCH VALIDATION\n";
echo "-------------------\n";

$emails = [
    'user1@gmail.com',
    'user2@yahoo.com',
    'test@tempmail.com',
    'bad-format',
    'spam@guerrillamail.com',
];

echo "Validating " . count($emails) . " emails...\n\n";

$results = $validator->validateMultiple($emails, false);

foreach ($results as $email => $result) {
    $status = $result['valid'] ? "VALID" : "INVALID";
    $reasons = implode(', ', $result['errors']) ?: 'OK';
    echo sprintf("%-30s [%s] %s\n", $email, $status, $reasons);
}

// ================================================
// Filter Valid/Invalid Emails
// ================================================
echo "\n\n6. FILTERING EMAILS\n";
echo "-------------------\n";

$validEmails = $validator->filterValid($emails, false);
$invalidEmails = $validator->filterInvalid($emails, false);

echo "Valid emails:\n";
foreach ($validEmails as $email) {
    echo "  - {$email}\n";
}

echo "\nInvalid emails:\n";
foreach ($invalidEmails as $email) {
    echo "  - {$email}\n";
}

// ================================================
// Statistics
// ================================================
echo "\n\n7. STATISTICS\n";
echo "-------------\n";

$stats = $validator->getStatistics($emails, false);

echo "Total: {$stats['total']}\n";
echo "Valid: {$stats['valid']}\n";
echo "Invalid: {$stats['invalid']}\n";
echo "Invalid format: {$stats['invalid_format']}\n";
echo "Disposable: {$stats['disposable']}\n";

// ================================================
// Custom Blocklist/Allowlist
// ================================================
echo "\n\n8. CUSTOM LISTS\n";
echo "---------------\n";

$customValidator = new EmailValidator([], []);

// Add custom disposable domains
$customValidator->addToBlocklist('custom-disposable.com');
$customValidator->addMultipleToBlocklist(['temp-domain.org', 'fake-mail.net']);

echo "Custom blocklist count: " . $customValidator->getBlocklistCount() . "\n";

// Test
echo "test@custom-disposable.com is disposable: ";
echo $customValidator->isDisposable('test@custom-disposable.com') ? "Yes" : "No";
echo "\n";

// Add to allowlist (override blocklist)
$customValidator->addToAllowlist('custom-disposable.com');
echo "After adding to allowlist: ";
echo $customValidator->isDisposable('test@custom-disposable.com') ? "Yes" : "No";
echo "\n";

// ================================================
// Email Normalization
// ================================================
echo "\n\n9. EMAIL NORMALIZATION\n";
echo "----------------------\n";

$rawEmails = [
    '  USER@EXAMPLE.COM  ',
    'Test@Test.Com',
    '   admin@DOMAIN.ORG',
];

echo "Original -> Normalized:\n";
foreach ($rawEmails as $raw) {
    $normalized = $validator->normalize($raw);
    echo "'{$raw}' -> '{$normalized}'\n";
}

// ================================================
// MX Record Validation
// ================================================
echo "\n\n10. MX RECORD VALIDATION\n";
echo "------------------------\n";

$domainsToCheck = [
    'user@google.com',
    'user@microsoft.com',
    'user@nonexistent-domain-xyz123.com',
];

echo "Checking MX records (requires network):\n";
foreach ($domainsToCheck as $email) {
    $domain = $validator->extractDomain($email);
    $hasMX = $validator->hasValidMX($email);
    echo "{$domain}: " . ($hasMX ? "Has MX records" : "No MX records") . "\n";
}

// ================================================
// Using Fetcher Utilities
// ================================================
echo "\n\n11. FETCHER UTILITIES\n";
echo "---------------------\n";

echo "Blocklist file exists: " . (Fetcher::blocklistExists() ? "Yes" : "No") . "\n";
echo "Allowlist file exists: " . (Fetcher::allowlistExists() ? "Yes" : "No") . "\n";
echo "Total blocklist domains: " . Fetcher::getBlocklistCount() . "\n";
echo "Total allowlist domains: " . Fetcher::getAllowlistCount() . "\n";

// Load all lists at once
$lists = Fetcher::loadAll();
echo "Loaded both lists: blocklist(" . count($lists['blocklist']) . "), allowlist(" . count($lists['allowlist']) . ")\n";

echo "\n===========================================\n";
echo "           Examples Complete!\n";
echo "===========================================\n";
