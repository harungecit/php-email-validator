<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Result;

use HarunGecit\EmailValidator\Result\DisposableCheckResult;
use PHPUnit\Framework\TestCase;

class DisposableCheckResultTest extends TestCase
{
    public function testSubdomainMatch(): void
    {
        $result = new DisposableCheckResult('user@eu.mailinator.com', 'eu.mailinator.com', true, 'mailinator.com', 'blocklist');

        $this->assertTrue($result->isDisposable());
        $this->assertTrue($result->isSubdomainMatch());
        $this->assertFalse($result->isAllowlisted());
        $this->assertSame('eu.mailinator.com', $result->getDomain());
        $this->assertSame('mailinator.com', $result->getMatchedDomain());
        $this->assertSame('blocklist', $result->getSource());
        $this->assertSame('user@eu.mailinator.com', $result->getInput());
    }

    public function testAllowlisted(): void
    {
        $result = new DisposableCheckResult('user@163.com', '163.com', false, '163.com', DisposableCheckResult::SOURCE_ALLOWLIST);

        $this->assertFalse($result->isDisposable());
        $this->assertTrue($result->isAllowlisted());
        $this->assertFalse($result->isSubdomainMatch());
    }

    public function testNoMatch(): void
    {
        $result = new DisposableCheckResult('user@example.com', 'example.com', false);

        $this->assertNull($result->getMatchedDomain());
        $this->assertNull($result->getSource());
        $this->assertFalse($result->isSubdomainMatch());
    }

    public function testSerialization(): void
    {
        $result = new DisposableCheckResult('x@a-temp.com', 'a-temp.com', true, 'a-temp.com', 'custom');
        $expected = [
            'input' => 'x@a-temp.com',
            'domain' => 'a-temp.com',
            'disposable' => true,
            'matched_domain' => 'a-temp.com',
            'source' => 'custom',
        ];

        $this->assertSame($expected, $result->toArray());
        $this->assertSame(json_encode($expected), json_encode($result));
    }
}
