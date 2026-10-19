<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel;

use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotDisposableEmail;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\NotRoleBasedEmail;
use HarunGecit\EmailValidator\Framework\Laravel\Rules\ValidEmail;
use Illuminate\Translation\ArrayLoader;

class ValidationRulesTest extends LaravelTestCase
{
    // ==================== Rule objects ====================

    public function testNotDisposableEmailRule(): void
    {
        $this->bootApplication();

        $errors = $this->validate(['email' => 'user@mailinator.com'], ['email' => [new NotDisposableEmail()]]);
        $this->assertSame(['email' => ['The email must not be a disposable email address.']], $errors);

        // Subdomains of disposable providers are caught too.
        $errors = $this->validate(['email' => 'user@eu.mailinator.com'], ['email' => [new NotDisposableEmail()]]);
        $this->assertArrayHasKey('email', $errors);

        $this->assertSame([], $this->validate(['email' => 'user@gmail.com'], ['email' => [new NotDisposableEmail()]]));
    }

    public function testNotDisposableEmailIgnoresNonEmails(): void
    {
        $this->bootApplication();

        $this->assertSame([], $this->validate(['email' => 'not-an-email'], ['email' => [new NotDisposableEmail()]]));
        $this->assertSame([], $this->validate(['email' => ['x']], ['email' => [new NotDisposableEmail()]]));
    }

    public function testCustomMessage(): void
    {
        $this->bootApplication();

        $errors = $this->validate(
            ['contact' => 'user@mailinator.com'],
            ['contact' => [new NotDisposableEmail('No throwaway addresses for :attribute (:matched).')]]
        );
        $this->assertSame(['contact' => ['No throwaway addresses for contact (mailinator.com).']], $errors);

        $errors = $this->validate(
            ['contact' => 'user@mailinator.com'],
            ['contact' => [(new NotDisposableEmail())->withMessage('Nope.')]]
        );
        $this->assertSame(['contact' => ['Nope.']], $errors);
    }

    public function testTranslatedMessagesAreUsed(): void
    {
        $app = $this->bootApplication();
        $app->make(ArrayLoader::class)->addMessages('en', 'validation', [
            'not_disposable' => 'Wegwerfadressen sind für :attribute nicht erlaubt.',
        ], 'emailvalidator');

        $errors = $this->validate(['email' => 'user@mailinator.com'], ['email' => [new NotDisposableEmail()]]);
        $this->assertSame(['email' => ['Wegwerfadressen sind für email nicht erlaubt.']], $errors);
    }

    public function testCustomListsFromConfigAreHonoured(): void
    {
        $this->bootApplication([
            'lists' => [
                'custom_blocklist' => ['blocked-domain.test'],
                'custom_allowlist' => ['mailinator.com'],
            ],
        ]);

        $rules = ['email' => [new NotDisposableEmail()]];
        $this->assertArrayHasKey('email', $this->validate(['email' => 'a@blocked-domain.test'], $rules));
        $this->assertSame([], $this->validate(['email' => 'a@mailinator.com'], $rules));
    }

    public function testValidEmailRuleReportsFirstFailingCheck(): void
    {
        $this->bootApplication();

        $rules = ['email' => [new ValidEmail()]];

        $this->assertSame([], $this->validate(['email' => 'john.doe@gmail.com'], $rules));
        $this->assertSame(
            ['email' => ['The email must be a valid email address.']],
            $this->validate(['email' => 'not-an-email'], $rules)
        );
        $this->assertSame(
            ['email' => ['The email must not be a disposable email address.']],
            $this->validate(['email' => 'user@mailinator.com'], $rules)
        );
    }

    public function testValidEmailRuleWithRoleBasedCheck(): void
    {
        $this->bootApplication([
            'checks' => ['format' => true, 'mx' => false, 'disposable' => true, 'role_based' => true],
        ]);

        $this->assertSame(
            ['email' => ['The email must not be a role-based email address.']],
            $this->validate(['email' => 'admin@gmail.com'], ['email' => [new ValidEmail()]])
        );
    }

    public function testValidEmailRuleCanOverrideMxCheck(): void
    {
        $app = $this->bootApplication([
            'checks' => ['format' => true, 'mx' => true, 'disposable' => true],
        ]);

        // checkMx: false skips the DNS lookup for this rule only.
        $this->assertSame([], $this->validate(['email' => 'john.doe@gmail.com'], ['email' => [new ValidEmail(checkMx: false)]]));
        $this->assertTrue($app->make(\HarunGecit\EmailValidator\Config\Configuration::class)->isMxCheckEnabled());
    }

    public function testValidEmailRuleReasonReplacement(): void
    {
        $this->bootApplication();

        $errors = $this->validate(
            ['email' => 'user@mailinator.com'],
            ['email' => [new ValidEmail(message: ':attribute rejected: :reason')]]
        );
        $this->assertSame(['email' => ['email rejected: Disposable email address']], $errors);
    }

    public function testNotRoleBasedEmailRule(): void
    {
        $this->bootApplication();

        $rules = ['email' => [new NotRoleBasedEmail()]];
        $this->assertSame(
            ['email' => ['The email must not be a role-based email address.']],
            $this->validate(['email' => 'info@example.com'], $rules)
        );
        $this->assertSame([], $this->validate(['email' => 'jane@example.com'], $rules));
    }

    // ==================== String rules ====================

    public function testNotDisposableStringRule(): void
    {
        $this->bootApplication();

        $this->assertSame(
            ['email' => ['The email must not be a disposable email address.']],
            $this->validate(['email' => 'user@mailinator.com'], ['email' => 'not_disposable'])
        );
        $this->assertSame([], $this->validate(['email' => 'user@gmail.com'], ['email' => 'not_disposable']));
    }

    public function testValidEmailStringRule(): void
    {
        $this->bootApplication();

        $this->assertSame([], $this->validate(['email' => 'john.doe@gmail.com'], ['email' => 'valid_email']));
        $this->assertSame(
            ['email' => ['The email must be a valid email address.']],
            $this->validate(['email' => 'user@mailinator.com'], ['email' => 'valid_email'])
        );
    }

    public function testOtherStringRules(): void
    {
        $this->bootApplication();

        $this->assertArrayHasKey('email', $this->validate(['email' => 'admin@example.com'], ['email' => 'not_role_based']));
        $this->assertSame([], $this->validate(['email' => 'jane@example.com'], ['email' => 'not_role_based']));

        $this->assertSame(
            ['email' => ['The email must not contain a plus sign (+).']],
            $this->validate(['email' => 'jane+news@example.com'], ['email' => 'not_subaddressed'])
        );
        $this->assertSame([], $this->validate(['email' => 'jane@example.com'], ['email' => 'not_subaddressed']));

        // No "@" means no domain: fails without a DNS lookup.
        $this->assertSame(
            ['email' => ['The email domain must have valid MX records.']],
            $this->validate(['email' => 'no-domain'], ['email' => 'has_mx'])
        );
    }

    public function testNotSpoofedStringRule(): void
    {
        if (!class_exists(\Spoofchecker::class)) {
            $this->markTestSkipped('ext-intl is not available.');
        }

        $this->bootApplication();

        // Cyrillic "а" inside a Latin local part.
        $this->assertSame(
            ['email' => ['The email must not contain mixed-script or invisible characters.']],
            $this->validate(['email' => "p\u{0430}ypal@example.com"], ['email' => 'not_spoofed'])
        );
        $this->assertSame([], $this->validate(['email' => 'paypal@example.com'], ['email' => 'not_spoofed']));
    }

    public function testStringRuleMessageCanBeOverriddenByTheApplication(): void
    {
        $app = $this->bootApplication();
        $app->make(ArrayLoader::class)->addMessages('en', 'validation', [
            'not_disposable' => 'Custom :attribute message.',
        ]);

        $this->assertSame(
            ['email' => ['Custom email message.']],
            $this->validate(['email' => 'user@mailinator.com'], ['email' => 'not_disposable'])
        );
    }
}
