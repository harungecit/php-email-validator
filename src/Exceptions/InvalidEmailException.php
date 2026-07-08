<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Exceptions;

/**
 * Class InvalidEmailException
 *
 * Exception thrown when email validation fails.
 *
 * @package HarunGecit\EmailValidator\Exceptions
 */
class InvalidEmailException extends EmailValidatorException
{
    public const INVALID_FORMAT = 1;
    public const DISPOSABLE_DOMAIN = 2;
    public const INVALID_MX = 3;
    public const ROLE_BASED = 4;
    public const SMTP_FAILED = 5;
    public const CATCH_ALL = 6;
    public const DNS_FAILED = 7;

    /**
     * Creates an exception for invalid email format.
     *
     * @param string $email The invalid email address.
     * @return self
     */
    public static function invalidFormat(string $email): self
    {
        return new self(
            "Invalid email format: {$email}",
            self::INVALID_FORMAT,
            null,
            $email,
            ['reason' => 'invalid_format']
        );
    }

    /**
     * Creates an exception for disposable email domain.
     *
     * @param string $email The email address.
     * @param string $domain The disposable domain.
     * @return self
     */
    public static function disposableDomain(string $email, string $domain): self
    {
        return new self(
            "Disposable email domain detected: {$domain}",
            self::DISPOSABLE_DOMAIN,
            null,
            $email,
            ['reason' => 'disposable_domain', 'domain' => $domain]
        );
    }

    /**
     * Creates an exception for invalid MX record.
     *
     * @param string $email The email address.
     * @param string $domain The domain without valid MX.
     * @return self
     */
    public static function invalidMx(string $email, string $domain): self
    {
        return new self(
            "No valid MX record found for domain: {$domain}",
            self::INVALID_MX,
            null,
            $email,
            ['reason' => 'invalid_mx', 'domain' => $domain]
        );
    }

    /**
     * Creates an exception for role-based email address.
     *
     * @param string $email The email address.
     * @param string $localPart The role-based local part.
     * @return self
     */
    public static function roleBased(string $email, string $localPart): self
    {
        return new self(
            "Role-based email address detected: {$localPart}",
            self::ROLE_BASED,
            null,
            $email,
            ['reason' => 'role_based', 'local_part' => $localPart]
        );
    }

    /**
     * Creates an exception for SMTP verification failure.
     *
     * @param string $email The email address.
     * @param string $error The SMTP error message.
     * @return self
     */
    public static function smtpFailed(string $email, string $error): self
    {
        return new self(
            "SMTP verification failed: {$error}",
            self::SMTP_FAILED,
            null,
            $email,
            ['reason' => 'smtp_failed', 'smtp_error' => $error]
        );
    }

    /**
     * Creates an exception for catch-all domain.
     *
     * @param string $email The email address.
     * @param string $domain The catch-all domain.
     * @return self
     */
    public static function catchAllDomain(string $email, string $domain): self
    {
        return new self(
            "Catch-all domain detected: {$domain}",
            self::CATCH_ALL,
            null,
            $email,
            ['reason' => 'catch_all', 'domain' => $domain]
        );
    }

    /**
     * Creates an exception for DNS lookup failure.
     *
     * @param string $email The email address.
     * @param string $domain The domain that failed DNS lookup.
     * @return self
     */
    public static function dnsFailed(string $email, string $domain): self
    {
        return new self(
            "DNS lookup failed for domain: {$domain}",
            self::DNS_FAILED,
            null,
            $email,
            ['reason' => 'dns_failed', 'domain' => $domain]
        );
    }
}
