<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Sync;

use RuntimeException;

/**
 * Class HttpDownloader
 *
 * Minimal dependency-free HTTP GET used by the list synchronizer. Uses
 * ext-curl when available and falls back to PHP streams otherwise.
 *
 * @package HarunGecit\EmailValidator\Sync
 */
class HttpDownloader
{
    /** Refuse responses larger than this many bytes. */
    public const MAX_BYTES = 52428800;

    private const USER_AGENT = 'harungecit/php-email-validator (+https://github.com/harungecit/php-email-validator)';

    /**
     * Downloads a URL and returns the response body.
     *
     * @param string $url An http(s) URL.
     * @param int $timeout Timeout in seconds.
     * @return string
     * @throws RuntimeException On network errors, non-2xx responses or oversized bodies.
     */
    public function get(string $url, int $timeout): string
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new RuntimeException("Only http(s) URLs are supported: {$url}");
        }

        return function_exists('curl_init') ? $this->getWithCurl($url, $timeout) : $this->getWithStreams($url, $timeout);
    }

    private function getWithCurl(string $url, int $timeout): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException("Unable to initialize cURL for {$url}");
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($handle, float $downloadTotal, float $downloaded): int {
                return $downloaded > self::MAX_BYTES ? 1 : 0;
            },
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        // The CurlHandle object is released when it goes out of scope (curl_close() is a no-op since PHP 8.0).

        if ($body === false || !is_string($body)) {
            throw new RuntimeException("Download failed for {$url}: " . ($error !== '' ? $error : 'unknown error'));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Download failed for {$url}: HTTP {$status}");
        }

        return $body;
    }

    private function getWithStreams(string $url, int $timeout): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
                'user_agent' => self::USER_AGENT,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException("Download failed for {$url}");
        }

        $body = stream_get_contents($stream, self::MAX_BYTES + 1);
        $meta = stream_get_meta_data($stream);
        fclose($stream);

        if ($body === false) {
            throw new RuntimeException("Download failed for {$url}");
        }

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException("Download refused for {$url}: response is larger than " . self::MAX_BYTES . ' bytes');
        }

        $status = 0;
        foreach ((array) ($meta['wrapper_data'] ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Download failed for {$url}: HTTP {$status}");
        }

        return $body;
    }
}
