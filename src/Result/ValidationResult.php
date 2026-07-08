<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Result;

/**
 * Class ValidationResult
 *
 * Represents the result of an email validation.
 *
 * @package HarunGecit\EmailValidator\Result
 */
class ValidationResult
{
    /**
     * @var string The validated email address.
     */
    private string $email;

    /**
     * @var bool Overall validity.
     */
    private bool $isValid = true;

    /**
     * @var array<string, bool> Individual check results.
     */
    private array $checks = [];

    /**
     * @var array<string> Error messages.
     */
    private array $errors = [];

    /**
     * @var array<string> Warning messages.
     */
    private array $warnings = [];

    /**
     * @var SuggestionResult|null Typo suggestion if available.
     */
    private ?SuggestionResult $suggestion = null;

    /**
     * @var array<string, mixed> Additional metadata.
     */
    private array $metadata = [];

    /**
     * ValidationResult constructor.
     *
     * @param string $email The email address being validated.
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * Adds a check result.
     *
     * @param string $name Check name.
     * @param bool $passed Whether the check passed.
     * @param string|null $error Error message if failed.
     * @return self
     */
    public function addCheck(string $name, bool $passed, ?string $error = null): self
    {
        $this->checks[$name] = $passed;

        if (!$passed) {
            $this->isValid = false;
            if ($error !== null) {
                $this->errors[] = $error;
            }
        }

        return $this;
    }

    /**
     * Adds a warning (doesn't affect validity).
     *
     * @param string $warning Warning message.
     * @return self
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    /**
     * Sets the typo suggestion.
     *
     * @param SuggestionResult $suggestion The suggestion result.
     * @return self
     */
    public function setSuggestion(SuggestionResult $suggestion): self
    {
        $this->suggestion = $suggestion;
        return $this;
    }

    /**
     * Adds metadata.
     *
     * @param string $key Metadata key.
     * @param mixed $value Metadata value.
     * @return self
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Gets the email address.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Checks if the email is valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Gets all check results.
     *
     * @return array<string, bool>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Checks if a specific check passed.
     *
     * @param string $check Check name.
     * @return bool|null True if passed, false if failed, null if not performed.
     */
    public function passed(string $check): ?bool
    {
        return $this->checks[$check] ?? null;
    }

    /**
     * Gets all error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Gets all warning messages.
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Gets the first error message.
     *
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Checks if there's a typo suggestion.
     *
     * @return bool
     */
    public function hasSuggestion(): bool
    {
        return $this->suggestion !== null;
    }

    /**
     * Gets the typo suggestion.
     *
     * @return SuggestionResult|null
     */
    public function getSuggestion(): ?SuggestionResult
    {
        return $this->suggestion;
    }

    /**
     * Gets the domain from the email.
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        $atPos = strrpos($this->email, '@');
        return $atPos !== false ? strtolower(substr($this->email, $atPos + 1)) : null;
    }

    /**
     * Gets the local part from the email.
     *
     * @return string|null
     */
    public function getLocalPart(): ?string
    {
        $atPos = strrpos($this->email, '@');
        return $atPos !== false ? substr($this->email, 0, $atPos) : null;
    }

    /**
     * Gets metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Gets a specific metadata value.
     *
     * @param string $key Metadata key.
     * @param mixed $default Default value if not found.
     * @return mixed
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Checks if the email is disposable.
     *
     * @return bool|null True if disposable, false if not, null if not checked.
     */
    public function isDisposable(): ?bool
    {
        if (!isset($this->checks['disposable'])) {
            return null;
        }
        // disposable check passes when email is NOT disposable
        return !$this->checks['disposable'];
    }

    /**
     * Checks if the email is role-based.
     *
     * @return bool|null True if role-based, false if not, null if not checked.
     */
    public function isRoleBased(): ?bool
    {
        if (!isset($this->checks['role_based'])) {
            return null;
        }
        // role_based check passes when email is NOT role-based
        return !$this->checks['role_based'];
    }

    /**
     * Checks if the email is subaddressed (plus addressing).
     *
     * @return bool|null
     */
    public function isSubaddressed(): ?bool
    {
        return $this->metadata['subaddressed'] ?? null;
    }

    /**
     * Converts the result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'valid' => $this->isValid,
            'checks' => $this->checks,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'domain' => $this->getDomain(),
            'local_part' => $this->getLocalPart(),
            'suggestion' => $this->suggestion?->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Converts the result to JSON.
     *
     * @param int $flags JSON encode flags.
     * @return string
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags) ?: '{}';
    }

    /**
     * Creates a result for a valid email.
     *
     * @param string $email The email address.
     * @return self
     */
    public static function valid(string $email): self
    {
        $result = new self($email);
        $result->addCheck('format', true);
        return $result;
    }

    /**
     * Creates a result for an invalid email format.
     *
     * @param string $email The email address.
     * @return self
     */
    public static function invalidFormat(string $email): self
    {
        $result = new self($email);
        $result->addCheck('format', false, 'Invalid email format');
        return $result;
    }
}
