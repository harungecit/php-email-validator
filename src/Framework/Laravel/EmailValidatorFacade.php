<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel;

use Illuminate\Support\Facades\Facade;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Result\ValidationResult;
use HarunGecit\EmailValidator\Result\SuggestionResult;

/**
 * Class EmailValidatorFacade
 *
 * Laravel facade for email validator.
 *
 * @method static ValidationResult validate(string $email)
 * @method static bool isValid(string $email, ?bool $checkMX = null)
 * @method static bool isValidFormat(string $email)
 * @method static bool isDisposable(string $email)
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
 * @method static EmailValidator addToBlocklist(string $domain)
 * @method static EmailValidator addToAllowlist(string $domain)
 * @method static EmailValidator addRoleBasedPrefix(string $prefix)
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
