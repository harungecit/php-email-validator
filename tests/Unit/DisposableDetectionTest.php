<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Unit;

use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Config\ConfigurationBuilder;
use HarunGecit\EmailValidator\EmailValidator;
use HarunGecit\EmailValidator\Exceptions\ConfigurationException;
use HarunGecit\EmailValidator\Result\DisposableCheckResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Disposable detection: list precedence, sources, configuration and the
 * bundled data set.
 */
class DisposableDetectionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ev-detect-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function basic(): ConfigurationBuilder
    {
        return ConfigurationBuilder::create()->basic();
    }

    public function testCheckDisposableExplainsSubdomainMatches(): void
    {
        $validator = EmailValidator::withConfig($this->basic()->build());
        $check = $validator->checkDisposable('User@EU.Mailinator.com');

        $this->assertTrue($check->isDisposable());
        $this->assertSame('eu.mailinator.com', $check->getDomain());
        $this->assertSame('mailinator.com', $check->getMatchedDomain());
        $this->assertSame(DisposableCheckResult::SOURCE_BLOCKLIST, $check->getSource());
        $this->assertTrue($check->isSubdomainMatch());
    }

    public function testCheckDisposableAcceptsBareDomains(): void
    {
        $validator = EmailValidator::withConfig($this->basic()->build());

        $this->assertTrue($validator->checkDisposable('yopmail.com')->isDisposable());
        $this->assertTrue($validator->isDisposableDomain('yopmail.com'));
        $this->assertFalse($validator->isDisposableDomain('gmail.com'));
        // isDisposable() keeps its 2.x contract: input without "@" is never disposable.
        $this->assertFalse($validator->isDisposable('yopmail.com'));
    }

    public function testInvalidInputIsNotDisposable(): void
    {
        $check = EmailValidator::withConfig($this->basic()->build())->checkDisposable('user@');

        $this->assertFalse($check->isDisposable());
        $this->assertNull($check->getDomain());
    }

    public function testSubdomainBlockingCanBeDisabled(): void
    {
        $validator = EmailValidator::withConfig($this->basic()->withoutSubdomainBlocking()->build());

        $this->assertTrue($validator->isDisposable('user@mailinator.com'));
        $this->assertFalse($validator->isDisposable('user@eu.mailinator.com'));
    }

    public function testCustomBlocklistFromConfiguration(): void
    {
        $validator = EmailValidator::withConfig($this->basic()->withCustomBlocklist(['Spam-Corp.example'])->build());
        $check = $validator->checkDisposable('a@mail.spam-corp.example');

        $this->assertTrue($check->isDisposable());
        $this->assertSame('spam-corp.example', $check->getMatchedDomain());
        $this->assertSame(DisposableCheckResult::SOURCE_CUSTOM, $check->getSource());
    }

    public function testCustomBlocklistFilesAndDirectories(): void
    {
        file_put_contents($this->directory . '/one.txt', "# team list\nfirst-temp.example\n");
        file_put_contents($this->directory . '/two.json', '["second-temp.example"]');
        file_put_contents($this->directory . '/ignored.md', 'third-temp.example');

        $validator = EmailValidator::withConfig(
            $this->basic()->withCustomBlocklistFiles([$this->directory, $this->directory . '/missing.txt'])->build()
        );

        $this->assertTrue($validator->isDisposable('a@first-temp.example'));
        $this->assertTrue($validator->isDisposable('a@second-temp.example'));
        $this->assertFalse($validator->isDisposable('a@third-temp.example'));
    }

    public function testSyncedListIsLoadedWithItsOwnSource(): void
    {
        $path = $this->directory . '/synced.txt';
        file_put_contents($path, "synced-temp.example\nmailinator.com\n");

        $validator = EmailValidator::withConfig($this->basic()->withSyncedBlocklist($path)->build());

        $this->assertSame(DisposableCheckResult::SOURCE_SYNCED, $validator->checkDisposable('x@synced-temp.example')->getSource());
        // Domains already in the bundled list keep their bundled source.
        $this->assertSame(DisposableCheckResult::SOURCE_BLOCKLIST, $validator->checkDisposable('x@mailinator.com')->getSource());

        $stats = $validator->getListStatistics();
        $this->assertSame(1, $stats['synced']);
        $this->assertSame($path, $stats['synced_path']);
        $this->assertNotNull($stats['last_synced']);
    }

    public function testMissingSyncedListIsIgnored(): void
    {
        $validator = EmailValidator::withConfig(
            $this->basic()->withSyncedBlocklist($this->directory . '/not-synced-yet.txt')->build()
        );

        $this->assertNull($validator->getListStatistics()['last_synced']);
        $this->assertTrue($validator->isDisposable('a@mailinator.com'));
    }

    public function testApplicationListsTakePrecedenceOverBundledLists(): void
    {
        // 163.com is on the bundled allowlist; the application explicitly blocks it.
        $validator = EmailValidator::withConfig($this->basic()->withCustomBlocklist(['163.com'])->build());
        $this->assertTrue($validator->isDisposable('user@163.com'));

        // mailinator.com is on the bundled blocklist; the application explicitly allows it.
        $validator = EmailValidator::withConfig($this->basic()->withCustomAllowlist(['mailinator.com'])->build());
        $check = $validator->checkDisposable('user@mailinator.com');
        $this->assertFalse($check->isDisposable());
        $this->assertTrue($check->isAllowlisted());

        // The application's allowlist beats the application's blocklist.
        $validator = EmailValidator::withConfig(
            $this->basic()->withCustomBlocklist(['both.example'])->withCustomAllowlist(['both.example'])->build()
        );
        $this->assertFalse($validator->isDisposable('user@both.example'));
    }

    public function testBundledAllowlistBeatsBundledAndSyncedBlocklists(): void
    {
        $path = $this->directory . '/synced.txt';
        file_put_contents($path, "163.com\n");

        $validator = EmailValidator::withConfig($this->basic()->withSyncedBlocklist($path)->build());

        $this->assertFalse($validator->isDisposable('user@163.com'));
    }

    public function testMostSpecificEntryWins(): void
    {
        $validator = EmailValidator::withConfig(
            $this->basic()->withCustomAllowlist(['ok.mailinator.com'])->build()
        );

        $this->assertFalse($validator->isDisposable('user@ok.mailinator.com'));
        $this->assertTrue($validator->isDisposable('user@other.mailinator.com'));
    }

    public function testRuntimeListChangesDoNotTouchTheSharedIndex(): void
    {
        $first = EmailValidator::withConfig($this->basic()->build());
        $second = EmailValidator::withConfig($this->basic()->build());
        $count = $first->getBlocklistCount();

        $first->removeFromBlocklist('mailinator.com')->addToBlocklist('runtime-temp.example');

        $this->assertFalse($first->isDisposable('a@mailinator.com'));
        $this->assertTrue($first->isDisposable('a@runtime-temp.example'));
        $this->assertSame($count, $first->getBlocklistCount());
        $this->assertNotContains('mailinator.com', $first->getBlocklist());
        $this->assertContains('runtime-temp.example', $first->getBlocklist());

        // Another instance sharing the bundled index is unaffected.
        $this->assertTrue($second->isDisposable('a@mailinator.com'));
        $this->assertFalse($second->isDisposable('a@runtime-temp.example'));

        // Re-adding a removed bundled domain restores it.
        $first->addToBlocklist('mailinator.com');
        $this->assertTrue($first->isDisposable('a@mailinator.com'));
        $this->assertSame($count + 1, $first->getBlocklistCount());
    }

    public function testValidateAddsMatchMetadata(): void
    {
        $result = EmailValidator::withConfig($this->basic()->build())->validate('user@eu.mailinator.com');

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isDisposable());
        $this->assertSame('mailinator.com', $result->getMetadataValue('disposable_match'));
        $this->assertSame('blocklist', $result->getMetadataValue('disposable_source'));
    }

    public function testConfigurationFromArray(): void
    {
        $config = Configuration::fromArray([
            'lists' => [
                'custom_blocklist' => ['array-temp.example'],
                'custom_allowlist' => ['Mailinator.com'],
                'custom_blocklist_paths' => $this->directory,
                'synced_blocklist_path' => $this->directory . '/synced.txt',
                'block_subdomains' => false,
            ],
            'sync' => ['urls' => ['https://example.com/list.txt', 'ftp://example.com/x', 'not a url'], 'timeout' => 5],
            'format' => ['mode' => 'filter_unicode'],
            'checks' => ['spoof' => true],
        ]);

        $this->assertSame(['array-temp.example'], $config->getCustomBlocklist());
        $this->assertSame(['mailinator.com'], $config->getCustomAllowlist());
        $this->assertSame([$this->directory], $config->getCustomBlocklistPaths());
        $this->assertSame($this->directory . '/synced.txt', $config->getSyncedBlocklistPath());
        $this->assertFalse($config->isSubdomainBlockingEnabled());
        $this->assertSame(['https://example.com/list.txt'], $config->getSyncUrls());
        $this->assertSame(5, $config->getSyncTimeout());
        $this->assertSame('filter_unicode', $config->getFormatMode());
        $this->assertTrue($config->isSpoofCheckEnabled());

        $roundTrip = Configuration::fromArray($config->toArray());
        $this->assertSame($config->toArray(), $roundTrip->toArray());
    }

    public function testDefaultSyncUrlIsThePackageList(): void
    {
        $this->assertSame([Configuration::DEFAULT_SYNC_URL], (new Configuration())->getSyncUrls());
    }

    public function testInvalidFormatModeThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        (new Configuration())->setFormatMode('loose');
    }

    public function testFormatModes(): void
    {
        $unicode = 'jörg@example.com';

        $filter = EmailValidator::withConfig($this->basic()->build());
        $this->assertFalse($filter->isValidFormat($unicode));

        $filterUnicode = EmailValidator::withConfig($this->basic()->withFormatMode('filter_unicode')->build());
        $this->assertTrue($filterUnicode->isValidFormat($unicode));

        if (!class_exists(\Egulias\EmailValidator\EmailValidator::class)) {
            $this->markTestSkipped('egulias/email-validator is not installed');
        }

        $rfc = EmailValidator::withConfig($this->basic()->withFormatMode('rfc')->build());
        $strict = EmailValidator::withConfig($this->basic()->withFormatMode('strict')->build());

        $this->assertTrue($rfc->isValidFormat('user@example.com'));
        $this->assertFalse($rfc->isValidFormat('user@@example.com'));
        // Valid per RFC 5322 but produces a warning (quoted local part).
        $this->assertTrue($rfc->isValidFormat('"user name"@example.com'));
        $this->assertFalse($strict->isValidFormat('"user name"@example.com'));
    }

    public function testSpoofDetection(): void
    {
        if (!class_exists(\Spoofchecker::class)) {
            $validator = EmailValidator::withConfig($this->basic()->build());
            $this->assertNull($validator->isSpoofed('user@example.com'));
            return;
        }

        $validator = EmailValidator::withConfig($this->basic()->withFormatMode('filter_unicode')->withSpoofCheck()->build());

        $this->assertFalse($validator->isSpoofed('user@gmail.com'));
        // Latin "gm" + Cyrillic "а" + Latin "il".
        $this->assertTrue($validator->isSpoofed("user@gm\u{0430}il.com"));
        // A single non-Latin script per part is legitimate.
        $this->assertFalse($validator->isSpoofed('ivan@почта.рф'));

        // Real-world vectors pass the format check: a punycode homograph domain
        // ("gmаil.com" with a Cyrillic "а") and a mixed-script local part.
        $result = $validator->validate('user@xn--gmil-63d.com');
        $this->assertTrue($result->passed('format'));
        $this->assertFalse($result->passed('spoof'));
        $this->assertContains('Email contains mixed-script or invisible characters', $result->getErrors());

        $this->assertFalse($validator->validate("p\u{0430}ypal@example.com")->passed('spoof'));
        $this->assertTrue($validator->validate('paypal@example.com')->passed('spoof'));
    }

    /**
     * Legitimate domains that upstream aggregators have listed as disposable
     * (spam-reputation feeds, public suffixes, ISP regional domains, privacy
     * relays, typo domains). They must never be reported as disposable.
     *
     * @return array<string, array{string}>
     */
    public static function legitimateDomainProvider(): array
    {
        $domains = [
            'gmail.com', 'outlook.com', 'yahoo.com', 'icloud.com', 'proton.me', '163.com', 'qq.com', 'naver.com',
            'yandex.net', 'namecheap.com', 'cafe24.com', 'nyc.rr.com', 'vip.126.com', 'sutterhealth.org',
            'wo.com.cn', 'any.msk.ru', 'any.pp.ua', 'test.com', 'domain.com', 'simplelogin.co', 'aleeas.com',
            'passmail.com', 'duck.com', 'mozmail.com', 'gamil.com', 'hotmal.com',
        ];

        return array_combine($domains, array_map(static fn (string $domain): array => [$domain], $domains));
    }

    #[DataProvider('legitimateDomainProvider')]
    public function testBundledListHasNoKnownFalsePositives(string $domain): void
    {
        $check = EmailValidator::withConfig($this->basic()->build())->checkDisposable($domain);

        $this->assertFalse($check->isDisposable(), "{$domain} matched {$check->getMatchedDomain()}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disposableDomainProvider(): array
    {
        $domains = [
            'mailinator.com', 'guerrillamail.com', 'sharklasers.com', 'yopmail.com', '10minutemail.com',
            'temp-mail.org', 'temp-mail.io', 'tempmail.com', 'trashmail.com', 'getnada.com', 'maildrop.cc',
        ];

        return array_combine($domains, array_map(static fn (string $domain): array => [$domain], $domains));
    }

    #[DataProvider('disposableDomainProvider')]
    public function testBundledListDetectsWellKnownProviders(string $domain): void
    {
        $this->assertTrue(EmailValidator::withConfig($this->basic()->build())->isDisposableDomain($domain));
    }
}
