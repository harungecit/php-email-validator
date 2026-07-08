<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Result;

use HarunGecit\EmailValidator\Result\ValidationResult;
use HarunGecit\EmailValidator\Result\SuggestionResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $result = new ValidationResult('test@example.com');

        $this->assertEquals('test@example.com', $result->getEmail());
        $this->assertTrue($result->isValid());
    }

    public function testAddCheckPassing(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', true);
        $result->addCheck('mx', true);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->passed('format'));
        $this->assertTrue($result->passed('mx'));
    }

    public function testAddCheckFailing(): void
    {
        $result = new ValidationResult('invalid');
        $result->addCheck('format', false, 'Invalid email format');

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->passed('format'));
        $this->assertContains('Invalid email format', $result->getErrors());
    }

    public function testPassedReturnsNullForUnchecked(): void
    {
        $result = new ValidationResult('test@example.com');

        $this->assertNull($result->passed('mx'));
    }

    public function testGetChecks(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', true);
        $result->addCheck('mx', true);
        $result->addCheck('disposable', false, 'Disposable email');

        $checks = $result->getChecks();
        $this->assertTrue($checks['format']);
        $this->assertTrue($checks['mx']);
        $this->assertFalse($checks['disposable']);
    }

    public function testWarnings(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', true);
        $result->addWarning('Email uses plus addressing');
        $result->addWarning('Domain may be a catch-all');

        $warnings = $result->getWarnings();
        $this->assertCount(2, $warnings);
        $this->assertContains('Email uses plus addressing', $warnings);
        $this->assertTrue($result->isValid());
    }

    public function testErrors(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', false, 'Invalid format');
        $result->addCheck('mx', false, 'No MX records');

        $errors = $result->getErrors();
        $this->assertCount(2, $errors);
        $this->assertEquals('Invalid format', $result->getFirstError());
    }

    public function testSuggestion(): void
    {
        $result = new ValidationResult('test@gmial.com');
        $suggestion = new SuggestionResult(
            'test@gmial.com',
            'test@gmail.com',
            'gmial.com',
            'gmail.com'
        );
        $result->setSuggestion($suggestion);

        $this->assertTrue($result->hasSuggestion());
        $this->assertSame($suggestion, $result->getSuggestion());
    }

    public function testNoSuggestion(): void
    {
        $result = new ValidationResult('test@gmail.com');

        $this->assertFalse($result->hasSuggestion());
        $this->assertNull($result->getSuggestion());
    }

    public function testMetadata(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addMetadata('mx_records', ['mx1.example.com', 'mx2.example.com']);
        $result->addMetadata('validation_time', 0.5);

        $this->assertEquals(['mx1.example.com', 'mx2.example.com'], $result->getMetadataValue('mx_records'));
        $this->assertEquals(0.5, $result->getMetadataValue('validation_time'));
        $this->assertNull($result->getMetadataValue('nonexistent'));
        $this->assertEquals('default', $result->getMetadataValue('nonexistent', 'default'));
    }

    public function testGetAllMetadata(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addMetadata('key1', 'value1');
        $result->addMetadata('key2', 'value2');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('key1', $metadata);
        $this->assertArrayHasKey('key2', $metadata);
    }

    public function testDomainExtraction(): void
    {
        $result = new ValidationResult('test@example.com');

        $this->assertEquals('example.com', $result->getDomain());
    }

    public function testLocalPartExtraction(): void
    {
        $result = new ValidationResult('john.doe@example.com');

        $this->assertEquals('john.doe', $result->getLocalPart());
    }

    public function testIsDisposable(): void
    {
        $result = new ValidationResult('test@mailinator.com');
        $result->addCheck('disposable', false, 'Disposable email');

        $this->assertTrue($result->isDisposable());

        $result2 = new ValidationResult('test@gmail.com');
        $result2->addCheck('disposable', true);

        $this->assertFalse($result2->isDisposable());

        $result3 = new ValidationResult('test@example.com');
        $this->assertNull($result3->isDisposable());
    }

    public function testIsRoleBased(): void
    {
        $result = new ValidationResult('admin@example.com');
        $result->addCheck('role_based', false, 'Role-based email');

        $this->assertTrue($result->isRoleBased());

        $result2 = new ValidationResult('john@example.com');
        $result2->addCheck('role_based', true);

        $this->assertFalse($result2->isRoleBased());
    }

    public function testIsSubaddressed(): void
    {
        $result = new ValidationResult('user+tag@example.com');
        $result->addMetadata('subaddressed', true);

        $this->assertTrue($result->isSubaddressed());

        $result2 = new ValidationResult('user@example.com');
        $result2->addMetadata('subaddressed', false);

        $this->assertFalse($result2->isSubaddressed());
    }

    public function testToArray(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', true);
        $result->addCheck('mx', true);
        $result->addWarning('Some warning');
        $result->addMetadata('key', 'value');

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertTrue($array['valid']);
        $this->assertArrayHasKey('checks', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertArrayHasKey('domain', $array);
        $this->assertArrayHasKey('local_part', $array);
        $this->assertArrayHasKey('suggestion', $array);
        $this->assertArrayHasKey('metadata', $array);
    }

    public function testToJson(): void
    {
        $result = new ValidationResult('test@example.com');
        $result->addCheck('format', true);

        $json = $result->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('test@example.com', $decoded['email']);
        $this->assertTrue($decoded['valid']);
    }

    public function testCreateValid(): void
    {
        $result = ValidationResult::valid('test@example.com');

        $this->assertTrue($result->isValid());
        $this->assertEquals('test@example.com', $result->getEmail());
        $this->assertTrue($result->passed('format'));
    }

    public function testCreateInvalidFormat(): void
    {
        $result = ValidationResult::invalidFormat('bad-email');

        $this->assertFalse($result->isValid());
        $this->assertEquals('bad-email', $result->getEmail());
        $this->assertFalse($result->passed('format'));
        $this->assertContains('Invalid email format', $result->getErrors());
    }

    public function testFluentInterface(): void
    {
        $result = new ValidationResult('test@example.com');

        $this->assertSame($result, $result->addCheck('format', true));
        $this->assertSame($result, $result->addWarning('warning'));
        $this->assertSame($result, $result->addMetadata('key', 'value'));
    }
}
