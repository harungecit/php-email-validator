<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Contracts;

use HarunGecit\EmailValidator\Result\ValidationResult;

/**
 * Interface ValidatorInterface
 *
 * Defines the contract for email validation implementations.
 *
 * @package HarunGecit\EmailValidator\Contracts
 */
interface ValidatorInterface
{
    /**
     * Validates an email address and returns detailed results.
     *
     * @param string $email The email address to validate.
     * @return ValidationResult The validation result with all check details.
     */
    public function validate(string $email): ValidationResult;

    /**
     * Checks if an email address is valid.
     *
     * @param string $email The email address to validate.
     * @return bool True if the email is valid, false otherwise.
     */
    public function isValid(string $email): bool;

    /**
     * Validates multiple email addresses at once.
     *
     * @param array<string> $emails Array of email addresses to validate.
     * @return array<string, ValidationResult> Array of validation results keyed by email.
     */
    public function validateBatch(array $emails): array;
}
