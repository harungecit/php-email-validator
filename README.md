<div align="center">

# PHP Email Validator

**The most complete email validation & disposable email detection library for PHP.**
One package for Laravel, Symfony, CodeIgniter 4, CakePHP, Yii 2, Yii 3, Phalcon — and plain PHP.

[![PHP Version](https://img.shields.io/packagist/php-v/harungecit/php-email-validator?style=flat-square&logo=php&logoColor=white)](https://packagist.org/packages/harungecit/php-email-validator)
[![Latest Version](https://img.shields.io/packagist/v/harungecit/php-email-validator?style=flat-square&logo=packagist&logoColor=white&label=version)](https://packagist.org/packages/harungecit/php-email-validator)
[![Total Downloads](https://img.shields.io/packagist/dt/harungecit/php-email-validator?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/harungecit/php-email-validator)
[![CI Status](https://img.shields.io/github/actions/workflow/status/harungecit/php-email-validator/ci.yml?branch=main&style=flat-square&logo=github&logoColor=white&label=tests)](https://github.com/harungecit/php-email-validator/actions/workflows/ci.yml)
[![List Updates](https://img.shields.io/github/actions/workflow/status/harungecit/php-email-validator/update-lists.yml?branch=main&style=flat-square&logo=github&logoColor=white&label=daily%20list%20update)](https://github.com/harungecit/php-email-validator/actions/workflows/update-lists.yml)
[![License](https://img.shields.io/packagist/l/harungecit/php-email-validator?style=flat-square&logo=opensource&logoColor=white)](LICENSE)

[![Blocklist Domains](https://img.shields.io/badge/blocklist-137,400%2B%20domains-red?style=flat-square&logo=shield&logoColor=white)](#-the-disposable-domain-list)
[![Frameworks](https://img.shields.io/badge/frameworks-Laravel%20%7C%20Symfony%20%7C%20CodeIgniter%20%7C%20CakePHP%20%7C%20Yii%20%7C%20Phalcon-blue?style=flat-square)](#-framework-integrations)
[![Platform Support](https://img.shields.io/badge/platform-Linux%20%7C%20Windows%20%7C%20macOS-blue?style=flat-square)](#compatibility)

</div>

```php
$validator = HarunGecit\EmailValidator\EmailValidator::create();

$validator->isValid('john@gmail.com');                  // true  — format, disposable and MX checks
$validator->isDisposable('john@mailinator.com');         // true
$validator->checkDisposable('x@eu.mailinator.com')       // explains *why*:
          ->getMatchedDomain();                          // 'mailinator.com' (subdomain match)
$validator->validate('john@gmial.com')->getSuggestion(); // 'john@gmail.com' (with typo suggestions on)
```

- **137,400+ disposable domains** from multiple sources — rebuilt **daily**, released weekly, and **false-positive filtered** (no `namecheap.com`, no `yandex.net`, no ISP domains, no public suffixes).
- **Explainable results** — which list entry matched, whether through a subdomain, and from which list.
- **A full validation pipeline**, not just a blocklist — RFC format modes, MX/DNS, homograph spoofing, role accounts, plus-addressing, typo suggestions, SMTP and catch-all.
- **Every PHP framework, natively** — validation rules, constraints, services and commands that feel at home in each framework.
- **Fast** — O(1) lookups (~2.5 µs, subdomains included); with OPcache the full domain index costs **zero** load time and per-request memory.
- **No API keys, no per-request fees** — everything runs locally; network is only used for the checks you enable (MX, SMTP) and for list syncs.

---

## Table of Contents

- [Why this package?](#-why-this-package)
- [Installation](#installation)
- [Quick start](#-quick-start)
- [Disposable email detection](#-disposable-email-detection)
- [The disposable domain list](#-the-disposable-domain-list)
- [Keeping the list fresh (sync)](#-keeping-the-list-fresh-sync)
- [Full validation pipeline](#-full-validation-pipeline)
- [Configuration](#-configuration)
- [Framework integrations](#-framework-integrations) — [Laravel](#laravel) · [Symfony](#symfony) · [CodeIgniter 4](#codeigniter-4) · [CakePHP](#cakephp) · [Yii 2](#yii-2) · [Yii 3](#yii-3) · [Phalcon](#phalcon-5) · [Plain PHP](#plain-php--any-framework)
- [Command line](#-command-line)
- [Performance](#-performance)
- [Caching, rate limiting & logging](#caching-rate-limiting--logging)
- [Result objects & API reference](#-result-objects--api-reference)
- [Testing](#testing) · [Upgrading](#upgrading) · [Contributing](#contributing) · [Changelog](#changelog)

---

## ⭐ Why this package?

| | **php-email-validator** | Typical disposable-list package |
|---|---|---|
| Disposable domains | **137,400+ disposable domains**, multi-source, daily rebuild | one source, a few thousand to ~100k |
| False-positive protection | Public Suffix List, popularity, typo & allowlist filters, regression tests | none — aggregated feeds are merged blindly |
| Subdomain detection (`x.mailinator.com`) | ✅ (configurable) | sometimes |
| Explains matches (entry + source) | ✅ `checkDisposable()` | rarely |
| Runtime sync between releases | ✅ append-only, atomic, locked, HTML-safe | sometimes |
| Format: filter / unicode / RFC 5322 / strict | ✅ | framework-dependent |
| MX / DNS, spoof (homograph), role-based, plus-addressing, typo suggestions | ✅ | ❌ |
| SMTP mailbox & catch-all verification | ✅ (opt-in) | ❌ |
| Frameworks | Laravel, Symfony, CodeIgniter 4, CakePHP, Yii 2, Yii 3, Phalcon, plain PHP | usually one |
| CLI | ✅ `vendor/bin/email-validator` | framework-only, if any |
| Caching / rate limiting / PSR-3 logging | ✅ Memory, File, Redis, Memcached, PSR-16 | ❌ |
| PHP versions | 8.0 – 8.5 | varies |

---

## Installation

```bash
composer require harungecit/php-email-validator
```

### Compatibility

- **PHP 8.0 – 8.5** (runtime verified on 8.0; full test suite on 8.1 – 8.5), on Linux, Windows and macOS.
- Laravel 9 – 13 · Symfony 6.4 – 8.x · CodeIgniter 4.4+ · CakePHP 4.4+ / 5.x · Yii 2.0 · Yii 3 (yiisoft/validator 2.x) · Phalcon 5.
- Optional: `ext-intl` (IDN + spoof check), `egulias/email-validator` (`rfc`/`strict` format modes), `ext-curl` (sync), `ext-redis`/`predis/predis`/`ext-memcached` (cache backends), `symfony/yaml` (YAML config).

---

## 🚀 Quick start

```php
use HarunGecit\EmailValidator\EmailValidator;

$validator = EmailValidator::create();          // format + disposable + MX checks

if (!$validator->isValid($email)) {
    // reject
}

// Detailed report
$result = $validator->validate('user@mailinator.com');

$result->isValid();       // false
$result->getErrors();     // ['Disposable email address']
$result->getChecks();     // ['format' => true, 'disposable' => false, 'mx' => true]
$result->getMetadata();   // ['disposable_match' => 'mailinator.com', 'disposable_source' => 'blocklist']
```

Just need a disposable check, no network?

```php
$validator = EmailValidator::basic();           // format + disposable only
$validator->isValid('user@yopmail.com');        // false
```

---

## 🛡 Disposable email detection

```php
$validator->isDisposable('user@mailinator.com');      // true  (email address)
$validator->isDisposableDomain('mailinator.com');      // true  (bare domain)

$check = $validator->checkDisposable('User@EU.Mailinator.com');
$check->isDisposable();      // true
$check->getDomain();         // 'eu.mailinator.com' (normalized: lowercase, trailing dot removed, IDN → punycode)
$check->getMatchedDomain();  // 'mailinator.com'
$check->isSubdomainMatch();  // true
$check->getSource();         // 'blocklist' | 'custom' | 'synced' | 'allowlist' | null
$check->isAllowlisted();     // false
$check->toArray();           // JSON-serializable
```

### How a domain is matched

The domain **and each of its parent domains** are looked up, most specific first —
`a.b.mailinator.com`, `b.mailinator.com`, `mailinator.com` — so throwaway subdomains are caught too.
On each level the lists are consulted in this order (first hit wins):

| Priority | List | Source label |
|:--:|---|---|
| 1 | **Your allowlist** — `lists.custom_allowlist`, `custom_allowlist_paths`, `addToAllowlist()` | `allowlist` |
| 2 | **Your blocklist** — `lists.custom_blocklist`, `custom_blocklist_paths`, `addToBlocklist()` | `custom` |
| 3 | Bundled allowlist (237 legitimate providers, ISPs and privacy relays) | `allowlist` |
| 4 | Synced blocklist (`lists.synced_blocklist_path`) | `synced` |
| 5 | Bundled blocklist (or your replacement via `lists.blocklist_path`) | `blocklist` |

So your own decisions always win over the bundled data — e.g. you can block a privacy relay the
package allows, or allow a domain the package blocks.

```php
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;

$config = ConfigurationBuilder::create()
    ->basic()
    ->withCustomBlocklist(['competitor-trials.example'])     // block more
    ->withCustomAllowlist(['partner.mailinator.com'])        // allow exceptions
    ->withCustomBlocklistFiles([__DIR__ . '/lists'])         // *.conf, *.txt, *.list, *.json files or directories
    ->withoutSubdomainBlocking()                             // optional: exact matches only
    ->build();

$validator = EmailValidator::withConfig($config);
```

Lists can also be changed at runtime (fluent): `addToBlocklist()`, `addToAllowlist()`,
`removeFromBlocklist()`, `removeFromAllowlist()`, `getBlocklist()`, `getBlocklistCount()`,
`isBlocklisted()`, `isAllowlisted()`. Runtime changes never copy the shared index.

> **Privacy relays** (Apple Hide My Email, Firefox Relay, DuckDuckGo, SimpleLogin, Proton Pass, addy.io)
> forward to a real inbox and are **allowed** by default. To block them, add them to your blocklist.

---

## 📚 The disposable domain list

The bundled list is rebuilt **every day** from multiple upstream projects and released as a patch
version **every Monday**, so a regular `composer update` keeps you current. Want daily freshness?
See [sync](#-keeping-the-list-fresh-sync).

| Source | Role | Treatment |
|---|---|---|
| [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) | hand-curated, conservative | **trusted** — taken as-is |
| [eramitgupta/disposable-email](https://github.com/eramitgupta/disposable-email) | large daily aggregator (several lists + StopForumSpam toxic domains) | **untrusted** — every entry is filtered |
| [`data/blocklist.local.conf`](data/blocklist.local.conf) | maintainers' additions | always included |
| [`data/allowlist.conf`](data/allowlist.conf) | legitimate domains | always excluded (with subdomains) |

Aggregated feeds contain real companies and ISPs that were once abused by spammers. Blindly merging
them would block e.g. `namecheap.com`, `yandex.net`, `sutterhealth.org`, Roadrunner/Spectrum ISP
addresses (`nyc.rr.com`) or entire country zones (`msk.ru`, `pp.ua`). The build therefore drops:

| Filter | Applies to | Example of what it prevents |
|---|---|---|
| Invalid syntax / upstream merge artifacts | all / untrusted | `btcmail.pwguerrillamail.net` (two lines glued together) |
| [Public Suffix List](https://publicsuffix.org) entries | all | `msk.ru`, `pp.ua`, `it.com` — would block every domain below them |
| Allowlisted domains **and their subdomains** | all | `vip.126.com`, `simplelogin.co`, `nyc.rr.com` |
| [Tranco](https://tranco-list.eu) top-100k popular domains | untrusted only | `namecheap.com`, `compaq.com`, `cafe24.com` |
| Typos of major providers | untrusted only | `gamil.com` → handled by typo suggestions instead of a misleading "disposable" error |

On top of that the build refuses to shrink the list by more than 5% in one run, switches to an
additive (remove-nothing) mode if an untrusted source is down, and CI keeps regression tests for
known false positives. Build details live in [`data/metadata.json`](data/metadata.json) and in the
output of `email-validator stats`.

**Found a missing domain or a false positive?** See [CONTRIBUTING.md](CONTRIBUTING.md) — fixes are permanent.

---

## 🔄 Keeping the list fresh (sync)

Package releases are weekly; the list on `main` is rebuilt daily. The **synchronizer** downloads
remote lists into a file in your app, and every validator configured with that file blocks those
domains too — no deploy needed.

```bash
vendor/bin/email-validator sync --path=var/email-validator/synced-blocklist.txt
```

```php
$config = ConfigurationBuilder::create()
    ->standard()
    ->withSyncedBlocklist(__DIR__ . '/var/email-validator/synced-blocklist.txt')
    ->build();
```

Schedule it (cron, Laravel scheduler, Symfony scheduler, …):

```cron
15 3 * * * cd /path/to/app && vendor/bin/email-validator sync --path=var/email-validator/synced-blocklist.txt
```

By default the synchronizer downloads **this package's own filtered list** from `main`
(`Configuration::DEFAULT_SYNC_URL`), so you get the same false-positive protection.
Use `--url=` (repeatable) or the `sync.urls` setting to add other plain-text or JSON lists.

Safeguards — a bad download never damages your last good list:

- failed requests, non-2xx responses, **HTML error pages**, empty responses and lists without a
  single valid domain are rejected before anything is written;
- **append-only** by default; `--replace` only drops domains when *every* source succeeded;
- allowlisted domains (bundled + yours, including subdomains) are never written;
- only domains that are **not already bundled** are stored, so the file stays tiny and cheap to load;
- writes go to a temp file that is **atomically renamed**, under an **exclusive lock**; an unchanged
  list is not rewritten.

Programmatic use:

```php
use HarunGecit\EmailValidator\Sync\ListSynchronizer;

$result = (new ListSynchronizer($config))->sync(replace: false);
$result->isSuccessful(); $result->getAdded(); $result->getTotal(); $result->getErrors();
```

---

## 🔬 Full validation pipeline

`validate()` runs every enabled check and returns a `ValidationResult`:

| Check | Default | What it does |
|---|:--:|---|
| `format` | ✅ | Syntax — see [format modes](#format-modes) |
| `spoof` | ❌ | Mixed-script / invisible characters per address part (needs `ext-intl`) |
| `disposable` | ✅ | Bundled + custom + synced lists, subdomain-aware |
| `mx` | ✅ | MX records (`checkdnsrr`), cached per domain |
| `role_based` | ❌ | `admin@`, `info@`, `support@`, … (configurable prefixes) |
| `subaddress` | ❌ | Plus addressing `user+tag@` (warning + `base_email` metadata) |
| `smtp` | ❌ | Live mailbox check (`RCPT TO`) — slow, may be blocked |
| `catch_all` | ❌ | Detects domains that accept every address |
| `typo_suggestion` | ❌ | "Did you mean `gmail.com`?" — never fails validation |

### Presets

| Preset | Enabled checks |
|--------|----------------|
| `minimal()` | format |
| `basic()` | format, disposable |
| `standard()` | format, mx, disposable, typo suggestion |
| `strict()` | format, mx, disposable, role-based, subaddress, typo suggestion, **smtp**, **catch-all** |

```php
$validator = EmailValidator::strict();   // ConfigurationBuilder::create()->strict()->build()
$validator = EmailValidator::basic();
```

> `strict()` enables SMTP and catch-all verification, which open live connections and can be slow
> or blocked. For most applications `standard()` is the recommended starting point.

### Format modes

| Mode | Engine | Accepts |
|---|---|---|
| `filter` (default) | `FILTER_VALIDATE_EMAIL` | ASCII addresses |
| `filter_unicode` | `FILTER_VALIDATE_EMAIL` + `FILTER_FLAG_EMAIL_UNICODE` | internationalized local parts (`jörg@example.com`) |
| `rfc` | `egulias/email-validator` `RFCValidation` | everything RFC 5322 allows |
| `strict` | `egulias/email-validator` `NoRFCWarningsValidation` | RFC 5322 without warnings (no quoted local parts, …) |

```php
ConfigurationBuilder::create()->standard()->withFormatMode('rfc')->withSpoofCheck()->build();
```

### Spoofing (homograph) detection

```php
$validator->isSpoofed('user@xn--gmil-63d.com');   // true  — "gmаil.com" with a Cyrillic "а"
$validator->isSpoofed("p\u{0430}ypal@example.com"); // true  — mixed-script local part
$validator->isSpoofed('ivan@почта.рф');           // false — one script per part is legitimate
```

### Individual checks

```php
$validator->isValidFormat('user@example.com');
$validator->hasValidMX('user@example.com');        // MX records (cached)
$validator->hasValidDNS('user@example.com');       // A / AAAA records
$validator->isRoleBased('info@example.com');
$validator->isSubaddressed('user+tag@gmail.com');
$validator->getBaseEmail('user+tag@gmail.com');    // 'user@gmail.com'
$validator->isCatchAll('user@example.com');
$validator->validateSMTP('user@example.com');
$validator->getSuggestion('user@gmial.com');       // SuggestionResult: 'user@gmail.com', confidence 0–100
```

### Batch validation

```php
$emails = ['user1@gmail.com', 'user2@mailinator.com', 'invalid-email'];

$validator->validateBatch($emails);              // array<string, ValidationResult>
$validator->filterValid($emails, false);         // ['user1@gmail.com']  ($checkMX = false)
$validator->filterInvalid($emails, false);
$validator->getStatistics($emails, false);
// ['total' => 3, 'valid' => 1, 'invalid' => 2, 'disposable' => 1, 'invalid_format' => 1, 'no_mx' => 0]
```

---

## ⚙ Configuration

Use the fluent builder, an array, or a PHP/JSON/YAML file — they all produce a `Configuration`.

```php
$config = ConfigurationBuilder::create()
    ->standard()
    ->withRoleBasedCheck()
    ->withFileCache(__DIR__ . '/cache', 3600)
    ->withRateLimiting(100, 60)
    ->build();

$validator = EmailValidator::withConfig($config);
$validator = EmailValidator::fromConfigFile(__DIR__ . '/config/emailvalidator.php'); // .php / .json / .yaml
$config    = Configuration::fromArray([...]);                                         // same keys
```

Ready-made templates: [`config/emailvalidator.php`](config/emailvalidator.php) and [`config/emailvalidator.json`](config/emailvalidator.json).

<details>
<summary><b>All configuration keys</b></summary>

| Key | Default | Description |
|---|---|---|
| `checks.format` / `mx` / `disposable` | `true` | Core checks |
| `checks.role_based` / `smtp` / `typo_suggestion` / `subaddress` / `catch_all` / `spoof` | `false` | Optional checks |
| `format.mode` | `filter` | `filter`, `filter_unicode`, `rfc`, `strict` |
| `lists.blocklist_path` / `allowlist_path` | `null` | **Replace** the bundled lists |
| `lists.custom_blocklist` / `custom_allowlist` | `[]` | Domains **added** to the lists (take precedence over bundled lists) |
| `lists.custom_blocklist_paths` / `custom_allowlist_paths` | `[]` | Extra list files or directories |
| `lists.synced_blocklist_path` | `null` | File maintained by `sync` (ignored until it exists) |
| `lists.block_subdomains` | `true` | Match parent domains (`x.mailinator.com` → `mailinator.com`) |
| `lists.role_based_path` | `null` | Custom role-prefix list file |
| `sync.urls` | package list on `main` | Remote lists for `sync` (text or JSON, http/https only) |
| `sync.timeout` | `30` | HTTP timeout in seconds |
| `cache.driver` / `ttl` / `prefix` / `options` | `memory`, `3600` | `memory`, `file`, `redis`, `memcached`, `psr16`, `null` |
| `rate_limit.enabled` / `max_attempts` / `decay_seconds` | `false`, `100`, `60` | Token-bucket limiter |
| `role_based.prefixes` | 36 prefixes | Role-account local parts |
| `typo.common_domains` / `typo.mappings` | major providers | Typo suggestion data |
| `smtp.timeout` / `from_email` / `from_domain` | `10`, `null`, `null` | SMTP verification |
| `dns.timeout` | `5` | DNS timeout |

</details>

---

## 🧩 Framework integrations

Every integration wraps the same core, so behaviour and configuration keys are identical everywhere.

| Framework | Validation | Services | Commands / sync | Extras |
|---|---|---|---|---|
| [Laravel 9–13](#laravel) | rule objects + string rules | facade, container | Artisan, scheduler | Blade directive, translations, Laravel Boost |
| [Symfony 6.4–8](#symfony) | `#[NotDisposableEmail]`, `#[ValidEmail]`, `#[NotRoleBasedEmail]` | autowiring | `bin/console` | YAML config, Scheduler |
| [CodeIgniter 4.4+](#codeigniter-4) | auto-registered rules | `service('emailValidator')` | CLI + cron | zero-config discovery, `.env` |
| [CakePHP 4.4+/5](#cakephp) | validation provider | DI plugin | CLI + cron | `Configure` key |
| [Yii 2](#yii-2) | model validators | app component | CLI + cron | aliases, `Yii::t()` messages |
| [Yii 3](#yii-3) | attribute rules | DI container | CLI + cron | — |
| [Phalcon 5](#phalcon-5) | validators | DI service provider | CLI + cron | config service section |

### Laravel

Supports Laravel 9–13 (rule objects need Laravel 10+). The service provider and the `EmailValidator` facade are auto-discovered.

```bash
php artisan email-validator:install     # publishes config/emailvalidator.php (add --translations for the messages)
php artisan email-validator:sync        # download the latest disposable domains
```

**Validation rules**

```php
use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotRoleBasedEmail;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\ValidEmail;

$request->validate([
    'email'      => ['required', 'email', new NotDisposableEmail()],
    'work_email' => ['required', new ValidEmail()],              // every check enabled in config, first failure reported
    'billing'    => ['required', new ValidEmail(checkMx: false)], // skip DNS for this rule
    'contact'    => ['required', 'email', new NotRoleBasedEmail()],
    'other'      => ['required', new NotDisposableEmail('Please use a permanent address.')],
]);

// String rules: valid_email, not_disposable, not_role_based, has_mx, not_subaddressed, not_spoofed
$request->validate(['email' => 'required|email:rfc,dns|not_disposable']);
```

Messages live in the `emailvalidator::validation` translation group (`php artisan vendor:publish --tag=emailvalidator-translations`).
Rule-object messages accept `:attribute`; `NotDisposableEmail` also `:domain` and `:matched`, `ValidEmail` also `:reason` and `:suggestion`.

**Facade, Blade & commands**

```php
use EmailValidator; // HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade

EmailValidator::isDisposable('user@mailinator.com');                           // true
EmailValidator::checkDisposable('user@eu.mailinator.com')->getMatchedDomain(); // 'mailinator.com'
EmailValidator::validate('john@gmial.com')->getSuggestion()?->getSuggestedEmail(); // 'john@gmail.com'
EmailValidator::getListStatistics();
```

```blade
@disposableEmail($user->email)
    <p>Please switch to a permanent email address.</p>
@enddisposableEmail
```

```bash
php artisan email-validator:check user@eu.mailinator.com example.org [--json]   # exit 1 if disposable
php artisan email-validator:validate user@example.com [--no-mx] [--json]         # exit 1 if invalid
php artisan email-validator:sync [--replace] [--json]
php artisan email-validator:stats [--json]
```

**Configuration** (`config/emailvalidator.php`, env-aware; the cache driver defaults to your Laravel cache store):

```php
'format' => ['mode' => env('EMAIL_VALIDATOR_FORMAT_MODE', 'filter')],
'lists'  => [
    'custom_blocklist'      => ['my-throwaway.com'],
    'custom_allowlist'      => ['my-company.com'],
    'synced_blocklist_path' => storage_path('app/email-validator/synced-blocklist.txt'),
    'block_subdomains'      => true,
],
'sync' => [
    'urls'     => [Configuration::DEFAULT_SYNC_URL],   // EMAIL_VALIDATOR_SYNC_URLS (comma-separated)
    'schedule' => env('EMAIL_VALIDATOR_SYNC_SCHEDULE'), // null|hourly|daily|weekly|monthly|cron expression
],
```

Set `EMAIL_VALIDATOR_SYNC_SCHEDULE=daily` and the package registers `email-validator:sync` on Laravel's scheduler (`withoutOverlapping()`).
AI agents using [Laravel Boost](https://github.com/laravel/boost) get this package's guidelines automatically.

### Symfony

Supports Symfony 6.4, 7.x and 8.x.

```php
// config/bundles.php
return [
    HarunGecit\EmailValidator\Framework\Symfony\EmailValidatorBundle::class => ['all' => true],
];
```

```yaml
# config/packages/email_validator.yaml
email_validator:
    checks:   { mx: true, disposable: true, role_based: false, spoof: false }
    format:   { mode: filter }           # filter | filter_unicode | rfc | strict
    lists:
        custom_blocklist: ['my-throwaway.com']
        custom_allowlist: ['my-company.com']
        synced_blocklist_path: '%kernel.project_dir%/var/email-validator/synced-blocklist.txt'  # default; ~ disables
        block_subdomains: true
    sync:     { timeout: 30 }
```

```php
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\NotRoleBasedEmail;
use HarunGecit\EmailValidator\Framework\Symfony\Validator\ValidEmail;
use Symfony\Component\Validator\Constraints as Assert;

class SignupDto
{
    #[Assert\NotBlank, Assert\Email, NotDisposableEmail]
    public string $email;

    #[ValidEmail(checkMx: false, message: 'Invalid email: {{ reason }}')]
    public string $workEmail;

    #[NotRoleBasedEmail(groups: ['registration'])]
    public string $contactEmail;
}
```

Message parameters: `{{ value }}`, `{{ domain }}`, `{{ matched }}` (NotDisposableEmail); `{{ check }}`, `{{ reason }}`, `{{ suggestion }}` (ValidEmail), with one error code per failed check.
Services: `EmailValidator` (autowirable, alias `email_validator`), `Configuration`, `ListSynchronizer` (`email_validator.synchronizer`).

```bash
bin/console email-validator:check user@eu.mailinator.com [--json]
bin/console email-validator:validate user@example.com [--no-mx] [--json]
bin/console email-validator:sync [--replace] [--json]     # schedule with cron or Symfony Scheduler (RunCommandMessage)
bin/console email-validator:stats [--json]
```

### CodeIgniter 4

**Zero configuration.** CodeIgniter's auto-discovery picks up the package's `Config/Registrar.php` and `Config/Services.php`:
the rules are added to `Config\Validation::$ruleSets` and `service('emailValidator')` returns a shared validator.

```php
$rules = ['email' => 'required|valid_email|not_disposable_email|not_role_based_email'];

if (! $this->validate($rules)) {
    return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
}
```

| Rule | Fails when |
|------|------------|
| `not_disposable_email` | the domain (or a parent domain) is disposable |
| `email_has_mx` | the domain has no MX record |
| `not_role_based_email` | the address is a role account (`admin@`, `info@`, …) |
| `not_subaddressed_email` | the address has a `+tag` |
| `not_spoofed_email` | it mixes scripts or has invisible characters (needs ext-intl) |
| `verified_email` | the full configured pipeline fails; the message names the failed check |

Messages ship as language lines (`{field}`, `{value}` and per-field custom errors work); override them in
`app/Language/<locale>/Validation.php`. Use `permit_empty` for optional fields.

```php
$validator = service('emailValidator');   // shared; single_service('emailValidator') for a new instance
$validator->checkDisposable('user@eu.mailinator.com')->getMatchedDomain(); // 'mailinator.com'
```

To configure, create `app/Config/EmailValidator.php` (properties mirror the configuration keys; `.env` overrides such as
`emailvalidator.checks.mx = false` work too):

```php
namespace Config;

use HarunGecit\EmailValidator\Framework\CodeIgniter\Config\EmailValidator as BaseEmailValidator;

class EmailValidator extends BaseEmailValidator
{
    public array $lists = [
        'custom_blocklist'      => ['competitor-temp.com'],
        'synced_blocklist_path' => WRITEPATH . 'email-validator/synced.conf',
        'block_subdomains'      => true,
    ];
}
```

Discovery disabled? Add `\HarunGecit\EmailValidator\Framework\CodeIgniter\EmailValidatorRules::class` to `Config\Validation::$ruleSets`.
Sync from cron: `vendor/bin/email-validator sync --path=writable/email-validator/synced.conf`.

### CakePHP

```php
use HarunGecit\EmailValidator\Framework\CakePHP\EmailValidationProvider;

public function validationDefault(Validator $validator): Validator
{
    // One call: registers the "emailValidator" provider and adds the rules
    EmailValidationProvider::attach($validator, 'email', [
        'notDisposable',
        'notRoleBased' => 'Please use a personal email address.',   // custom message
    ]);

    return $validator;
}
```

Rules: `notDisposable`, `notRoleBased`, `notSubaddressed`, `notSpoofed`, `hasMx`, `verifiedEmail` (full pipeline).
Manual use works as with any provider: `->setProvider('emailValidator', new EmailValidationProvider())->add('email', 'notDisposable', ['rule' => 'notDisposable', 'provider' => 'emailValidator'])`.

Register the plugin to get `EmailValidator`/`Configuration` in the DI container, configured from the `EmailValidator` key:

```php
// src/Application.php
$this->addPlugin(\HarunGecit\EmailValidator\Framework\CakePHP\EmailValidatorPlugin::class);

// config/app_local.php
'EmailValidator' => [
    'checks' => ['mx' => true, 'role_based' => true],
    'lists'  => ['synced_blocklist_path' => TMP . 'email-validator' . DS . 'synced.conf'],
    'cache'  => ['driver' => 'cakephp', 'options' => ['config' => 'default']],   // optional: use a Cake cache config
],
```

```php
public function signup(\HarunGecit\EmailValidator\EmailValidator $emailValidator) { /* injected */ }
```

### Yii 2

```php
// config/web.php (and console.php)
'components' => [
    'emailValidator' => [
        'class'  => \HarunGecit\EmailValidator\Framework\Yii2\EmailValidatorComponent::class,
        'config' => [
            'checks' => ['mx' => true, 'role_based' => true],
            'lists'  => ['synced_blocklist_path' => '@runtime/email-validator/synced.conf'],   // aliases are resolved
        ],
    ],
],
```

```php
use HarunGecit\EmailValidator\Framework\Yii2\NotDisposableEmailValidator;
use HarunGecit\EmailValidator\Framework\Yii2\NotRoleBasedEmailValidator;
use HarunGecit\EmailValidator\Framework\Yii2\VerifiedEmailValidator;

public function rules()
{
    return [
        ['email', 'required'],
        ['email', 'email'],
        ['email', NotDisposableEmailValidator::class],
        ['email', NotRoleBasedEmailValidator::class],
        ['email', VerifiedEmailValidator::class, 'messages' => ['mx' => 'The domain of {attribute} cannot receive email.']],
    ];
}

Yii::$app->emailValidator->checkDisposable('user@eu.mailinator.com')->getMatchedDomain(); // 'mailinator.com'
```

Messages support `{attribute}`, `{value}`, `{domain}`, `{matched}` (and `{check}`, `{error}`, `{suggestion}` for `VerifiedEmailValidator`) and go through `Yii::t('app', …)`.

### Yii 3

```php
use HarunGecit\EmailValidator\Framework\Yii3\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Yii3\VerifiedEmail;
use Yiisoft\Validator\Rule\{Email, Required};

final class SignupForm
{
    #[Required, Email, NotDisposableEmail]
    public string $email = '';

    #[VerifiedEmail(skipOnEmpty: true, messages: ['disposable' => 'Please use a permanent email address.'])]
    public string $backupEmail = '';
}
```

Handlers receive `EmailValidator` from the DI container — register it with your configuration:

```php
// config/common/di/email-validator.php
return [
    EmailValidator::class => ['__construct()' => [Configuration::fromArray(['checks' => ['mx' => true]])]],
];
```

### Phalcon 5

```php
use HarunGecit\EmailValidator\Framework\Phalcon\EmailValidatorServiceProvider;
use HarunGecit\EmailValidator\Framework\Phalcon\Validator\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Phalcon\Validator\VerifiedEmail;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\Email;

$di->register(new EmailValidatorServiceProvider([
    'checks' => ['mx' => true],
    'lists'  => ['synced_blocklist_path' => BASE_PATH . '/storage/email-validator/synced.conf'],
]));   // or no argument: reads the "emailValidator" section of the "config" service

$validation = new Validation();
$validation->add('email', new Email());
$validation->add('email', new NotDisposableEmail(['message' => 'Disposable addresses (:domain) are not allowed']));
$validation->add('email', new VerifiedEmail(['messages' => ['mx' => 'The domain of :field cannot receive email']]));

$messages = $validation->validate($_POST);
```

### Plain PHP / any framework

The core has no framework dependency: create an `EmailValidator`, keep it in your container, and
use `vendor/bin/email-validator sync` from cron. Slim, Mezzio, Laminas, Lumen, Nette, Spiral,
WordPress plugins — anything with Composer works.

```php
$validator = EmailValidator::fromConfigFile(__DIR__ . '/config/emailvalidator.php');
```

---

## 💻 Command line

```text
vendor/bin/email-validator check <email|domain>...   Is it disposable? Shows the matched entry and its source
vendor/bin/email-validator validate <email>...       Full validation (format, disposable, MX, ...)
vendor/bin/email-validator sync                      Download remote lists into the synced list file
vendor/bin/email-validator stats                     List statistics

Options: --config=FILE  --path=FILE  --url=URL (repeatable)  --timeout=SECONDS
         --replace  --no-mx  --no-subdomains  --json  -h/--help  -V/--version
```

```console
$ vendor/bin/email-validator check user@eu.mailinator.com gmail.com 163.com
DISPOSABLE user@eu.mailinator.com (matched mailinator.com in blocklist)
OK         gmail.com
ALLOWED    163.com (matched 163.com in allowlist)
```

Exit codes: `0` = nothing flagged, `1` = disposable/invalid found or sync failed, `2` = usage error —
handy for shell scripts and CI (e.g. validating a CSV export).

---

## ⚡ Performance

- **Lookups are O(1)** hash lookups; a check including the parent-domain walk takes ~2.5 µs.
- **With OPcache** (every production PHP-FPM setup) the precompiled `data/blocklist.php` index is an
  immutable array in shared memory: loading the whole list costs nothing and adds **no per-request memory**.
- **Without OPcache** (CLI, workers) the list is read with a single `explode()` in ~18 ms, once per process.
- Runtime list changes (`addToBlocklist()`, custom/synced lists) are kept in small side tables, so the
  shared index is never copied.
- MX and catch-all results are cached through the configured cache backend.

---

## Caching, rate limiting & logging

```php
ConfigurationBuilder::create()->withMemoryCache(3600);                       // in-process (default)
ConfigurationBuilder::create()->withFileCache('/tmp/ev-cache', 3600);        // filesystem
ConfigurationBuilder::create()->withRedisCache('127.0.0.1', 6379, null, 0);  // ext-redis or Predis
ConfigurationBuilder::create()->withMemcachedCache('127.0.0.1', 11211);      // ext-memcached
ConfigurationBuilder::create()->withPsr16Cache($anyPsr16Cache);              // any PSR-16 cache
ConfigurationBuilder::create()->withoutCache();

CacheManager::registerDriver('my-driver', MyCacheAdapter::class);           // custom drivers
```

Rate limiting throttles validations per address with a token bucket that shares the cache backend
(works across processes with Redis/Memcached/File):

```php
$config = ConfigurationBuilder::create()->standard()->withRateLimiting(maxAttempts: 5, decaySeconds: 60)->build();

try {
    $validator->validate($email);
} catch (RateLimitExceededException $e) {
    echo "Retry after {$e->getRetryAfter()}s";
}
```

PSR-3 logging: `$validator->setLogger($logger)` or `$config->setLogger($logger)` — each `validate()`
logs a debug entry with the outcome, checks and errors.

---

## 📖 Result objects & API reference

<details>
<summary><b>ValidationResult</b></summary>

`isValid, getEmail, getDomain, getLocalPart, getChecks, passed(check), getErrors, getFirstError, getWarnings,
isDisposable, isRoleBased, isSubaddressed, getMetadata, getMetadataValue, hasSuggestion, getSuggestion, toArray, toJson`.

Metadata keys: `disposable_match`, `disposable_source`, `subaddressed`, `base_email`.
</details>

<details>
<summary><b>DisposableCheckResult</b></summary>

`getInput, getDomain, isDisposable, getMatchedDomain, getSource, isAllowlisted, isSubdomainMatch, toArray, jsonSerialize`.
Source constants: `SOURCE_BLOCKLIST`, `SOURCE_CUSTOM`, `SOURCE_SYNCED`, `SOURCE_ALLOWLIST`.
</details>

<details>
<summary><b>SuggestionResult</b></summary>

`getOriginalEmail, getSuggestedEmail, getOriginalDomain, getSuggestedDomain, getReason, getConfidence, isHighConfidence, toArray, __toString`.
</details>

<details>
<summary><b>EmailValidator</b></summary>

| Method | Description |
|--------|-------------|
| `create()` / `withConfig($c)` / `fromConfigFile($path)` / `strict()` / `basic()` | Factories |
| `validate($email): ValidationResult` | Full pipeline |
| `isValid($email, ?bool $checkMX = null): bool` | Boolean validation |
| `validateBatch($emails)` | `array<string, ValidationResult>` |
| `checkDisposable($emailOrDomain): DisposableCheckResult` | Explained disposable lookup |
| `isDisposable($email)` / `isDisposableDomain($domain)` | Disposable checks |
| `isValidFormat / hasValidMX / hasValidDNS / isSpoofed / isRoleBased / isSubaddressed / getBaseEmail / getSuggestion / isCatchAll / validateSMTP` | Individual checks |
| `getListStatistics(): array` | Counts, sources, last sync, index strategy |
| `addToBlocklist / addToAllowlist / removeFrom* / getBlocklist / getAllowlist / get*Count / isBlocklisted / isAllowlisted` | List management |
| `validateMultiple / validateWithDetails / filterValid / filterInvalid / getStatistics` | 2.x-compatible helpers |
| `extractDomain / extractLocalPart / normalize / normalizeMultiple` | Utilities |
| `setConfiguration / getConfiguration / setLogger / setCacheEnabled / clearCache` | Runtime |
</details>

<details>
<summary><b>ConfigurationBuilder</b></summary>

Presets `strict/standard/basic/minimal`; cache `withMemoryCache/withFileCache/withRedisCache/withMemcachedCache/withPsr16Cache/withCache/withoutCache`;
`withRateLimiting/withoutRateLimiting`; checks `withRoleBasedCheck/withTypoSuggestion/withSmtpCheck/withSubaddressCheck/withCatchAllCheck/withSpoofCheck/withoutMxCheck/withoutDisposableCheck`;
format `withFormatMode`; lists `withBlocklist/withAllowlist/withCustomBlocklist/withCustomAllowlist/withCustomBlocklistFiles/withCustomAllowlistFiles/withSyncedBlocklist/withoutSubdomainBlocking`; `build()`.
</details>

<details>
<summary><b>Fetcher (list files)</b></summary>

`loadBlocklist, loadAllowlist, loadAll, loadBlocklistIndex, loadAllowlistIndex, loadCustomBlocklist, loadCustomAllowlist, loadPaths,
mergeLists, saveList, compileIndex, getMetadata, clearCache, getBlocklistPath, getBlocklistIndexPath, getAllowlistPath, getMetadataPath,
blocklistExists, allowlistExists, getBlocklistCount, getAllowlistCount, isOpcacheActive`.
</details>

<details>
<summary><b>Exceptions</b></summary>

All extend `HarunGecit\EmailValidator\Exceptions\EmailValidatorException`:
`RateLimitExceededException` (`getRetryAfter()`, `getMaxAttempts()`), `InvalidEmailException`,
`ConfigurationException` (invalid value, unsupported format, missing file or dependency),
`CacheException`, `SmtpConnectionException`.
</details>

---

## Testing

```bash
composer test           # PHPUnit: core, data integrity and every framework integration
composer test-coverage  # HTML coverage report in ./coverage
composer update-lists   # maintainers: rebuild data/ from all upstream sources
```

The library runs on PHP 8.0+; the test suite needs PHP 8.1+ (PHPUnit 10–12). CI runs PHP 8.1 – 8.5 on
Ubuntu, Windows and macOS, the oldest supported framework versions on PHP 8.1, a PHP 8.0 runtime
smoke test, and data-integrity checks on every push.

---

## Upgrading

**3.2** is backward compatible with 3.0/3.1 and the 2.x API. Worth knowing:

- The bundled list grew from ~8,900 to over **136,000** domains in 3.2.0. If a legitimate domain your users rely
  on is now flagged, add it to `lists.custom_allowlist` and [report it](CONTRIBUTING.md).
- Your `custom_blocklist` / `addToBlocklist()` entries now **override the bundled allowlist**
  (previously the allowlist always won). The bundled blocklist still never overrides the allowlist.
- `EmailValidator::create()` now shares the precompiled index instead of copying the list.
- List files written by `Fetcher::saveList()` end with a newline.

Coming from **1.x**? The namespace changed from `PHPOrbit\EmailValidator` to `HarunGecit\EmailValidator`,
and the package from `phporbit/php-email-validator` to `harungecit/php-email-validator`.

---

## Contributing

Contributions are welcome — missing disposable domains, false-positive reports, framework
integrations and docs. Please read [CONTRIBUTING.md](CONTRIBUTING.md); security issues go through
[SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE). The disposable domain data is derived from
[disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) (CC0)
and [eramitgupta/disposable-email](https://github.com/eramitgupta/disposable-email) (MIT); thanks to
their maintainers and to the [Public Suffix List](https://publicsuffix.org) and [Tranco](https://tranco-list.eu) projects.

## Author

**Harun Geçit** — [GitHub](https://github.com/harungecit) · [LinkedIn](https://linkedin.com/in/harungecit) · [Email](mailto:info@harungecit.com)

If this package saves you from fake sign-ups, please ⭐ the repository — it helps other PHP developers find it.

---

## Changelog

Automated weekly data releases (blocklist updates) are documented on the
[Releases](https://github.com/harungecit/php-email-validator/releases) page.

### v3.2.0
- **136,577 disposable domains at release** (up from 8,948): the blocklist is now built from
  [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) (trusted) and
  [eramitgupta/disposable-email](https://github.com/eramitgupta/disposable-email) (filtered aggregator).
- False-positive filtering in the build: Public Suffix List, Tranco top-100k, typo domains, allowlist subdomains and
  upstream merge artifacts; allowlist expanded to 237 domains (ISPs, organisations, privacy relays such as SimpleLogin and Proton Pass).
- The list is rebuilt **daily** on `main`; patch releases still ship every Monday (skipped automatically when `main` has unreleased code).
- New `checkDisposable()` returning `DisposableCheckResult` (matched domain, source, subdomain match), `isDisposableDomain()`,
  `isSpoofed()` and `getListStatistics()`; `validate()` adds `disposable_match`/`disposable_source` metadata.
- New configuration: `lists.custom_blocklist`, `custom_allowlist`, `custom_*_paths`, `synced_blocklist_path`, `block_subdomains`,
  `sync.urls`, `sync.timeout`, `format.mode` (`filter`, `filter_unicode`, `rfc`, `strict`) and the `spoof` check.
- New list synchronizer (`ListSynchronizer`) with append-only/replace modes, HTML/empty-response rejection, allowlist filtering,
  atomic writes and locking.
- New framework-agnostic CLI: `vendor/bin/email-validator check|validate|sync|stats`.
- Precompiled OPcache index (`data/blocklist.php`): zero load cost and no per-request memory with OPcache; runtime list changes no longer copy the list.
- Domain normalization (case, trailing dot, IDN → punycode) for every list lookup.
- New integrations: **CodeIgniter 4** (auto-discovered rules + service), **CakePHP** (validation provider + plugin),
  **Yii 2** (component + validators), **Yii 3** (attribute rules), **Phalcon 5** (validators + service provider).
- Laravel: rule objects, `not_spoofed` rule, `@disposableEmail` Blade directive, Artisan commands, scheduled sync, Laravel Boost guidelines.
  Symfony: `#[NotDisposableEmail]` / `#[ValidEmail]` constraints and console commands. Laravel 13 and Symfony 8 support.
- Application lists (`custom_*`) now take precedence over the bundled allowlist.
- Added CONTRIBUTING.md and SECURITY.md; leaner Composer dist archive.

### v3.1.1
- Updated the disposable blocklist to 8,900+ domains (785 new domains from upstream).
- `isDisposable()` now detects subdomains of disposable providers (`user@sub.mailinator.com`) by also checking parent domains; the most specific list entry wins. Lookups use a hash index instead of a linear scan.
- Added a scheduled GitHub Actions workflow that refreshes the blocklist weekly and publishes a patch release automatically.
- `composer update-lists` now rebuilds the blocklist as `upstream + data/blocklist.local.conf − allowlist` instead of a keep-everything union, so upstream false-positive removals are applied (removed `gmal.com`, `iwi.net`, `mailhub.pro`). Added safety checks for unattended runs (malformed lines skipped, abort on a suspiciously small upstream or a >5% shrink; `--force` overrides).

### v3.1.0
- Updated the disposable blocklist to 8,100+ domains (244 new domains from the [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) project).
- Expanded the allowlist to 190+ domains (added `proton.me`, `pm.me`, `hey.com`, `tuta.com`, `zohomail.com`, `icloud.com`, `duck.com`, `simplelogin.io`, `addy.io`, and more). The upstream allowlist was discontinued; it is now maintained in this package.
- Added `composer update-lists` (`scripts/update-lists.php`) to refresh the blocklist from upstream with a single command.
- Expanded the default typo mappings and common-domain list (Proton, Tuta, HEY, Zoho Mail, GMX, Yandex, and regional Yahoo/Hotmail/Outlook domains) and synchronized them across all config templates.
- Expanded the default role-based prefixes (`administrator`, `donotreply`, `security`, `privacy`, `legal`, `compliance`, `notifications`, and more).
- `Fetcher::saveList()` now always writes LF line endings regardless of platform.

### v3.0.1
- Fixed CI failures on PHP 8.0 and 8.1.

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
