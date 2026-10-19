<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Result;

use JsonSerializable;

/**
 * Class DisposableCheckResult
 *
 * Explains a disposable-domain lookup: whether the address is disposable,
 * which list entry matched (the domain itself or one of its parents) and
 * which list that entry came from.
 *
 * @package HarunGecit\EmailValidator\Result
 */
final class DisposableCheckResult implements JsonSerializable
{
    /** The bundled blocklist, or the file configured as `lists.blocklist_path`. */
    public const SOURCE_BLOCKLIST = 'blocklist';

    /** Domains added through configuration (`lists.custom_blocklist*`) or addToBlocklist(). */
    public const SOURCE_CUSTOM = 'custom';

    /** Domains downloaded by the list synchronizer (`lists.synced_blocklist_path`). */
    public const SOURCE_SYNCED = 'synced';

    /** The matched entry is on the allowlist, so the address is not disposable. */
    public const SOURCE_ALLOWLIST = 'allowlist';

    /**
     * @param string $input The email address or domain that was checked.
     * @param string|null $domain The normalized domain, or null if none could be extracted.
     * @param bool $disposable Whether the domain is considered disposable.
     * @param string|null $matchedDomain The list entry that decided the result.
     * @param string|null $source One of the SOURCE_* constants, or null if nothing matched.
     */
    public function __construct(
        private string $input,
        private ?string $domain,
        private bool $disposable,
        private ?string $matchedDomain = null,
        private ?string $source = null
    ) {
    }

    /**
     * Gets the email address or domain that was checked.
     *
     * @return string
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * Gets the normalized domain that was looked up.
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Whether the address is disposable.
     *
     * @return bool
     */
    public function isDisposable(): bool
    {
        return $this->disposable;
    }

    /**
     * Gets the list entry that decided the result, e.g. "mailinator.com" for
     * "user@eu.mailinator.com". Null when no list entry matched.
     *
     * @return string|null
     */
    public function getMatchedDomain(): ?string
    {
        return $this->matchedDomain;
    }

    /**
     * Gets the list the matched entry belongs to (one of the SOURCE_* constants).
     *
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Whether the domain was explicitly allowed by the allowlist.
     *
     * @return bool
     */
    public function isAllowlisted(): bool
    {
        return $this->source === self::SOURCE_ALLOWLIST;
    }

    /**
     * Whether the decision came from a parent domain entry rather than the
     * domain itself (e.g. "sub.mailinator.com" matched "mailinator.com").
     *
     * @return bool
     */
    public function isSubdomainMatch(): bool
    {
        return $this->matchedDomain !== null && $this->matchedDomain !== $this->domain;
    }

    /**
     * Converts the result to an array.
     *
     * @return array{input: string, domain: string|null, disposable: bool, matched_domain: string|null, source: string|null}
     */
    public function toArray(): array
    {
        return [
            'input' => $this->input,
            'domain' => $this->domain,
            'disposable' => $this->disposable,
            'matched_domain' => $this->matchedDomain,
            'source' => $this->source,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
