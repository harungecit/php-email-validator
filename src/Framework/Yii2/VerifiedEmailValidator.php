<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Yii2;

use HarunGecit\EmailValidator\EmailValidator;
use Yii;

/**
 * Class VerifiedEmailValidator
 *
 * Yii2 validator running the full {@see EmailValidator::validate()} pipeline
 * (format, disposable, MX, role-based, SMTP, ... as enabled in the resolved
 * validator's configuration).
 *
 * ```php
 * public function rules()
 * {
 *     return [
 *         ['email', VerifiedEmailValidator::class],
 *         // custom messages per failed check:
 *         ['email', VerifiedEmailValidator::class, 'messages' => [
 *             'disposable' => 'Please use a permanent email address.',
 *         ]],
 *     ];
 * }
 * ```
 *
 * The error message is chosen by the first failed check: {@see $messages}
 * (keyed by check name: format, spoof, disposable, mx, role_based, smtp,
 * catch_all) falls back to {@see $message}.
 *
 * Message placeholders: `{attribute}`, `{value}`, `{check}` (failed check
 * name), `{error}` (the library's first error message) and `{suggestion}`
 * (typo suggestion if `typo_suggestion` is enabled, else empty).
 *
 * @package HarunGecit\EmailValidator\Framework\Yii2
 */
class VerifiedEmailValidator extends AbstractEmailValidator
{
    /**
     * @var array<string, string> Error messages keyed by failed check name; merged over the defaults.
     */
    public $messages = [];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->message === null) {
            $this->message = Yii::t('app', '{attribute} is not a valid email address.');
        }

        $this->messages = array_merge([
            'format' => Yii::t('app', '{attribute} is not a valid email address.'),
            'spoof' => Yii::t('app', '{attribute} contains suspicious characters.'),
            'disposable' => Yii::t('app', '{attribute} must not be a disposable email address.'),
            'mx' => Yii::t('app', 'The domain of {attribute} cannot receive email.'),
            'role_based' => Yii::t('app', '{attribute} must not be a role-based email address.'),
            'smtp' => Yii::t('app', 'The mailbox of {attribute} could not be verified.'),
            'catch_all' => Yii::t('app', '{attribute} must not use a catch-all domain.'),
        ], (array) $this->messages);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateEmail(string $email, EmailValidator $validator): ?array
    {
        $result = $validator->validate($email);

        if ($result->isValid()) {
            return null;
        }

        $failedCheck = (string) array_search(false, $result->getChecks(), true);
        $suggestion = $result->getSuggestion();

        return [$this->messages[$failedCheck] ?? (string) $this->message, [
            'check' => $failedCheck,
            'error' => (string) $result->getFirstError(),
            'suggestion' => $suggestion !== null ? $suggestion->getSuggestedEmail() : '',
        ]];
    }
}
