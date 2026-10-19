<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Framework\Phalcon\Validator;

use Phalcon\Filter\Validation;

/**
 * Class NotDisposableEmail
 *
 * Phalcon 5 validator rejecting email addresses from disposable providers
 * (subdomains of listed providers included, the allowlist honoured).
 *
 * ```php
 * use Phalcon\Filter\Validation;
 * use Phalcon\Filter\Validation\Validator\Email;
 * use HarunGecit\EmailValidator\Framework\Phalcon\Validator\NotDisposableEmail;
 *
 * $validation = new Validation();
 * $validation->add('email', new Email());
 * $validation->add('email', new NotDisposableEmail([
 *     'message'    => 'Disposable addresses (:domain) are not allowed',
 *     'allowEmpty' => true,
 * ]));
 * ```
 *
 * Values without an "@" are not checked; combine with the built-in `Email`
 * validator for format validation. Non-string values fail.
 *
 * Message placeholders: `:field`, `:domain` (domain of the value) and
 * `:matched` (the blocklist entry that matched).
 *
 * Options: `message`/`template`, `label`, `code`, `allowEmpty`,
 * `cancelOnFail`, `validator` (EmailValidator instance), `service`
 * (DI service name, default `emailValidator`; `false` disables the lookup).
 *
 * @package HarunGecit\EmailValidator\Framework\Phalcon\Validator
 */
class NotDisposableEmail extends AbstractEmailValidator
{
    /**
     * @var string|null Default message template.
     */
    protected $template = 'Field :field must not be a disposable email address';

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
            $validation->appendMessage($this->messageFactory($validation, $field, [':domain' => '', ':matched' => '']));

            return false;
        }

        if (strpos($value, '@') === false) {
            return true;
        }

        $check = $this->getEmailValidator($validation)->checkDisposable($value);

        if (!$check->isDisposable()) {
            return true;
        }

        $validation->appendMessage($this->messageFactory($validation, $field, [
            ':domain' => (string) $check->getDomain(),
            ':matched' => (string) $check->getMatchedDomain(),
        ]));

        return false;
    }
}
