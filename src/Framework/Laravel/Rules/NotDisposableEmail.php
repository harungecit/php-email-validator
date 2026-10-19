<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Laravel\Rules;

use Closure;

/**
 * Class NotDisposableEmail
 *
 * Fails when the email address belongs to a disposable / temporary email
 * provider (bundled blocklist + custom and synced lists, subdomains included,
 * allowlist honoured).
 *
 * ```php
 * use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotDisposableEmail;
 *
 * $request->validate([
 *     'email' => ['required', 'email', new NotDisposableEmail()],
 * ]);
 * ```
 *
 * Non-string values and values without "@" are ignored: combine with the
 * `email` rule to enforce the format.
 *
 * Message replacements: :attribute, :domain, :matched.
 *
 * @package HarunGecit\EmailValidator\Framework\Laravel\Rules
 */
class NotDisposableEmail extends EmailRule
{
    /**
     * {@inheritdoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || strpos($value, '@') === false) {
            return;
        }

        $check = $this->emailValidator()->checkDisposable($value);

        if ($check->isDisposable()) {
            $this->fail($fail, 'not_disposable', [
                'domain' => (string) $check->getDomain(),
                'matched' => (string) $check->getMatchedDomain(),
            ]);
        }
    }
}
