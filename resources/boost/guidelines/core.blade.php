@verbatim
## harungecit/php-email-validator

Email validation and disposable (temporary) email detection for Laravel: format, disposable domains (bundled list of 8,000+ domains, subdomains included, plus custom and auto-synced lists), MX records, role-based addresses, plus addressing, homograph spoofing, typo suggestions and optional SMTP checks. The service provider and the `EmailValidator` facade alias are auto-discovered.

### Validation rules

- Prefer the rule objects (Laravel 10+) in `HarunGecit\EmailValidator\Framework\Laravel\Rules`:
  - `new NotDisposableEmail()` rejects disposable providers only (cheap, no DNS). Ignores values without "@", so keep the built-in `email` rule for format checks.
  - `new ValidEmail()` runs every check enabled in `config/emailvalidator.php` and reports the first failing one. `new ValidEmail(checkMx: false)` skips DNS for this rule.
  - `new NotRoleBasedEmail()` rejects admin@, info@, support@ and other configured prefixes.
  - All accept a custom message or translation key: `new NotDisposableEmail('Use a permanent address.')`.
- String rules are also registered: `valid_email`, `not_disposable`, `not_role_based`, `has_mx`, `not_subaddressed`, `not_spoofed`.

<code-snippet name="Reject disposable emails in a form request" lang="php">
use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\ValidEmail;

public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255', new NotDisposableEmail()],
        // or the full configured pipeline (format, disposable, MX, ...):
        'work_email' => ['required', new ValidEmail()],
        // string rule form:
        'backup_email' => 'nullable|email|not_disposable|not_role_based',
    ];
}
</code-snippet>

### Facade / service

Resolve `HarunGecit\EmailValidator\EmailValidator` from the container (singleton, configured from `config/emailvalidator.php`) or use the facade. Do not instantiate `new EmailValidator()` inside a Laravel app, because that ignores the app configuration.

<code-snippet name="Programmatic checks" lang="php">
use HarunGecit\EmailValidator\Framework\Laravel\EmailValidatorFacade as EmailValidator;

EmailValidator::isDisposable('user@mailinator.com');      // true
EmailValidator::isDisposableDomain('eu.mailinator.com');  // true (subdomain match)

$check = EmailValidator::checkDisposable('user@eu.mailinator.com');
$check->getMatchedDomain();  // 'mailinator.com'
$check->getSource();         // 'blocklist' | 'custom' | 'synced' | 'allowlist' | null

$result = EmailValidator::validate('john@gmial.com');
$result->isValid();
$result->getErrors();
$result->getSuggestion()?->getSuggestedEmail(); // 'john@gmail.com'

EmailValidator::getListStatistics(); // blocklist/custom/synced/allowlist counts, last_synced, ...
</code-snippet>

### Blade

<code-snippet name="Blade directive" lang="blade">
@disposableEmail($user->email)
    <p>Please switch to a permanent email address.</p>
@enddisposableEmail
</code-snippet>

### Configuration (config/emailvalidator.php)

- Publish with `php artisan email-validator:install` (or `vendor:publish --tag=emailvalidator-config`). Translations: `--tag=emailvalidator-translations` (namespace `emailvalidator::validation.*`).
- `checks.*` enables the checks (`mx`, `disposable`, `role_based`, `smtp`, `spoof`, ...). Keep `smtp` and `catch_all` off unless explicitly required, because they are slow and often blocked.
- `format.mode`: `filter` (default), `filter_unicode`, `rfc`, `strict` (`rfc`/`strict` need egulias/email-validator).
- `lists.custom_blocklist` / `lists.custom_allowlist` (inline domains) and `lists.custom_blocklist_paths` / `lists.custom_allowlist_paths` (files) extend the bundled lists. The allowlist always wins. `lists.block_subdomains` (default true) also blocks subdomains.
- `lists.synced_blocklist_path` (default `storage/app/email-validator/synced-blocklist.txt`) is written by the sync command. `sync.urls` sets the remote lists (env `EMAIL_VALIDATOR_SYNC_URLS`, comma-separated), and `sync.timeout` sets the HTTP timeout.
- `sync.schedule` (env `EMAIL_VALIDATOR_SYNC_SCHEDULE`: `hourly`, `daily`, `weekly`, `monthly` or a cron expression) schedules the sync automatically. Do not add a duplicate entry in `routes/console.php`.

### Artisan commands

- `php artisan email-validator:sync [--replace]` downloads the latest disposable domains. A failed download never damages the existing list.
- `php artisan email-validator:check user@example.com example.org [--json]` explains a disposable lookup (exit code 1 when disposable).
- `php artisan email-validator:validate user@example.com [--no-mx] [--json]` runs the full pipeline.
- `php artisan email-validator:stats [--json]` shows list sizes and the last sync time.

### Testing

Disable network checks in tests with `<env name="EMAIL_VALIDATOR_CHECK_MX" value="false"/>` in phpunit.xml. The configuration is read once when the `EmailValidator` singleton is built, so changing `config()` after it was resolved has no effect. Use well-known domains such as `mailinator.com` for disposable cases.
@endverbatim
