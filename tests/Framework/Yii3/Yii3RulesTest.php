<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Yii3;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\Yii3\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Yii3\NotDisposableEmailHandler;
use HarunGecit\EmailValidator\Framework\Yii3\VerifiedEmail;
use HarunGecit\EmailValidator\Framework\Yii3\VerifiedEmailHandler;
use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RuleHandlerResolver\SimpleRuleHandlerContainer;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\Validator;

/**
 * Tests the Yii3 (yiisoft/validator 2.x) rules and handlers.
 */
class Yii3RulesTest extends TestCase
{
    private EmailValidator $emailValidator;

    private Validator $validator;

    protected function setUp(): void
    {
        if (!interface_exists(\Yiisoft\Validator\RuleInterface::class)) {
            $this->markTestSkipped('yiisoft/validator is not installed.');
        }

        // No network: format + disposable + role-based checks only.
        $this->emailValidator = new EmailValidator(Configuration::fromArray([
            'checks' => [
                'format' => true,
                'mx' => false,
                'disposable' => true,
                'role_based' => true,
                'smtp' => false,
                'typo_suggestion' => true,
            ],
            'lists' => [
                'custom_blocklist' => ['trash-mail.test'],
                'custom_allowlist' => ['ok.trash-mail.test'],
            ],
        ]));

        $this->validator = new Validator(new SimpleRuleHandlerContainer([
            NotDisposableEmailHandler::class => new NotDisposableEmailHandler($this->emailValidator),
            VerifiedEmailHandler::class => new VerifiedEmailHandler($this->emailValidator),
        ]));
    }

    public function testNotDisposableAcceptsRegularEmail(): void
    {
        $result = $this->validator->validate('john@example.com', [new NotDisposableEmail()]);

        $this->assertTrue($result->isValid());
    }

    public function testNotDisposableRejectsBundledAndCustomDomains(): void
    {
        $result = $this->validator->validate('john@mailinator.com', [new NotDisposableEmail()]);
        $this->assertFalse($result->isValid());
        $this->assertSame(['Value must not be a disposable email address.'], $result->getErrorMessages());

        $result = $this->validator->validate('john@trash-mail.test', [new NotDisposableEmail()]);
        $this->assertFalse($result->isValid());
    }

    public function testNotDisposableRejectsSubdomainsAndHonoursAllowlist(): void
    {
        $this->assertFalse(
            $this->validator->validate('john@eu.trash-mail.test', [new NotDisposableEmail()])->isValid()
        );
        $this->assertTrue(
            $this->validator->validate('john@ok.trash-mail.test', [new NotDisposableEmail()])->isValid()
        );
    }

    public function testNotDisposableIgnoresValuesWithoutAtSign(): void
    {
        $this->assertTrue($this->validator->validate('mailinator.com', [new NotDisposableEmail()])->isValid());
    }

    public function testNotDisposableCustomMessagePlaceholders(): void
    {
        $rule = new NotDisposableEmail(message: '{Property}: {domain} matched {matched} ({value}).');
        $result = $this->validator->validate(['email' => 'a@x.trash-mail.test'], ['email' => [$rule]]);

        $this->assertSame(
            ['email' => ['Email: x.trash-mail.test matched trash-mail.test (a@x.trash-mail.test).']],
            $result->getErrorMessagesIndexedByProperty()
        );
    }

    public function testNotDisposableRejectsNonString(): void
    {
        $result = $this->validator->validate(['email' => 123], ['email' => [new NotDisposableEmail()]]);

        $this->assertSame(['email' => ['Email must be a string. int given.']], $result->getErrorMessagesIndexedByProperty());
    }

    public function testSkipOnEmpty(): void
    {
        $data = ['email' => ''];

        $this->assertFalse(
            $this->validator->validate($data, ['email' => [new VerifiedEmail()]])->isValid()
        );
        $this->assertTrue(
            $this->validator->validate($data, ['email' => [new VerifiedEmail(skipOnEmpty: true)]])->isValid()
        );
        $this->assertTrue(
            $this->validator->validate($data, ['email' => [new NotDisposableEmail(skipOnEmpty: true)]])->isValid()
        );
    }

    public function testSkipOnErrorAndWhen(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => [new Required(), new VerifiedEmail(skipOnError: true)]];
        $this->assertCount(1, $this->validator->validate($data, $rules)->getErrors());

        $rule = new NotDisposableEmail(when: static fn (mixed $value, ValidationContext $context): bool => false);
        $this->assertTrue($this->validator->validate(['email' => 'a@mailinator.com'], ['email' => [$rule]])->isValid());
    }

    public function testVerifiedAcceptsValidEmail(): void
    {
        $this->assertTrue($this->validator->validate('john.doe@example.com', [new VerifiedEmail()])->isValid());
    }

    public function testVerifiedUsesMessageOfFirstFailedCheck(): void
    {
        $result = $this->validator->validate(['email' => 'not-an-email'], ['email' => [new VerifiedEmail()]]);
        $this->assertSame(['email' => ['Email is not a valid email address.']], $result->getErrorMessagesIndexedByProperty());

        $result = $this->validator->validate(['email' => 'john@mailinator.com'], ['email' => [new VerifiedEmail()]]);
        $this->assertSame(
            ['email' => ['Email must not be a disposable email address.']],
            $result->getErrorMessagesIndexedByProperty()
        );

        $result = $this->validator->validate(['email' => 'admin@example.com'], ['email' => [new VerifiedEmail()]]);
        $this->assertSame(
            ['email' => ['Email must not be a role-based email address.']],
            $result->getErrorMessagesIndexedByProperty()
        );
    }

    public function testVerifiedCustomMessages(): void
    {
        $rule = new VerifiedEmail(
            message: 'Generic: {error}',
            messages: ['disposable' => 'No throwaway addresses ({check}: {error}).'],
        );

        $result = $this->validator->validate('john@mailinator.com', [$rule]);
        $this->assertSame(
            ['No throwaway addresses (disposable: Disposable email address).'],
            $result->getErrorMessages()
        );
        $this->assertSame(
            'No throwaway addresses ({check}: {error}).',
            $rule->getMessageFor('disposable')
        );
        $this->assertSame('Generic: {error}', $rule->getMessageFor('unknown_check'));
    }

    public function testVerifiedSuggestionPlaceholder(): void
    {
        $rule = new VerifiedEmail(messages: ['role_based' => 'Role address. Did you mean "{suggestion}"?']);

        $result = $this->validator->validate('info@yahooo.com', [$rule]);

        $this->assertSame(['Role address. Did you mean "info@yahoo.com"?'], $result->getErrorMessages());
    }

    public function testVerifiedRejectsNonString(): void
    {
        $result = $this->validator->validate(['email' => ['a@b.c']], ['email' => [new VerifiedEmail()]]);

        $this->assertSame(['email' => ['Email must be a string. array given.']], $result->getErrorMessagesIndexedByProperty());
    }

    public function testAttributesOnObject(): void
    {
        $form = new class () {
            #[NotDisposableEmail]
            #[VerifiedEmail]
            public string $email = 'john@mailinator.com';
        };

        $result = $this->validator->validate($form);

        $this->assertFalse($result->isValid());
        $this->assertSame(
            [
                'Email must not be a disposable email address.',
                'Email must not be a disposable email address.',
            ],
            $result->getPropertyErrorMessages('email')
        );
    }

    public function testRuleOptionsAndNames(): void
    {
        $rule = new VerifiedEmail(messages: ['mx' => 'No MX.'], skipOnEmpty: true);
        $options = $rule->getOptions();

        $this->assertSame(VerifiedEmail::class, $rule->getName());
        $this->assertSame(VerifiedEmailHandler::class, $rule->getHandler());
        $this->assertSame(['template' => 'No MX.', 'parameters' => []], $options['messages']['mx']);
        $this->assertTrue($options['skipOnEmpty']);
        $this->assertFalse($options['skipOnError']);

        $rule = new NotDisposableEmail();
        $this->assertSame(NotDisposableEmail::class, $rule->getName());
        $this->assertSame(NotDisposableEmailHandler::class, $rule->getHandler());
        $this->assertSame(
            ['template' => '{Property} must not be a disposable email address.', 'parameters' => []],
            $rule->getOptions()['message']
        );
    }

    public function testHandlersRejectForeignRules(): void
    {
        $context = new ValidationContext();

        try {
            (new NotDisposableEmailHandler($this->emailValidator))->validate('a@b.c', new VerifiedEmail(), $context);
            $this->fail('Expected UnexpectedRuleException.');
        } catch (UnexpectedRuleException $e) {
            $this->assertStringContainsString(NotDisposableEmail::class, $e->getMessage());
        }

        $this->expectException(UnexpectedRuleException::class);
        (new VerifiedEmailHandler($this->emailValidator))->validate('a@b.c', new NotDisposableEmail(), $context);
    }

    public function testDefaultHandlerCreatesValidatorLazily(): void
    {
        // The default container instantiates handlers without arguments.
        $result = (new Validator())->validate('john@mailinator.com', [new NotDisposableEmail()]);

        $this->assertFalse($result->isValid());
    }
}
