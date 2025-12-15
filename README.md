# HarunGecit/php-email-validator

A comprehensive PHP email validation library for checking email format, detecting disposable email addresses, validating MX records, and batch email processing.

---

## Features

- **Format Validation:** Validates email addresses against RFC standards using PHP's built-in filters
- **Disposable Email Detection:** Identifies temporary/disposable email addresses using an extensive blocklist (4,900+ domains)
- **MX Record Validation:** Verifies the existence of mail servers for email domains
- **Batch Validation:** Validate multiple emails at once with detailed results
- **Statistics:** Get validation statistics for email lists
- **Filtering:** Filter valid/invalid emails from arrays
- **Customizable Lists:** Add/remove domains from blocklist and allowlist dynamically
- **Caching:** Built-in caching for improved performance
- **Lightweight:** All dependencies and blocklists included for offline use
- **Cross-Platform:** Works on Linux, Windows, and macOS

---

## Installation

Install the package via Composer:

```bash
composer require harungecit/php-email-validator
```

---

## Compatibility

This package is compatible with the following PHP versions:

- PHP 7.4
- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5

Tested on Ubuntu, Windows, and macOS platforms.

---

## Quick Start

```php
use HarunGecit\EmailValidator\EmailValidator;

// Create validator with default lists
$validator = EmailValidator::create();

// Validate a single email
if ($validator->isValid('user@example.com')) {
    echo "Email is valid!";
}
```

---

## Usage

### Basic Example

```php
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Fetcher;

// Load blocklist and allowlist from the package
$blocklist = Fetcher::loadBlocklist();
$allowlist = Fetcher::loadAllowlist();

// Initialize the validator
$validator = new EmailValidator($blocklist, $allowlist);

$email = "example@10minutemail.com";

// Perform validations
if (!$validator->isValidFormat($email)) {
    echo "Invalid email format.";
} elseif ($validator->isDisposable($email)) {
    echo "Disposable email detected.";
} elseif (!$validator->hasValidMX($email)) {
    echo "Invalid MX record.";
} else {
    echo "Email is valid.";
}
```

### Complete Validation (One-liner)

```php
$validator = EmailValidator::create();

// Validates format, checks blocklist, and verifies MX records
if ($validator->isValid('user@example.com')) {
    echo "Email passed all checks!";
}

// Skip MX check for faster validation
if ($validator->isValid('user@example.com', false)) {
    echo "Email passed format and blocklist checks!";
}
```

### Detailed Validation Results

```php
$result = $validator->validateWithDetails('user@mailinator.com');

// Result structure:
// [
//     'valid' => false,
//     'format' => true,
//     'disposable' => true,
//     'mx' => null,
//     'domain' => 'mailinator.com',
//     'errors' => ['Disposable email address']
// ]

if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo "Error: $error\n";
    }
}
```

### Batch Validation

```php
$emails = [
    'user1@gmail.com',
    'user2@mailinator.com',
    'invalid-email',
    'user3@example.com',
];

// Validate all emails at once
$results = $validator->validateMultiple($emails, false);

foreach ($results as $email => $result) {
    $status = $result['valid'] ? 'VALID' : 'INVALID';
    echo "$email: $status\n";
}
```

### Filter Valid/Invalid Emails

```php
$emails = ['valid@gmail.com', 'bad@tempmail.com', 'invalid', 'ok@example.com'];

// Get only valid emails
$validEmails = $validator->filterValid($emails, false);
// Result: ['valid@gmail.com', 'ok@example.com']

// Get only invalid emails
$invalidEmails = $validator->filterInvalid($emails, false);
// Result: ['bad@tempmail.com', 'invalid']
```

### Get Validation Statistics

```php
$emails = ['a@gmail.com', 'b@tempmail.com', 'invalid', 'c@example.com'];

$stats = $validator->getStatistics($emails, false);

// Result:
// [
//     'total' => 4,
//     'valid' => 2,
//     'invalid' => 2,
//     'disposable' => 1,
//     'invalid_format' => 1,
//     'no_mx' => 0
// ]
```

### Custom Blocklist/Allowlist

```php
$validator = new EmailValidator([], []);

// Add domains to blocklist
$validator->addToBlocklist('custom-disposable.com');
$validator->addMultipleToBlocklist(['temp1.com', 'temp2.com']);

// Add domains to allowlist (takes priority over blocklist)
$validator->addToAllowlist('allowed-domain.com');

// Remove domains
$validator->removeFromBlocklist('temp1.com');
$validator->removeFromAllowlist('allowed-domain.com');

// Get lists
$blocklist = $validator->getBlocklist();
$allowlist = $validator->getAllowlist();

// Get counts
echo "Blocklist: " . $validator->getBlocklistCount() . " domains\n";
echo "Allowlist: " . $validator->getAllowlistCount() . " domains\n";
```

### Email Normalization

```php
// Normalize single email (lowercase, trim)
$normalized = $validator->normalize('  USER@EXAMPLE.COM  ');
// Result: 'user@example.com'

// Normalize multiple emails
$normalized = $validator->normalizeMultiple(['USER@A.COM', 'Test@B.Com']);
// Result: ['user@a.com', 'test@b.com']
```

### Extract Email Parts

```php
$email = 'user.name+tag@sub.example.com';

$domain = $validator->extractDomain($email);
// Result: 'sub.example.com'

$localPart = $validator->extractLocalPart($email);
// Result: 'user.name+tag'
```

### Using Fetcher Utilities

```php
use HarunGecit\EmailValidator\Fetcher;

// Load both lists at once
$lists = Fetcher::loadAll();
$blocklist = $lists['blocklist'];
$allowlist = $lists['allowlist'];

// Load custom lists
$customBlocklist = Fetcher::loadCustomBlocklist('/path/to/custom.conf');

// Merge multiple lists
$merged = Fetcher::mergeLists(['/path/to/list1.conf', '/path/to/list2.conf']);

// Save a list
Fetcher::saveList('/path/to/output.conf', ['domain1.com', 'domain2.com']);

// Check file existence
if (Fetcher::blocklistExists()) {
    echo "Blocklist has " . Fetcher::getBlocklistCount() . " domains\n";
}

// Clear cache (useful for testing or reloading)
Fetcher::clearCache();
```

---

## How It Works

1. **Email Format Validation:**
   - Uses PHP's `filter_var` function with `FILTER_VALIDATE_EMAIL`
   - Example: `test@example.com` is valid, `test@com` is not

2. **Disposable Email Detection:**
   - Checks the domain against a blocklist of 4,900+ known disposable providers
   - Allowlist takes priority (domains in allowlist are never marked as disposable)
   - Example: `user@mailinator.com` is marked as disposable

3. **MX Record Validation:**
   - Uses `checkdnsrr` function to verify mail server existence
   - Results are cached for performance
   - Example: `example.com` with a valid mail server passes validation

---

## Blocklist and Allowlist

The package comes with preloaded blocklist and allowlist files located in the `data/` directory.

- **Blocklist (`blocklist.conf`):** Contains 4,900+ domains of known disposable email providers
- **Allowlist (`allowlist.conf`):** Contains 180+ domains that should always be considered valid

### File Format

One domain per line, lowercase:
```
mailinator.com
guerrillamail.com
tempmail.com
```

You can customize these files or load custom lists using the Fetcher class.

---

## API Reference

### EmailValidator Class

| Method | Description |
|--------|-------------|
| `create(): self` | Static factory method with default lists |
| `isValidFormat(string $email): bool` | Validate email format |
| `isDisposable(string $email): bool` | Check if email is disposable |
| `hasValidMX(string $email): bool` | Check MX records |
| `hasValidDNS(string $email): bool` | Check A/AAAA records |
| `isValid(string $email, bool $checkMX = true): bool` | Complete validation |
| `validateMultiple(array $emails, bool $checkMX = true): array` | Batch validation |
| `validateWithDetails(string $email, bool $checkMX = true): array` | Detailed results |
| `filterValid(array $emails, bool $checkMX = true): array` | Filter valid emails |
| `filterInvalid(array $emails, bool $checkMX = true): array` | Filter invalid emails |
| `getStatistics(array $emails, bool $checkMX = true): array` | Get statistics |
| `extractDomain(string $email): ?string` | Extract domain |
| `extractLocalPart(string $email): ?string` | Extract local part |
| `normalize(string $email): string` | Normalize email |
| `normalizeMultiple(array $emails): array` | Normalize multiple |
| `isAllowlisted(string $email): bool` | Check if in allowlist |
| `isBlocklisted(string $email): bool` | Check if in blocklist |
| `addToBlocklist(string $domain): self` | Add to blocklist |
| `addMultipleToBlocklist(array $domains): self` | Add multiple to blocklist |
| `addToAllowlist(string $domain): self` | Add to allowlist |
| `addMultipleToAllowlist(array $domains): self` | Add multiple to allowlist |
| `removeFromBlocklist(string $domain): self` | Remove from blocklist |
| `removeFromAllowlist(string $domain): self` | Remove from allowlist |
| `getBlocklist(): array` | Get blocklist |
| `getAllowlist(): array` | Get allowlist |
| `getBlocklistCount(): int` | Get blocklist count |
| `getAllowlistCount(): int` | Get allowlist count |
| `setCacheEnabled(bool $enabled): self` | Enable/disable MX caching |
| `clearCache(): self` | Clear MX cache |

### Fetcher Class

| Method | Description |
|--------|-------------|
| `loadBlocklist(bool $useCache = true): array` | Load blocklist |
| `loadAllowlist(bool $useCache = true): array` | Load allowlist |
| `loadAll(bool $useCache = true): array` | Load both lists |
| `loadCustomBlocklist(string $path): array` | Load custom blocklist |
| `loadCustomAllowlist(string $path): array` | Load custom allowlist |
| `mergeLists(array $paths): array` | Merge multiple lists |
| `saveList(string $path, array $domains): bool` | Save list to file |
| `clearCache(): void` | Clear loaded cache |
| `getBlocklistPath(): string` | Get blocklist path |
| `getAllowlistPath(): string` | Get allowlist path |
| `blocklistExists(): bool` | Check blocklist exists |
| `allowlistExists(): bool` | Check allowlist exists |
| `getBlocklistCount(): int` | Get blocklist count |
| `getAllowlistCount(): int` | Get allowlist count |

---

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

---

## Upgrading from v1.x

If you're upgrading from v1.x, note these breaking changes:

1. **Namespace Change:**
   ```php
   // Old (v1.x)
   use PHPOrbit\EmailValidator\EmailValidator;

   // New (v2.x)
   use HarunGecit\EmailValidator\EmailValidator;
   ```

2. **Package Name Change:**
   ```bash
   # Old
   composer require phporbit/php-email-validator

   # New
   composer require harungecit/php-email-validator
   ```

---

## Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

Please ensure that all new code:
- Has comprehensive tests
- Follows PSR-12 coding standards
- Includes PHPDoc comments

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Author

**Harun Geçit**

- [GitHub](https://github.com/harungecit)
- [LinkedIn](https://linkedin.com/in/harungecit)
- [Email](mailto:info@harungecit.com)

---

## Changelog

### v2.0.0
- **Breaking:** Namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`
- **Breaking:** Package name changed from `phporbit/php-email-validator` to `harungecit/php-email-validator`
- Added batch email validation (`validateMultiple`, `filterValid`, `filterInvalid`)
- Added `isValid()` method for complete validation
- Added `validateWithDetails()` for detailed results
- Added `getStatistics()` for validation statistics
- Added static factory method `EmailValidator::create()`
- Added fluent interface for list management
- Added MX record caching with `setCacheEnabled()` and `clearCache()`
- Added `hasValidDNS()` for A/AAAA record checking
- Added email normalization methods
- Added `isAllowlisted()` and `isBlocklisted()` methods
- Enhanced Fetcher with caching, custom list loading, and merge capabilities
- Expanded test suite with comprehensive coverage
- Added multi-platform CI support (Ubuntu, Windows, macOS)
- Added PHP 8.5 support

### v1.0.2
- Updated blocklist and allowlist files
- Improved code quality and documentation

### v1.0.1
- Bug fixes and improvements

### v1.0.0
- Initial release with support for:
  - Email format validation
  - Disposable email detection
  - MX record validation
