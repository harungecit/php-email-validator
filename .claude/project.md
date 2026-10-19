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

Patch releases after v3.1.1 may be automated data-only releases created by
`.github/workflows/update-lists.yml` (data refreshed daily on main, released on Mondays).
Check `git tag` for the real latest version before releasing manually. The workflow skips
the automatic release when main has unreleased code changes since the last tag.

### v3.2.0 (October 2026)
- Multi-source blocklist: disposable-email-domains (trusted) + eramitgupta/disposable-email (untrusted, filtered) → ~136,500 domains
- Build filters: Public Suffix List, Tranco top-100k (untrusted only), typo domains (untrusted only), allowlist + subdomains, concatenation artifacts
- `data/blocklist.php` compiled index (used when OPcache is active), `data/metadata.json` build info
- `checkDisposable()` → `DisposableCheckResult` (matched domain + source), `isDisposableDomain()`, `isSpoofed()`, `getListStatistics()`
- New config: `lists.custom_*`, `lists.synced_blocklist_path`, `lists.block_subdomains`, `sync.*`, `format.mode`, `checks.spoof`
- List precedence: app allowlist > app blocklist > bundled allowlist > bundled/synced blocklist
- Runtime sync (`Sync\ListSynchronizer`), CLI `bin/email-validator` (check/validate/sync/stats)
- Integrations: Laravel (rules, Blade, Artisan, Boost), Symfony (constraints, commands), CodeIgniter 4, CakePHP, Yii 2, Yii 3, Phalcon 5

### v3.1.1 (September 2026)
- Blocklist updated from upstream (8,948 domains)
- `isDisposable()` matches parent domains (subdomains of disposable providers), hash-index lookups
- `scripts/update-lists.php` now builds `upstream + data/blocklist.local.conf - allowlist` with safety checks (`--force` to override the shrink guard)
- Added `.github/workflows/update-lists.yml`: weekly list update + automatic patch release

### v3.1.0 (July 2026)
- Blocklist updated from upstream (8,166 domains), allowlist expanded (199 domains, now maintained in-package)
- Added `composer update-lists` (`scripts/update-lists.php`) for one-command blocklist refresh
- Expanded and synchronized default typo mappings, common domains, and role-based prefixes across all config templates

### v3.0.0 (July 2026)
- **Requires PHP 8.0+** (dropped 7.4)
- Added `validate()` returning `ValidationResult`, `ConfigurationBuilder` with presets, config file loading (PHP/JSON/YAML)
- Added role-based, subaddress, typo suggestion, SMTP, and catch-all checks
- Added pluggable cache layer, token-bucket rate limiting, PSR-3 logging
- Added Laravel and Symfony integrations

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
│       ├── ci.yml             # GitHub Actions CI (8.1–8.5 matrix, 8.0 runtime smoke, data checks)
│       └── update-lists.yml   # Daily multi-source list rebuild + Monday patch release
├── bin/email-validator        # Framework-agnostic CLI (check/validate/sync/stats)
├── config/                    # Core config templates (PHP/JSON)
├── data/
│   ├── blocklist.conf         # ~136,500 disposable domains (generated, do not edit by hand)
│   ├── blocklist.php          # Compiled lookup index of blocklist.conf (generated)
│   ├── metadata.json          # Build info: date, sources, filter counts (generated)
│   ├── blocklist.local.conf   # Local additions (bypass popularity/typo filters)
│   └── allowlist.conf         # ~237 legitimate domains (also excludes their subdomains)
├── examples/example.php       # Usage examples
├── resources/boost/           # Laravel Boost AI guidelines
├── scripts/update-lists.php   # Multi-source build (PSL/Tranco/typo/allowlist/artifact filters)
├── src/
│   ├── EmailValidator.php     # Main validation class
│   ├── Fetcher.php            # List loading (OPcache-compiled index or explode())
│   ├── Config/                # Configuration, builder, loader (+ CodeIgniter Registrar/Services shims)
│   ├── Console/Cli.php        # CLI implementation
│   ├── Result/                # ValidationResult, SuggestionResult, DisposableCheckResult
│   ├── Support/               # Domain normalization, ListParser
│   ├── Sync/                  # ListSynchronizer, HttpDownloader, SyncResult
│   ├── Validators/            # Role-based, typo, subaddress, SMTP, catch-all
│   └── Framework/             # Laravel, Symfony, CodeIgniter, CakePHP, Yii2, Yii3, Phalcon
├── tests/                     # Unit/ and Framework/ suites
├── CONTRIBUTING.md / SECURITY.md
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

- PHP 8.0 (minimum)
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5

## Platform Support

- Linux (Ubuntu)
- Windows
- macOS

## Dependencies

**Runtime:**
- PHP >= 8.0
- ext-filter
- psr/log, psr/simple-cache

**Development:**
- PHPUnit ^9.5 || ^10.0 || ^11.0 || ^12.0

## Data Files

### blocklist.conf
Contains ~136,500 disposable email domains. Generated by `composer update-lists` as
`trusted sources + filtered untrusted sources + blocklist.local.conf - allowlist (and its subdomains)`;
refreshed daily by the `update-lists.yml` workflow (released Mondays). Format: one domain per line,
lowercase, LF, trailing newline. `blocklist.php` (compiled index) and `metadata.json` are regenerated
with it — never edit any of the three by hand.

Sources (see `SOURCES` in scripts/update-lists.php):
- disposable-email-domains/disposable-email-domains — trusted (taken as-is)
- eramitgupta/disposable-email — untrusted aggregator (includes StopForumSpam toxic domains); its
  entries are dropped when they are public suffixes, Tranco top-100k domains, typo domains of
  major providers, or concatenation artifacts. A failing untrusted source switches to additive mode.

Common domains included:
- mailinator.com
- guerrillamail.com
- tempmail.com
- yopmail.com
- 10minutemail.com

### allowlist.conf
Contains ~237 legitimate domains that might be falsely flagged (regional providers, ISPs, privacy relays such as SimpleLogin/Proton Pass/addy.io, and organisations that aggregators mislabel). Allowlisted domains and their subdomains are excluded from the blocklist build. Format: one domain per line, lowercase.

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
1. Edit `data/blocklist.local.conf` (NOT `data/blocklist.conf` — it is regenerated)
2. Add domains (one per line, lowercase)
3. Run `composer update-lists`, then run tests to verify

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
