<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii2;

use HarunGecit\EmailValidator\EmailValidator;
use Yii;

/**
 * Class NotDisposableEmailValidator
 *
 * Yii2 validator rejecting email addresses from disposable providers
 * (including subdomains of listed providers, honouring the allowlist).
 *
 * ```php
 * public function rules()
 * {
 *     return [
 *         ['email', 'email'],
 *         ['email', NotDisposableEmailValidator::class],
 *     ];
 * }
 * ```
 *
 * Values without an "@" are not checked (combine with the built-in `email`
 * validator for format validation).
 *
 * Message placeholders: `{attribute}`, `{value}`, `{domain}` (domain of the
 * value) and `{matched}` (the blocklist entry that matched).
 *
 * @package HarunGecit\EmailValidator\Framework\Yii2
 */
class NotDisposableEmailValidator extends AbstractEmailValidator
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->message === null) {
            $this->message = Yii::t('app', '{attribute} must not be a disposable email address.');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateEmail(string $email, EmailValidator $validator): ?array
    {
        if (strpos($email, '@') === false) {
            return null;
        }

        $check = $validator->checkDisposable($email);

        if (!$check->isDisposable()) {
            return null;
        }

        return [(string) $this->message, [
            'domain' => (string) $check->getDomain(),
            'matched' => (string) $check->getMatchedDomain(),
        ]];
    }
}
