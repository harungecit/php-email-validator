<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\CakePHP;

use Cake\Core\Configure;
use Cake\Validation\Validator;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Framework\CakePHP\EmailValidationProvider;
use HarunGecit\EmailValidator\Framework\CakePHP\EmailValidatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * CakePHP validation provider, exercised with the real Cake\Validation\Validator.
 */
class EmailValidationProviderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Validator::class)) {
            $this->markTestSkipped('cakephp/validation is not installed.');
        }
    }

    protected function tearDown(): void
    {
        EmailValidatorFactory::setInstance(null);

        if (class_exists(Configure::class)) {
            Configure::delete(EmailValidatorFactory::CONFIGURE_KEY);
        }
    }

    private function provider(): EmailValidationProvider
    {
        $config = ConfigurationBuilder::create()->basic()->withRoleBasedCheck()->build();

        return new EmailValidationProvider(new EmailValidator($config));
    }

    public function testDisposableEmailFailsWithMessage(): void
    {
        $validator = new Validator();
        $validator
            ->setProvider('emailValidator', $this->provider())
            ->add('email', 'notDisposable', [
                'rule' => 'notDisposable',
                'provider' => 'emailValidator',
                'message' => 'No throwaway addresses.',
            ]);

        $errors = $validator->validate(['email' => 'user@mailinator.com']);

        $this->assertSame(['email' => ['notDisposable' => 'No throwaway addresses.']], $errors);
    }

    public function testNormalEmailPasses(): void
    {
        $validator = new Validator();
        EmailValidationProvider::attach(
            $validator,
            'email',
            ['notDisposable', 'notRoleBased', 'notSubaddressed', 'notSpoofed', 'verifiedEmail'],
            [],
            $this->provider()
        );

        $this->assertSame([], $validator->validate(['email' => 'john.doe@example.com']));
    }

    public function testAttachAddsRulesWithDefaultAndCustomMessages(): void
    {
        $validator = new Validator();
        $result = EmailValidationProvider::attach($validator, 'contact', [
            'notDisposable',
            'notRoleBased' => 'Please use a personal address.',
        ], [], $this->provider());

        $this->assertSame($validator, $result);
        $this->assertInstanceOf(EmailValidationProvider::class, $validator->getProvider(EmailValidationProvider::NAME));

        $errors = $validator->validate(['contact' => 'info@mailinator.com']);

        $this->assertSame([
            'contact' => [
                'notDisposable' => EmailValidationProvider::MESSAGES['notDisposable'],
                'notRoleBased' => 'Please use a personal address.',
            ],
        ], $errors);
    }

    public function testAttachPassesRuleOptions(): void
    {
        $validator = new Validator();
        EmailValidationProvider::attach($validator, 'email', ['notSubaddressed'], ['on' => 'create'], $this->provider());

        $this->assertNotEmpty($validator->validate(['email' => 'john+tag@example.com'], true));
        $this->assertSame([], $validator->validate(['email' => 'john+tag@example.com'], false));
    }

    public function testAttachKeepsAnExistingProvider(): void
    {
        $provider = $this->provider();
        $validator = new Validator();
        $validator->setProvider(EmailValidationProvider::NAME, $provider);

        EmailValidationProvider::attach($validator, 'email', ['notDisposable']);

        $this->assertSame($provider, $validator->getProvider(EmailValidationProvider::NAME));
    }

    public function testAttachRejectsUnknownRules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailValidationProvider::attach(new Validator(), 'email', ['notAThing']);
    }

    public function testVerifiedEmailRunsTheConfiguredPipeline(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->verifiedEmail('john@example.com'));
        $this->assertFalse($provider->verifiedEmail('john@mailinator.com'));
        $this->assertFalse($provider->verifiedEmail('admin@example.com'));
        $this->assertFalse($provider->verifiedEmail('not-an-email'));
        $this->assertFalse($provider->verifiedEmail(null));
    }

    public function testHasMxFailsWithoutDomain(): void
    {
        $this->assertFalse($this->provider()->hasMx('not-an-email'));
        $this->assertFalse($this->provider()->hasMx(['x']));
    }

    public function testDefaultProviderUsesConfigureSettings(): void
    {
        if (!class_exists(Configure::class)) {
            $this->markTestSkipped('cakephp/core is not installed.');
        }

        Configure::write(EmailValidatorFactory::CONFIGURE_KEY, [
            'checks' => ['mx' => false, 'role_based' => true],
            'lists' => ['custom_blocklist' => ['blocked.test']],
        ]);

        $provider = new EmailValidationProvider();

        $this->assertSame(EmailValidatorFactory::getInstance(), $provider->getValidator());
        $this->assertFalse($provider->getValidator()->getConfiguration()->isMxCheckEnabled());
        $this->assertFalse($provider->notDisposable('user@eu.blocked.test'));
        $this->assertTrue($provider->verifiedEmail('john@example.com'));
        $this->assertFalse($provider->verifiedEmail('support@example.com'));
    }

    public function testFactoryBuildsConfigurationFromExplicitSettings(): void
    {
        $config = EmailValidatorFactory::configuration(['checks' => ['mx' => false, 'spoof' => true]]);

        $this->assertFalse($config->isMxCheckEnabled());
        $this->assertTrue($config->isSpoofCheckEnabled());
        $this->assertNotSame(EmailValidatorFactory::create([]), EmailValidatorFactory::create([]));
    }
}
