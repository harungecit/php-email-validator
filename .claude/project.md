# PHP Email Validator - Project Documentation

This document serves as Claude's reference for understanding and maintaining this project.

## Rules for Claude

**IMPORTANT - Git Operations:**
- **NEVER** run `git commit` - only show the command to the user
- **NEVER** run `git push` - only show the command to the user
- **NEVER** run `git tag` with push - only show the command to the user
- Always show git commands as text/code blocks for user to review and execute manually
- User will handle all git operations themselves

**Code Changes:**
- Always run tests after making changes
- Update version in composer.json when releasing
- Update CHANGELOG in README.md
- Update this documentation for major changes

## Project Overview

**Name:** harungecit/php-email-validator
**Type:** PHP Library
**Author:** Harun Geçit
**License:** MIT

A comprehensive PHP email validation library that provides:
- Email format validation
- Disposable email detection
- MX record verification
- Batch/array email validation

## Version History

### v2.0.0 (December 2025)
- **Breaking Change:** Namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`
- **Breaking Change:** Package name changed from `phporbit/php-email-validator` to `harungecit/php-email-validator`
- Added batch/array email validation support (`validateMultiple`, `filterValid`, `filterInvalid`)
- Added `isValid()` method for complete validation in one call
- Added `validateWithDetails()` for detailed validation results
- Added `getStatistics()` for batch validation statistics
- Added static factory method `EmailValidator::create()`
- Added fluent interface for list management
- Added MX record caching
- Added `hasValidDNS()` for A/AAAA record checking
- Added email normalization methods
- Added list management methods (add/remove domains)
- Enhanced Fetcher with caching, custom list loading, and merge capabilities
- Expanded test suite from 3 to 50+ test methods
- Added multi-platform CI support (Ubuntu, Windows, macOS)
- Added PHP 8.5 support

### v1.0.2 (October 2025)
- Updated blocklist and allowlist files
- Improved code quality and documentation

### v1.0.1
- Bug fixes and improvements

### v1.0.0
- Initial release

## Project Structure

```
emailvalidator/
├── .claude/                    # Claude documentation
│   └── project.md             # This file
├── .github/
│   └── workflows/
│       └── ci.yml             # GitHub Actions CI
├── data/
│   ├── blocklist.conf         # ~4940 disposable domains
│   └── allowlist.conf         # ~188 legitimate domains
├── examples/
│   └── example.php            # Usage examples
├── src/
│   ├── EmailValidator.php     # Main validation class
│   └── Fetcher.php           # List loading utility
├── tests/
│   ├── EmailValidatorTest.php # Validator tests
│   └── FetcherTest.php       # Fetcher tests
├── composer.json
├── LICENSE
└── README.md
```

## Core Classes

### EmailValidator

Main class for email validation.

**Key Methods:**
- `isValidFormat(string $email): bool` - Validates email format
- `isDisposable(string $email): bool` - Checks against blocklist
- `hasValidMX(string $email): bool` - Checks MX records
- `isValid(string $email, bool $checkMX = true): bool` - Complete validation
- `validateMultiple(array $emails, bool $checkMX = true): array` - Batch validation
- `validateWithDetails(string $email, bool $checkMX = true): array` - Detailed results
- `filterValid(array $emails, bool $checkMX = true): array` - Filter valid emails
- `filterInvalid(array $emails, bool $checkMX = true): array` - Filter invalid emails
- `getStatistics(array $emails, bool $checkMX = true): array` - Validation stats
- `extractDomain(string $email): ?string` - Get domain from email
- `extractLocalPart(string $email): ?string` - Get username from email
- `normalize(string $email): string` - Normalize email (lowercase, trim)
- `addToBlocklist/addToAllowlist` - Dynamic list management
- `removeFromBlocklist/removeFromAllowlist` - Dynamic list management

### Fetcher

Static utility class for loading domain lists.

**Key Methods:**
- `loadBlocklist(bool $useCache = true): array` - Load disposable domains
- `loadAllowlist(bool $useCache = true): array` - Load allowed domains
- `loadAll(bool $useCache = true): array` - Load both lists
- `loadCustomBlocklist/loadCustomAllowlist(string $path): array` - Custom lists
- `mergeLists(array $paths): array` - Merge multiple lists
- `saveList(string $path, array $domains): bool` - Save domains to file
- `clearCache(): void` - Clear loaded list cache
- `getBlocklistPath/getAllowlistPath(): string` - Get default paths
- `blocklistExists/allowlistExists(): bool` - Check file existence
- `getBlocklistCount/getAllowlistCount(): int` - Get domain counts

## PHP Version Support

- PHP 7.4 (minimum)
- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5 (when available)

## Platform Support

- Linux (Ubuntu)
- Windows
- macOS

## Dependencies

**Runtime:**
- PHP >= 7.4
- ext-filter

**Development:**
- PHPUnit ^9.5 || ^10.0 || ^11.0 || ^12.0

## Data Files

### blocklist.conf
Contains ~4940 disposable email domains. Format: one domain per line, lowercase.

Common domains included:
- mailinator.com
- guerrillamail.com
- tempmail.com
- yopmail.com
- 10minutemail.com

### allowlist.conf
Contains ~188 legitimate domains that might be falsely flagged. Format: one domain per line, lowercase.

Includes regional email providers like:
- 163.com (China)
- 126.com (China)
- naver.com (Korea)
- qq.com (China)

## Testing

Run tests:
```bash
composer test
```

Run with coverage:
```bash
composer test-coverage
```

Test files:
- `tests/EmailValidatorTest.php` - 50+ test methods
- `tests/FetcherTest.php` - 20+ test methods

## Common Tasks

### Adding new disposable domains
1. Edit `data/blocklist.conf`
2. Add domains (one per line, lowercase)
3. Run tests to verify

### Adding new allowed domains
1. Edit `data/allowlist.conf`
2. Add domains (one per line, lowercase)
3. Run tests to verify

### Releasing new version
1. Update version in `composer.json`
2. Update CHANGELOG in README.md
3. Update this documentation
4. Create git tag
5. Push to repository

## Code Style

- PSR-4 autoloading
- PSR-12 coding standards
- Full PHPDoc comments
- Type declarations (PHP 7.4+ style)

## Important Notes

1. **Namespace Change (v2.0.0):** The namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`. Users upgrading from v1.x must update their imports.

2. **MX Record Checks:** Require network connectivity. Can be disabled by passing `false` to validation methods.

3. **Caching:** Both Fetcher and EmailValidator implement caching for performance. Use `clearCache()` methods to reset.

4. **Allowlist Priority:** Domains in allowlist are never marked as disposable, even if also in blocklist.

5. **Case Insensitivity:** All domain comparisons are case-insensitive.
