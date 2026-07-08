# HarunGecit/php-email-validator

[![PHP Version](https://img.shields.io/packagist/php-v/harungecit/php-email-validator?style=flat-square&logo=php&logoColor=white)](https://packagist.org/packages/harungecit/php-email-validator)
[![Latest Version](https://img.shields.io/packagist/v/harungecit/php-email-validator?style=flat-square&logo=packagist&logoColor=white&label=version)](https://packagist.org/packages/harungecit/php-email-validator)
[![Total Downloads](https://img.shields.io/packagist/dt/harungecit/php-email-validator?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/harungecit/php-email-validator)
[![License](https://img.shields.io/packagist/l/harungecit/php-email-validator?style=flat-square&logo=opensource&logoColor=white)](LICENSE)
[![CI Status](https://img.shields.io/github/actions/workflow/status/harungecit/php-email-validator/ci.yml?branch=main&style=flat-square&logo=github&logoColor=white&label=tests)](https://github.com/harungecit/php-email-validator/actions)
[![Code Quality](https://img.shields.io/badge/code%20quality-A-brightgreen?style=flat-square&logo=codacy&logoColor=white)]()
[![Maintenance](https://img.shields.io/badge/maintained-yes-green?style=flat-square&logo=git&logoColor=white)]()

A comprehensive, framework-friendly PHP email validation library: format and RFC checks, disposable-domain detection, MX/DNS verification, role-based detection, plus-addressing (subaddress) handling, typo suggestions, SMTP and catch-all verification, batch validation, pluggable caching and rate limiting — all configurable through a fluent builder.

[![Blocklist Domains](https://img.shields.io/badge/blocklist-7,900%2B%20domains-red?style=flat-square&logo=shield&logoColor=white)]()
[![Allowlist Domains](https://img.shields.io/badge/allowlist-180%2B%20domains-green?style=flat-square&logo=shield&logoColor=white)]()
[![Platform Support](https://img.shields.io/badge/platform-Linux%20%7C%20Windows%20%7C%20macOS-blue?style=flat-square&logo=windows&logoColor=white)]()

---

## Features

- **Format Validation** — RFC-compliant format checks via PHP's built-in filters.
- **Disposable Email Detection** — an extensive bundled blocklist (7,900+ domains, sourced from [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)) with an allowlist override.
- **MX & DNS Verification** — verify that the domain has mail servers (MX) or A/AAAA records.
- **Role-Based Detection** — flag addresses like `info@`, `admin@`, `support@`.
- **Subaddress (Plus Addressing)** — detect `user+tag@gmail.com`, extract the base address, compare equivalence.
- **Typo Suggestions** — "did you mean `gmail.com`?" for common provider typos, with a confidence score.
- **SMTP & Catch-All Verification** — optional live mailbox and catch-all domain checks.
- **Rich Result Object** — `ValidationResult` with per-check status, errors, warnings, metadata, and JSON export.
- **Pluggable Cache** — Memory, File, Redis, Memcached, PSR-16, or Null adapters.
- **Rate Limiting** — token-bucket limiter to throttle validation attempts.
- **Fluent Configuration** — `ConfigurationBuilder` with `strict`, `standard`, `basic`, and `minimal` presets, or load from PHP/JSON/YAML files.
- **Framework Integration** — first-class Laravel and Symfony support.
- **PSR-3 Logging** — inject any PSR-3 logger.
- **Batch Tools** — validate lists, filter valid/invalid, gather statistics.
- **Backward Compatible** — the 2.x API (`isValid`, `validateWithDetails`, `validateMultiple`, list management) still works unchanged.

---

## Installation

```bash
composer require harungecit/php-email-validator
```

---

## Compatibility

- **Requires PHP 8.0 or newer.** Verified on PHP **8.0, 8.1, 8.2, 8.3, 8.4** and **8.5**.
- Tested on Ubuntu, Windows, and macOS.
- Optional integrations (install as needed): `illuminate/support` (Laravel), `symfony/http-kernel` + `symfony/dependency-injection` (Symfony), `ext-redis`/`predis/predis` (Redis cache), `ext-memcached` (Memcached cache), `ext-intl` (IDN), `symfony/yaml` or `ext-yaml` (YAML config).

> The library itself runs on PHP 8.0+. Running the **test suite** requires PHP 8.1+ (PHPUnit 10+), since the tests use attribute-based data providers.

---

## Quick Start

```php
use HarunGecit\EmailValidator\EmailValidator;

$validator = EmailValidator::create();

// Simple boolean check (format + disposable + MX by default)
if ($validator->isValid('user@example.com')) {
    echo "Email is valid!";
}
```

Need a detailed report instead of a boolean? Use `validate()`:

```php
$result = $validator->validate('user@example.com');

$result->isValid();      // bool
$result->getErrors();    // string[]
$result->getChecks();    // ['format' => true, 'disposable' => true, 'mx' => true, ...]
```

---

## Configuration

Everything is driven by a `Configuration` object. The easiest way to build one is the fluent `ConfigurationBuilder`.

```php
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;

$config = ConfigurationBuilder::create()
    ->standard()                       // start from a preset
    ->withRoleBasedCheck()             // then customize
    ->withTypoSuggestion()
    ->withFileCache(__DIR__ . '/cache', 3600)
    ->withRateLimiting(100, 60)
    ->build();

$validator = EmailValidator::withConfig($config);
```

### Presets

| Preset | Enabled checks |
|--------|----------------|
| `minimal()` | format |
| `basic()` | format, disposable |
| `standard()` | format, mx, disposable, typo suggestion |
| `strict()` | format, mx, disposable, role-based, subaddress, typo suggestion, **smtp**, **catch-all** |

```php
$validator = EmailValidator::strict();   // shortcut for ConfigurationBuilder::create()->strict()->build()
$validator = EmailValidator::basic();    // shortcut for the basic() preset
```

> **Note:** `strict()` enables SMTP and catch-all verification, which perform live network connections and can be slow or blocked by mail servers. For most applications `standard()` is the recommended starting point.

### Individual toggles

Every check has an `enable*` method on `Configuration` and a `with*`/`without*` method on the builder:

```php
$config = ConfigurationBuilder::create()
    ->withoutMxCheck()          // disable MX lookups
    ->withoutDisposableCheck()  // allow disposable domains
    ->withSubaddressCheck()     // detect plus addressing
    ->withCatchAllCheck()       // detect catch-all domains
    ->withSmtpCheck(10, 'verify@your-domain.com')
    ->build();
```

Check defaults: `format`, `mx`, `disposable` are **on**; `role_based`, `smtp`, `typo_suggestion`, `subaddress`, `catch_all` are **off** (a bare `new Configuration()`). Presets change these as shown above.

### Loading configuration from a file

`EmailValidator::fromConfigFile()` supports `.php`, `.json`, and `.yaml`/`.yml`:

```php
$validator = EmailValidator::fromConfigFile(__DIR__ . '/config/emailvalidator.php');
```

A ready-made template lives in [`config/emailvalidator.php`](config/emailvalidator.php) (and `.json`). Top-level keys: `checks`, `cache`, `rate_limit`, `lists`, `role_based`, `typo`, `smtp`, `dns`.

```php
// config/emailvalidator.php
return [
    'checks' => [
        'format' => true, 'mx' => true, 'disposable' => true,
        'role_based' => false, 'smtp' => false,
        'typo_suggestion' => true, 'subaddress' => false, 'catch_all' => false,
    ],
    'cache'      => ['driver' => 'memory', 'ttl' => 3600, 'prefix' => 'email_validator_', 'options' => []],
    'rate_limit' => ['enabled' => false, 'max_attempts' => 100, 'decay_seconds' => 60],
    'lists'      => ['blocklist_path' => null, 'allowlist_path' => null, 'role_based_path' => null],
    'smtp'       => ['timeout' => 10, 'from_email' => null, 'from_domain' => null],
    'dns'        => ['timeout' => 5],
];
```

---

## The `ValidationResult` object

`validate()` returns a rich result you can inspect:

```php
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;

$config    = ConfigurationBuilder::create()->standard()->withRoleBasedCheck()->build();
$validator = EmailValidator::withConfig($config);

$result = $validator->validate('info@gmial.com');

$result->isValid();                 // bool — true only if every enabled check passed
$result->getEmail();                // 'info@gmial.com'
$result->getDomain();               // 'gmial.com'
$result->getLocalPart();            // 'info'
$result->getChecks();               // ['format' => true, 'disposable' => true, 'role_based' => false, ...]
$result->passed('mx');              // ?bool — null if the check was not run
$result->getErrors();               // ['Role-based email address']
$result->getFirstError();           // 'Role-based email address'
$result->getWarnings();             // string[]
$result->isDisposable();            // ?bool
$result->isRoleBased();             // ?bool
$result->isSubaddressed();          // ?bool
$result->getMetadata();             // array (e.g. base_email for subaddressed emails)
$result->hasSuggestion();           // bool
$result->getSuggestion();           // ?SuggestionResult
$result->toArray();                 // full array representation
$result->toJson(JSON_PRETTY_PRINT); // JSON string
```

---

## Individual Checks

Each check is also available as a standalone method:

```php
$validator->isValidFormat('user@example.com');   // format only
$validator->isDisposable('user@mailinator.com'); // blocklist (allowlist wins)
$validator->hasValidMX('user@example.com');      // MX records (cached)
$validator->hasValidDNS('user@example.com');     // A / AAAA records
$validator->isRoleBased('info@example.com');     // role-based prefix
$validator->isSubaddressed('user+tag@gmail.com');// plus addressing
$validator->getBaseEmail('user+tag@gmail.com');  // 'user@gmail.com'
$validator->isCatchAll('user@example.com');      // catch-all domain (SMTP)
$validator->validateSMTP('user@example.com');    // live SMTP mailbox check
```

### Typo suggestions

```php
$suggestion = $validator->getSuggestion('user@gmial.com');

if ($suggestion !== null) {
    $suggestion->getSuggestedEmail();   // 'user@gmail.com'
    $suggestion->getSuggestedDomain();  // 'gmail.com'
    $suggestion->getOriginalEmail();    // 'user@gmial.com'
    $suggestion->getReason();           // 'known_typo' | 'similar_domain'
    $suggestion->getConfidence();       // 0–100
    $suggestion->isHighConfidence(80);  // bool
    (string) $suggestion;               // 'user@gmail.com'
}
```

---

## Batch Validation

```php
$emails = ['user1@gmail.com', 'user2@mailinator.com', 'invalid-email'];

// Rich results keyed by email
$results = $validator->validateBatch($emails);         // array<string, ValidationResult>

// Backward-compatible array results
$results = $validator->validateMultiple($emails, false); // $checkMX = false

// Filtering
$valid   = $validator->filterValid($emails, false);      // string[]
$invalid = $validator->filterInvalid($emails, false);    // string[]

// Statistics
$stats = $validator->getStatistics($emails, false);
// ['total' => 3, 'valid' => 1, 'invalid' => 2, 'disposable' => 1, 'invalid_format' => 1, 'no_mx' => 0]
```

---

## Caching

MX lookups and catch-all results are cached through a pluggable adapter. Choose a driver via the builder:

```php
ConfigurationBuilder::create()->withMemoryCache(3600);                          // in-process (default)
ConfigurationBuilder::create()->withFileCache('/tmp/ev-cache', 3600);           // filesystem
ConfigurationBuilder::create()->withRedisCache('127.0.0.1', 6379, null, 0);     // ext-redis or Predis
ConfigurationBuilder::create()->withMemcachedCache('127.0.0.1', 11211);         // ext-memcached
ConfigurationBuilder::create()->withPsr16Cache($anyPsr16Cache);                 // wrap a PSR-16 cache
ConfigurationBuilder::create()->withoutCache();                                 // disable caching
```

Supported driver names: `memory`, `file`, `redis`, `memcached`, `psr16`, `null`. You can also register a custom driver:

```php
use HarunGecit\EmailValidator\Cache\CacheManager;

CacheManager::registerDriver('my-driver', MyCacheAdapter::class);
CacheManager::getAvailableDrivers();          // string[]
CacheManager::isDriverAvailable('redis');     // bool
```

The legacy `setCacheEnabled(false)` / `clearCache()` helpers from 2.x still work.

---

## Rate Limiting

Throttle how many times an identifier may be validated within a decay window. When the limit is exceeded, `validate()`/`isValid()` throw `RateLimitExceededException`.

```php
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\Exceptions\RateLimitExceededException;

$config = ConfigurationBuilder::create()
    ->standard()
    ->withRateLimiting(maxAttempts: 5, decaySeconds: 60)
    ->build();

$validator = EmailValidator::withConfig($config);

try {
    $result = $validator->validate($email);
} catch (RateLimitExceededException $e) {
    echo "Too many attempts. Retry after {$e->getRetryAfter()}s (max {$e->getMaxAttempts()}).";
}
```

The limiter shares the configured cache backend, so it works across processes when backed by Redis/Memcached/File.

---

## Logging (PSR-3)

```php
$validator->setLogger($psr3Logger);
// or via configuration
$config = ConfigurationBuilder::create()->standard()->build();
$config->setLogger($psr3Logger);
```

Each `validate()` call logs a debug entry with the email, outcome, checks, and errors.

---

## Custom Blocklist / Allowlist

```php
$validator = new EmailValidator([], []); // start empty

$validator->addToBlocklist('custom-disposable.com')
          ->addMultipleToBlocklist(['temp1.com', 'temp2.com'])
          ->addToAllowlist('trusted-domain.com'); // allowlist always wins over blocklist

$validator->removeFromBlocklist('temp1.com');

$validator->getBlocklist();       // string[]
$validator->getBlocklistCount();  // int
$validator->isBlocklisted('a@temp2.com');
$validator->isAllowlisted('a@trusted-domain.com');
```

You can also point the configuration at your own list files:

```php
$config = ConfigurationBuilder::create()
    ->withBlocklist('/path/to/blocklist.conf')
    ->withAllowlist('/path/to/allowlist.conf')
    ->build();
```

---

## Framework Integration

### Laravel

The package ships with auto-discovery (service provider + `EmailValidator` facade). Publish the config if you want to customize it:

```bash
php artisan vendor:publish --tag=emailvalidator-config
```

Resolve it from the container, or use the facade:

```php
use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade as EmailValidator;

EmailValidator::isValid('user@example.com');
EmailValidator::validate('user@example.com');

// or inject the concrete service
public function store(\HarunGecit\EmailValidator\EmailValidator $validator) { /* ... */ }
```

Config-driven validation rules are registered automatically:

```php
$request->validate([
    'email' => ['required', 'valid_email', 'not_disposable', 'not_role_based', 'has_mx', 'not_subaddressed'],
]);
```

The published config (`config/emailvalidator.php`) is env-aware (`MAIL_FROM_ADDRESS`, `LOG_CHANNEL`, etc.) and defaults the cache driver to `laravel`, reusing your application's cache store.

### Symfony

Register the bundle:

```php
// config/bundles.php
return [
    HarunGecit\EmailValidator\Framework\Symfony\EmailValidatorBundle::class => ['all' => true],
];
```

Configure it under the `email_validator` key:

```yaml
# config/packages/email_validator.yaml
email_validator:
    checks:
        format: true
        mx: true
        disposable: true
        typo_suggestion: true
    cache:
        driver: memory   # memory | file | redis | memcached | psr16 | null
        ttl: 3600
    rate_limit:
        enabled: false
        max_attempts: 100
        decay_seconds: 60
```

The `EmailValidator` service is public and autowirable (service id `email_validator`, or type-hint the class).

---

## How It Works

1. **Format** — `filter_var($email, FILTER_VALIDATE_EMAIL)`.
2. **Disposable** — the domain is checked against the bundled blocklist; any domain in the allowlist is never treated as disposable.
3. **MX / DNS** — `checkdnsrr()` for MX (or A/AAAA) records, with results cached per domain.
4. **Role-based / Subaddress / Typo / SMTP / Catch-all** — each runs only when enabled in the configuration and contributes to the final `ValidationResult`.

---

## Blocklist and Allowlist

Bundled data lives in the `data/` directory:

- **Blocklist (`blocklist.conf`)** — 7,900+ disposable/temporary email domains.
- **Allowlist (`allowlist.conf`)** — 180+ domains that should always be considered valid.

The blocklist is synchronized with the community-maintained [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) project (plus a few local additions). One lowercase domain per line:

```
mailinator.com
guerrillamail.com
tempmail.com
```

Load, merge, or persist lists with the `Fetcher` utility:

```php
use HarunGecit\EmailValidator\Fetcher;

$lists = Fetcher::loadAll();                    // ['blocklist' => [...], 'allowlist' => [...]]
$custom = Fetcher::loadCustomBlocklist('/path/to/custom.conf');
$merged = Fetcher::mergeLists(['/a.conf', '/b.conf']);
Fetcher::saveList('/out.conf', ['domain1.com', 'domain2.com']);
```

---

## API Reference

### `EmailValidator`

| Method | Description |
|--------|-------------|
| `create(): self` | Factory with the default bundled lists |
| `withConfig(Configuration $c): self` | Factory from a configuration object |
| `fromConfigFile(string $path): self` | Factory from a PHP/JSON/YAML config file |
| `strict(): self` / `basic(): self` | Factory from a preset |
| `validate(string $email): ValidationResult` | Full validation, rich result |
| `isValid(string $email, ?bool $checkMX = null): bool` | Boolean validation |
| `validateBatch(array $emails): array` | Batch → `ValidationResult[]` |
| `validateMultiple/validateWithDetails/filterValid/filterInvalid/getStatistics` | 2.x-compatible batch helpers |
| `isValidFormat/isDisposable/hasValidMX/hasValidDNS` | Individual checks |
| `isRoleBased/isSubaddressed/getBaseEmail/getSuggestion/isCatchAll/validateSMTP` | Advanced checks |
| `extractDomain/extractLocalPart/normalize/normalizeMultiple` | Utilities |
| `addToBlocklist/addToAllowlist/removeFrom*/getBlocklist/getAllowlist/…` | List management (fluent) |
| `setConfiguration/getConfiguration/setLogger/setCacheEnabled/clearCache` | Configuration & runtime |

### `ConfigurationBuilder`

`create()`, presets `strict()/standard()/basic()/minimal()`, cache `withMemoryCache/withFileCache/withRedisCache/withMemcachedCache/withPsr16Cache/withCache/withoutCache`, `withRateLimiting/withoutRateLimiting`, checks `withRoleBasedCheck/withTypoSuggestion/withSmtpCheck/withSubaddressCheck/withCatchAllCheck/withoutMxCheck/withoutDisposableCheck`, lists `withBlocklist/withAllowlist`, and `build()`.

### `ValidationResult`

`isValid, getEmail, getDomain, getLocalPart, getChecks, passed, getErrors, getFirstError, getWarnings, isDisposable, isRoleBased, isSubaddressed, getMetadata, getMetadataValue, hasSuggestion, getSuggestion, toArray, toJson`.

### `SuggestionResult`

`getOriginalEmail, getSuggestedEmail, getOriginalDomain, getSuggestedDomain, getReason, getConfidence, isHighConfidence, toArray, __toString`.

### `Fetcher`

`loadBlocklist, loadAllowlist, loadAll, loadCustomBlocklist, loadCustomAllowlist, mergeLists, saveList, clearCache, getBlocklistPath, getAllowlistPath, blocklistExists, allowlistExists, getBlocklistCount, getAllowlistCount`.

---

## Exceptions

All exceptions extend `HarunGecit\EmailValidator\Exceptions\EmailValidatorException`:

| Exception | When |
|-----------|------|
| `RateLimitExceededException` | Rate limit exceeded (`getRetryAfter()`, `getMaxAttempts()`) |
| `InvalidEmailException` | Semantic validation failures (factory helpers per reason) |
| `ConfigurationException` | Invalid config value, unsupported format, missing file |
| `CacheException` | Cache driver/backend errors |
| `SmtpConnectionException` | SMTP connection/timeout/unexpected-response errors |

---

## Testing

```bash
composer test           # run the suite
composer test-coverage  # HTML coverage report in ./coverage
```

> The test toolchain uses PHPUnit 10+ and therefore requires PHP 8.1+. The library runtime supports PHP 8.0+ (verified by a dedicated CI job).

---

## Upgrading to 3.0

3.0 is **backward compatible** with the 2.x public API — existing code using `isValid()`, `validateWithDetails()`, `validateMultiple()`, `filterValid()`, list management, and `EmailValidator::create()` continues to work unchanged.

What's new / worth knowing:

- **Minimum PHP is now 8.0** (2.x supported 7.4). Language features used in 3.0 require 8.0+.
- **New rich API:** `validate()` returns a `ValidationResult` object instead of an array; the array-based `validateWithDetails()` is still available.
- **Configuration & presets:** prefer `ConfigurationBuilder` and the `strict/standard/basic/minimal` presets over ad-hoc setters.
- **New checks:** role-based, subaddress, typo suggestion, SMTP, and catch-all (all opt-in).
- **Pluggable cache & rate limiting** replace the simple 2.x MX cache toggle (which still works).
- **Framework packages:** Laravel and Symfony integrations are now included.

Coming from **1.x**? The namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`, and the package from `phporbit/php-email-validator` to `harungecit/php-email-validator`.

---

## Contributing

Contributions are welcome:

1. Fork the repository and create a feature branch.
2. Make your changes with tests.
3. Ensure `composer test` passes and code follows PSR-12 with PHPDoc comments.
4. Submit a pull request.

---

## License

Licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

## Author

**Harun Geçit**

- [GitHub](https://github.com/harungecit)
- [LinkedIn](https://linkedin.com/in/harungecit)
- [Email](mailto:info@harungecit.com)

---

## Changelog

### v3.0.0
- **Requires PHP 8.0+** (dropped 7.4). Forward-compatible through PHP 8.5.
- Added a rich `ValidationResult` object returned by the new `validate()` method, plus `validateBatch()`.
- Added `Configuration` and a fluent `ConfigurationBuilder` with `strict`, `standard`, `basic`, and `minimal` presets.
- Added configuration file loading (PHP / JSON / YAML) via `EmailValidator::fromConfigFile()`.
- Added new checks: role-based detection, subaddress (plus addressing), typo suggestions (`SuggestionResult`), SMTP verification, and catch-all detection.
- Added a pluggable cache layer with Memory, File, Redis, Memcached, PSR-16, and Null adapters.
- Added token-bucket rate limiting with `RateLimitExceededException`.
- Added PSR-3 logging support.
- Added Laravel (service provider, facade, publishable config, validation rules) and Symfony (bundle, DI extension) integrations.
- Updated the disposable blocklist to 7,900+ domains, synchronized with the [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) project.
- Fully backward compatible with the 2.x public API.

### v2.0.0
- **Breaking:** Namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`.
- **Breaking:** Package name changed from `phporbit/php-email-validator` to `harungecit/php-email-validator`.
- Added batch validation (`validateMultiple`, `filterValid`, `filterInvalid`), `isValid()`, `validateWithDetails()`, `getStatistics()`, and the `EmailValidator::create()` factory.
- Added a fluent interface for list management, MX record caching, `hasValidDNS()`, email normalization, and `isAllowlisted()`/`isBlocklisted()`.
- Enhanced the `Fetcher` with caching, custom list loading, and merge capabilities.
- Added multi-platform CI (Ubuntu, Windows, macOS).

### v1.0.x
- Initial releases: email format validation, disposable email detection, and MX record validation.
