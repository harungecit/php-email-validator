<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Validators;

use HarunGecit\EmailValidator\Contracts\CacheInterface;
use HarunGecit\EmailValidator\Config\Configuration;

/**
 * Class CatchAllDetector
 *
 * Detects catch-all email domains.
 *
 * @package HarunGecit\EmailValidator\Validators
 */
class CatchAllDetector
{
    /**
     * @var SmtpValidator SMTP validator instance.
     */
    private SmtpValidator $smtpValidator;

    /**
     * @var CacheInterface Cache adapter.
     */
    private CacheInterface $cache;

    /**
     * @var int Cache TTL in seconds.
     */
    private int $cacheTtl;

    /**
     * @var string Cache key prefix.
     */
    private string $cachePrefix = 'catch_all_';

    /**
     * CatchAllDetector constructor.
     *
     * @param SmtpValidator $smtpValidator SMTP validator instance.
     * @param CacheInterface $cache Cache adapter.
     * @param Configuration|null $config Optional configuration.
     */
    public function __construct(
        SmtpValidator $smtpValidator,
        CacheInterface $cache,
        ?Configuration $config = null
    ) {
        $this->smtpValidator = $smtpValidator;
        $this->cache = $cache;
        $this->cacheTtl = $config?->getCacheTtl() ?? 3600;
    }

    /**
     * Checks if an email domain is a catch-all.
     *
     * @param string $email The email address to check.
     * @return bool True if the domain is catch-all.
     */
    public function isCatchAll(string $email): bool
    {
        $domain = $this->extractDomain($email);

        if ($domain === null) {
            return false;
        }

        return $this->isDomainCatchAll($domain);
    }

    /**
     * Checks if a domain is a catch-all.
     *
     * @param string $domain The domain to check.
     * @return bool True if the domain is catch-all.
     */
    public function isDomainCatchAll(string $domain): bool
    {
        $cacheKey = $this->cachePrefix . $domain;

        // Check cache first
        if ($this->cache->has($cacheKey)) {
            return (bool) $this->cache->get($cacheKey, false);
        }

        // Perform SMTP check
        $isCatchAll = $this->smtpValidator->isCatchAll($domain);

        // Cache the result
        $this->cache->set($cacheKey, $isCatchAll, $this->cacheTtl);

        return $isCatchAll;
    }

    /**
     * Clears the cache for a specific domain.
     *
     * @param string $domain The domain to clear.
     * @return void
     */
    public function clearCache(string $domain): void
    {
        $this->cache->delete($this->cachePrefix . $domain);
    }

    /**
     * Sets the cache TTL.
     *
     * @param int $seconds TTL in seconds.
     * @return self
     */
    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = max(0, $seconds);
        return $this;
    }

    /**
     * Extracts the domain from an email address.
     *
     * @param string $email The email address.
     * @return string|null The domain or null.
     */
    private function extractDomain(string $email): ?string
    {
        $atPos = strrpos($email, '@');
        return $atPos !== false ? strtolower(substr($email, $atPos + 1)) : null;
    }
}
