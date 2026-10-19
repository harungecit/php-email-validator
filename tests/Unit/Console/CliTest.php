<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit\Console;

use HarunGecit\EmailValidator\Console\Cli;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    /**
     * @param array<string> $args
     */
    private function cli(array $args): int
    {
        return (new Cli($this->stdout, $this->stderr))->run(array_merge(['email-validator'], $args));
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);
        return (string) stream_get_contents($stream);
    }

    public function testCheckFlagsDisposableAddresses(): void
    {
        $this->assertSame(Cli::EXIT_FLAGGED, $this->cli(['check', 'user@eu.mailinator.com', 'gmail.com']));

        $output = $this->read($this->stdout);
        $this->assertStringContainsString('DISPOSABLE user@eu.mailinator.com (matched mailinator.com in blocklist)', $output);
        $this->assertStringContainsString('OK         gmail.com', $output);
    }

    public function testCheckReturnsZeroWhenNothingIsDisposable(): void
    {
        $this->assertSame(Cli::EXIT_OK, $this->cli(['check', 'user@gmail.com', '163.com']));
        $this->assertStringContainsString('ALLOWED    163.com', $this->read($this->stdout));
    }

    public function testCheckJson(): void
    {
        $this->cli(['check', 'mailinator.com', '--json']);
        $decoded = json_decode($this->read($this->stdout), true);

        $this->assertSame('mailinator.com', $decoded[0]['matched_domain']);
        $this->assertTrue($decoded[0]['disposable']);
    }

    public function testValidateWithoutMx(): void
    {
        $this->assertSame(Cli::EXIT_FLAGGED, $this->cli(['validate', 'user@mailinator.com', 'not-an-email', '--no-mx']));

        $output = $this->read($this->stdout);
        $this->assertStringContainsString('INVALID user@mailinator.com — Disposable email address', $output);
        $this->assertStringContainsString('INVALID not-an-email — Invalid email format', $output);
    }

    public function testStatsJson(): void
    {
        $this->assertSame(Cli::EXIT_OK, $this->cli(['stats', '--json']));
        $stats = json_decode($this->read($this->stdout), true);

        $this->assertGreaterThan(100000, $stats['blocklist']);
        $this->assertTrue($stats['block_subdomains']);
    }

    public function testSyncRequiresAPath(): void
    {
        $this->assertSame(Cli::EXIT_USAGE, $this->cli(['sync']));
        $this->assertStringContainsString('--path=FILE', $this->read($this->stderr));
    }

    public function testUsageAndUnknownCommands(): void
    {
        $this->assertSame(Cli::EXIT_USAGE, $this->cli([]));
        $this->assertSame(Cli::EXIT_OK, $this->cli(['help']));
        $this->assertSame(Cli::EXIT_USAGE, $this->cli(['frobnicate']));
        $this->assertSame(Cli::EXIT_USAGE, $this->cli(['check']));
        $this->assertStringContainsString('Unknown command "frobnicate"', $this->read($this->stderr));
        $this->assertStringContainsString('Commands:', $this->read($this->stdout));
    }

    public function testVersion(): void
    {
        $this->assertSame(Cli::EXIT_OK, $this->cli(['--version']));
        $this->assertStringStartsWith('php-email-validator ', $this->read($this->stdout));
    }

    public function testMissingConfigFileIsAUsageError(): void
    {
        $this->assertSame(Cli::EXIT_USAGE, $this->cli(['stats', '--config=/does/not/exist.php']));
        $this->assertStringContainsString('Error:', $this->read($this->stderr));
    }
}
