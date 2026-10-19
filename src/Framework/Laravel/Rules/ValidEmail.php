<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Rules;

use Closure;

/**
 * Class ValidEmail
 *
 * Runs the full validation pipeline configured in config/emailvalidator.php
 * (format, disposable, MX, role-based, spoof, SMTP, catch-all — whichever
 * checks are enabled) and reports the first failing check with its own
 * translatable message (`not_disposable`, `has_mx`, `not_role_based`, ...).
 *
 * ```php
 * use HarunGecit\EmailValidator\Framework\Laravel\Rules\ValidEmail;
 *
 * 'email' => ['required', new ValidEmail()],
 * 'email' => ['required', new ValidEmail(checkMx: false)],
 * ```
 *
 * Message replacements: :attribute, :reason (the validator's error) and
 * :suggestion (typo suggestion such as "user@gmail.com", or empty).
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Rules
 */
class ValidEmail extends EmailRule
{
    /**
     * Message keys by failing check name.
     */
    public const CHECK_MESSAGES = [
        'format' => 'valid_email',
        'disposable' => 'not_disposable',
        'mx' => 'has_mx',
        'role_based' => 'not_role_based',
        'spoof' => 'not_spoofed',
        'smtp' => 'smtp',
        'catch_all' => 'catch_all',
    ];

    /**
     * @param bool|null $checkMx Override the configured MX check (null = use configuration).
     * @param string|null $message Custom message or translation key for every failure.
     */
    public function __construct(protected ?bool $checkMx = null, ?string $message = null)
    {
        parent::__construct($message);
    }

    /**
     * {@inheritdoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $this->fail($fail, 'valid_email', ['reason' => 'Invalid email format', 'suggestion' => '']);
            return;
        }

        $validator = $this->emailValidator();
        $config = $validator->getConfiguration();
        $mxEnabled = $config->isMxCheckEnabled();

        if ($this->checkMx !== null) {
            $config->enableMxCheck($this->checkMx);
        }

        try {
            $result = $validator->validate($value);
        } finally {
            $config->enableMxCheck($mxEnabled);
        }

        if ($result->isValid()) {
            return;
        }

        $key = 'valid_email';
        foreach ($result->getChecks() as $check => $passed) {
            if (!$passed) {
                $key = self::CHECK_MESSAGES[$check] ?? 'valid_email';
                break;
            }
        }

        $suggestion = $result->getSuggestion();

        $this->fail($fail, $key, [
            'reason' => (string) ($result->getFirstError() ?? 'Invalid email address'),
            'suggestion' => $suggestion !== null ? $suggestion->getSuggestedEmail() : '',
        ]);
    }
}
