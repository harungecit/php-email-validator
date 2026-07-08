<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Contracts;

/**
 * Interface SmtpVerifierInterface
 *
 * Defines the contract for SMTP verification implementations.
 *
 * @package HarunGecit\EmailValidator\Contracts
 */
interface SmtpVerifierInterface
{
    /**
     * Verifies if an email address exists via SMTP.
     *
     * @param string $email The email address to verify.
     * @return bool True if the email address appears to exist, false otherwise.
     */
    public function verify(string $email): bool;

    /**
     * Checks if a domain is configured as a catch-all.
     *
     * @param string $domain The domain to check.
     * @return bool True if the domain accepts all email addresses.
     */
    public function isCatchAll(string $domain): bool;

    /**
     * Gets the last error message from SMTP verification.
     *
     * @return string|null The last error message, or null if no error.
     */
    public function getLastError(): ?string;
}
