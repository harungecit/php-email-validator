<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel;

use Illuminate\Support\Facades\Facade;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Result\DisposableCheckResult;
use HarunGecit\EmailValidator\Result\ValidationResult;
use HarunGecit\EmailValidator\Result\SuggestionResult;

/**
 * Class EmailValidatorFacade
 *
 * Laravel facade for email validator.
 *
 * @method static ValidationResult validate(string $email)
 * @method static array<string, ValidationResult> validateBatch(array $emails)
 * @method static bool isValid(string $email, ?bool $checkMX = null)
 * @method static bool isValidFormat(string $email)
 * @method static bool isDisposable(string $email)
 * @method static bool isDisposableDomain(string $domain)
 * @method static DisposableCheckResult checkDisposable(string $emailOrDomain)
 * @method static bool|null isSpoofed(string $email)
 * @method static bool isAllowlisted(string $email)
 * @method static bool isBlocklisted(string $email)
 * @method static bool hasValidMX(string $email)
 * @method static bool hasValidDNS(string $email)
 * @method static bool isRoleBased(string $email)
 * @method static bool isSubaddressed(string $email)
 * @method static string|null getBaseEmail(string $email)
 * @method static SuggestionResult|null getSuggestion(string $email)
 * @method static bool isCatchAll(string $email)
 * @method static bool validateSMTP(string $email)
 * @method static array validateMultiple(array $emails, bool $checkMX = true)
 * @method static array validateWithDetails(string $email, bool $checkMX = true)
 * @method static array filterValid(array $emails, bool $checkMX = true)
 * @method static array filterInvalid(array $emails, bool $checkMX = true)
 * @method static array getStatistics(array $emails, bool $checkMX = true)
 * @method static string|null extractDomain(string $email)
 * @method static string|null extractLocalPart(string $email)
 * @method static string normalize(string $email)
 * @method static array normalizeMultiple(array $emails)
 * @method static EmailValidator addToBlocklist(string $domain)
 * @method static EmailValidator addMultipleToBlocklist(array $domains)
 * @method static EmailValidator removeFromBlocklist(string $domain)
 * @method static EmailValidator addToAllowlist(string $domain)
 * @method static EmailValidator addMultipleToAllowlist(array $domains)
 * @method static EmailValidator removeFromAllowlist(string $domain)
 * @method static EmailValidator addRoleBasedPrefix(string $prefix)
 * @method static array getBlocklist()
 * @method static array getAllowlist()
 * @method static int getBlocklistCount()
 * @method static int getAllowlistCount()
 * @method static array{blocklist: int, custom: int, synced: int, total_blocked: int, allowlist: int, block_subdomains: bool, synced_path: string|null, last_synced: string|null, bundled_generated_at: string|null, bundled_sources: array<string>, index_strategy: string} getListStatistics()
 * @method static Configuration getConfiguration()
 * @method static EmailValidator clearCache()
 *
 * @see \HarunGecit\EmailValidator\EmailValidator
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel
 */
class EmailValidatorFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'email-validator';
    }
}
