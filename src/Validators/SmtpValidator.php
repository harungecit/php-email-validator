<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Validators;

use HarunGecit\EmailValidator\Contracts\SmtpVerifierInterface;
use HarunGecit\EmailValidator\Config\Configuration;
use HarunGecit\EmailValidator\Exceptions\SmtpConnectionException;

/**
 * Class SmtpValidator
 *
 * Validates email addresses via SMTP protocol.
 *
 * @package HarunGecit\EmailValidator\Validators
 */
class SmtpValidator implements SmtpVerifierInterface
{
    /**
     * @var int Connection timeout in seconds.
     */
    private int $timeout;

    /**
     * @var string Email address for MAIL FROM command.
     */
    private string $fromEmail;

    /**
     * @var string Domain for HELO/EHLO command.
     */
    private string $fromDomain;

    /**
     * @var string|null Last error message.
     */
    private ?string $lastError = null;

    /**
     * @var int Default SMTP port.
     */
    private int $port = 25;

    /**
     * SmtpValidator constructor.
     *
     * @param Configuration|null $config Optional configuration.
     */
    public function __construct(?Configuration $config = null)
    {
        if ($config !== null) {
            $this->timeout = $config->getSmtpTimeout();
            $this->fromEmail = $config->getSmtpFromEmail() ?? 'verify@example.com';
            $this->fromDomain = $config->getSmtpFromDomain() ?? $this->getHostname();
        } else {
            $this->timeout = 10;
            $this->fromEmail = 'verify@example.com';
            $this->fromDomain = $this->getHostname();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $email): bool
    {
        $this->lastError = null;

        $domain = $this->extractDomain($email);
        if ($domain === null) {
            $this->lastError = 'Invalid email format';
            return false;
        }

        $mxHosts = $this->getMxHosts($domain);
        if (empty($mxHosts)) {
            $this->lastError = 'No MX records found';
            return false;
        }

        foreach ($mxHosts as $host) {
            try {
                $result = $this->verifyWithHost($email, $host);
                if ($result === true) {
                    return true;
                }
            } catch (SmtpConnectionException $e) {
                $this->lastError = $e->getMessage();
                continue;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCatchAll(string $domain): bool
    {
        // Generate a random non-existent email address
        $randomEmail = 'nonexistent_' . bin2hex(random_bytes(8)) . '@' . $domain;

        // If a random address is accepted, it's likely a catch-all
        return $this->verify($randomEmail);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Verifies an email with a specific SMTP host.
     *
     * @param string $email The email to verify.
     * @param string $host The SMTP host.
     * @return bool True if the email appears valid.
     * @throws SmtpConnectionException
     */
    private function verifyWithHost(string $email, string $host): bool
    {
        $socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            throw SmtpConnectionException::connectionFailed($host, $this->port, $errstr, $email);
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            // Read greeting
            $this->expectCode($socket, $host, 220);

            // Send EHLO (or HELO as fallback)
            $this->sendCommand($socket, "EHLO {$this->fromDomain}");
            try {
                $this->expectCode($socket, $host, 250);
            } catch (SmtpConnectionException $e) {
                // Try HELO instead
                $this->sendCommand($socket, "HELO {$this->fromDomain}");
                $this->expectCode($socket, $host, 250);
            }

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->expectCode($socket, $host, 250);

            // RCPT TO - This is the key check
            $this->sendCommand($socket, "RCPT TO:<{$email}>");
            $response = $this->getResponse($socket);
            $code = $this->getResponseCode($response);

            // Send QUIT
            $this->sendCommand($socket, "QUIT");
            @fclose($socket);

            // 250, 251 = success
            // 450, 451, 452 = temporary failure (might be valid)
            // 550, 551, 552, 553 = permanent failure (invalid)
            return $code >= 200 && $code < 300;
        } catch (\Exception $e) {
            @fclose($socket);
            throw $e instanceof SmtpConnectionException
                ? $e
                : SmtpConnectionException::connectionFailed($host, $this->port, $e->getMessage(), $email);
        }
    }

    /**
     * Sends a command to the SMTP server.
     *
     * @param resource $socket The socket connection.
     * @param string $command The command to send.
     * @return void
     */
    private function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * Gets the response from the SMTP server.
     *
     * @param resource $socket The socket connection.
     * @return string The response.
     */
    private function getResponse($socket): string
    {
        $response = '';

        while ($line = @fgets($socket, 515)) {
            $response .= $line;

            // Response complete when 4th character is a space
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    /**
     * Gets the response code from an SMTP response.
     *
     * @param string $response The SMTP response.
     * @return int The response code.
     */
    private function getResponseCode(string $response): int
    {
        return (int) substr($response, 0, 3);
    }

    /**
     * Expects a specific response code.
     *
     * @param resource $socket The socket connection.
     * @param string $host The SMTP host.
     * @param int $expectedCode The expected code.
     * @throws SmtpConnectionException
     */
    private function expectCode($socket, string $host, int $expectedCode): void
    {
        $response = $this->getResponse($socket);
        $code = $this->getResponseCode($response);

        if ($code !== $expectedCode) {
            throw SmtpConnectionException::unexpectedResponse($host, $expectedCode, $code, $response);
        }
    }

    /**
     * Gets MX hosts for a domain, sorted by priority.
     *
     * @param string $domain The domain.
     * @return array<string> Sorted MX hosts.
     */
    private function getMxHosts(string $domain): array
    {
        $mxHosts = [];
        $mxWeights = [];

        if (@getmxrr($domain, $mxHosts, $mxWeights)) {
            // Sort by weight (priority)
            array_multisort($mxWeights, SORT_ASC, SORT_NUMERIC, $mxHosts);
            return $mxHosts;
        }

        // Fallback to A record
        $ip = @gethostbyname($domain);
        if ($ip !== $domain) {
            return [$domain];
        }

        return [];
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

    /**
     * Gets the server hostname.
     *
     * @return string
     */
    private function getHostname(): string
    {
        $hostname = gethostname();
        return $hostname !== false ? $hostname : 'localhost';
    }

    /**
     * Sets the connection timeout.
     *
     * @param int $seconds Timeout in seconds.
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * Sets the MAIL FROM email address.
     *
     * @param string $email The email address.
     * @return self
     */
    public function setFromEmail(string $email): self
    {
        $this->fromEmail = $email;
        return $this;
    }

    /**
     * Sets the HELO domain.
     *
     * @param string $domain The domain.
     * @return self
     */
    public function setFromDomain(string $domain): self
    {
        $this->fromDomain = $domain;
        return $this;
    }

    /**
     * Sets the SMTP port.
     *
     * @param int $port The port number.
     * @return self
     */
    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }
}
