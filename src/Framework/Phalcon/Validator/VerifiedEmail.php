<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Phalcon\Validator;

use Phalcon\Filter\Validation;

/**
 * Class VerifiedEmail
 *
 * Phalcon 5 validator running the full
 * {@see \HarunGecit\EmailValidator\EmailValidator::validate()} pipeline
 * (format, disposable, MX, role-based, SMTP, ... as enabled in the resolved
 * validator's configuration).
 *
 * ```php
 * use Phalcon\Filter\Validation;
 * use HarunGecit\EmailValidator\Framework\Phalcon\Validator\VerifiedEmail;
 *
 * $validation = new Validation();
 * $validation->add('email', new VerifiedEmail([
 *     'messages' => ['disposable' => 'Please use a permanent email address'],
 * ]));
 * ```
 *
 * Message selection, most specific first:
 * 1. the `messages` option entry for the first failed check (keys: format,
 *    spoof, disposable, mx, role_based, smtp, catch_all);
 * 2. an explicit `message`/`template` option (string or per-field array);
 * 3. the built-in message for the failed check ({@see DEFAULT_MESSAGES});
 * 4. the class default template.
 *
 * Message placeholders: `:field`, `:check` (failed check name), `:error`
 * (the library's first error message) and `:suggestion` (typo suggestion or
 * empty string).
 *
 * Options: `message`/`template`, `messages`, `label`, `code`, `allowEmpty`,
 * `cancelOnFail`, `validator` (EmailValidator instance), `service`
 * (DI service name, default `emailValidator`; `false` disables the lookup).
 *
 * @package HarunGecit\EmailValidator\Framework\Phalcon\Validator
 */
class VerifiedEmail extends AbstractEmailValidator
{
    /**
     * Built-in messages per failed check.
     *
     * @var array<string, string>
     */
    public const DEFAULT_MESSAGES = [
        'format' => 'Field :field must be a valid email address',
        'spoof' => 'Field :field contains suspicious characters',
        'disposable' => 'Field :field must not be a disposable email address',
        'mx' => 'The domain of field :field cannot receive email',
        'role_based' => 'Field :field must not be a role-based email address',
        'smtp' => 'The mailbox of field :field could not be verified',
        'catch_all' => 'Field :field must not use a catch-all domain',
    ];

    /**
     * @var string|null Default message template.
     */
    protected $template = 'Field :field must be a valid email address';

    /**
     * Executes the validation.
     *
     * @param Validation $validation The validation being run.
     * @param mixed $field The field name.
     * @return bool
     */
    public function validate(Validation $validation, $field): bool
    {
        $field = (string) $field;
        $value = $validation->getValue($field);

        if ($this->allowEmpty($field, $value)) {
            return true;
        }

        if (!is_string($value)) {
            $this->appendError($validation, $field, $this->resolveTemplate($field, 'format'), [
                ':check' => 'format',
                ':error' => 'Invalid email format',
                ':suggestion' => '',
            ]);

            return false;
        }

        $result = $this->getEmailValidator($validation)->validate($value);

        if ($result->isValid()) {
            return true;
        }

        $failedCheck = (string) array_search(false, $result->getChecks(), true);
        $suggestion = $result->getSuggestion();

        $this->appendError($validation, $field, $this->resolveTemplate($field, $failedCheck), [
            ':check' => $failedCheck,
            ':error' => (string) $result->getFirstError(),
            ':suggestion' => $suggestion !== null ? $suggestion->getSuggestedEmail() : '',
        ]);

        return false;
    }

    /**
     * Picks the message template for a failed check.
     *
     * @param string $field The field name.
     * @param string $check The failed check name.
     * @return string
     */
    protected function resolveTemplate(string $field, string $check): string
    {
        $messages = $this->getOption('messages', []);
        if (is_array($messages) && isset($messages[$check])) {
            return (string) $messages[$check];
        }

        if (!$this->hasCustomTemplate($field) && isset(self::DEFAULT_MESSAGES[$check])) {
            return self::DEFAULT_MESSAGES[$check];
        }

        return $this->getTemplate($field);
    }
}
