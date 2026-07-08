<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Validators;

use HarunGecit\EmailValidator\Validators\RoleBasedValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RoleBasedValidatorTest extends TestCase
{
    private RoleBasedValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RoleBasedValidator();
    }

    public static function roleBasedEmailsProvider(): array
    {
        return [
            ['admin@example.com', true],
            ['info@example.com', true],
            ['support@example.com', true],
            ['sales@example.com', true],
            ['contact@example.com', true],
            ['noreply@example.com', true],
            ['no-reply@example.com', true],
            ['help@example.com', true],
            ['webmaster@example.com', true],
            ['postmaster@example.com', true],
            ['hostmaster@example.com', true],
            ['abuse@example.com', true],
            ['billing@example.com', true],
            ['marketing@example.com', true],
            ['hr@example.com', true],
            ['jobs@example.com', true],
            ['careers@example.com', true],
            ['john@example.com', false],
            ['jane.doe@example.com', false],
            ['user123@example.com', false],
            ['personal@example.com', false],
        ];
    }

    #[DataProvider('roleBasedEmailsProvider')]
    public function testIsRoleBased(string $email, bool $expected): void
    {
        $this->assertEquals($expected, $this->validator->isRoleBased($email));
    }

    public function testAddPrefix(): void
    {
        $this->assertFalse($this->validator->isRoleBased('custom@example.com'));

        $this->validator->addPrefix('custom');

        $this->assertTrue($this->validator->isRoleBased('custom@example.com'));
    }

    public function testRemovePrefix(): void
    {
        $this->assertTrue($this->validator->isRoleBased('admin@example.com'));

        $this->validator->removePrefix('admin');

        $this->assertFalse($this->validator->isRoleBased('admin@example.com'));
    }

    public function testSetPrefixes(): void
    {
        $this->validator->setPrefixes(['only', 'these']);

        $this->assertTrue($this->validator->isRoleBased('only@example.com'));
        $this->assertTrue($this->validator->isRoleBased('these@example.com'));
        $this->assertFalse($this->validator->isRoleBased('admin@example.com'));
    }

    public function testGetPrefixes(): void
    {
        $prefixes = $this->validator->getPrefixes();

        $this->assertIsArray($prefixes);
        $this->assertContains('admin', $prefixes);
        $this->assertContains('info', $prefixes);
    }

    public function testCaseInsensitivity(): void
    {
        $this->assertTrue($this->validator->isRoleBased('ADMIN@example.com'));
        $this->assertTrue($this->validator->isRoleBased('Admin@example.com'));
        $this->assertTrue($this->validator->isRoleBased('INFO@EXAMPLE.COM'));
    }

    public function testInvalidEmailFormat(): void
    {
        $this->assertFalse($this->validator->isRoleBased('invalid-email'));
        $this->assertFalse($this->validator->isRoleBased(''));
        $this->assertFalse($this->validator->isRoleBased('@example.com'));
    }

    public function testPrefixWithSeparators(): void
    {
        $this->assertTrue($this->validator->isRoleBased('admin.user@example.com'));
        $this->assertTrue($this->validator->isRoleBased('admin-user@example.com'));
        $this->assertTrue($this->validator->isRoleBased('admin_user@example.com'));
    }

    public function testGetDetectedPrefix(): void
    {
        $this->assertEquals('admin', $this->validator->getDetectedPrefix('admin@example.com'));
        $this->assertEquals('info', $this->validator->getDetectedPrefix('info@example.com'));
        $this->assertNull($this->validator->getDetectedPrefix('john@example.com'));
    }

    public function testFluentInterface(): void
    {
        $this->assertSame($this->validator, $this->validator->setPrefixes(['test']));
        $this->assertSame($this->validator, $this->validator->addPrefix('custom'));
        $this->assertSame($this->validator, $this->validator->removePrefix('test'));
    }

    public function testSubaddressHandling(): void
    {
        $this->assertTrue($this->validator->isRoleBased('admin+tag@example.com'));
    }
}
