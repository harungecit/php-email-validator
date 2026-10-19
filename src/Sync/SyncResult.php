<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Sync;

use JsonSerializable;

/**
 * Class SyncResult
 *
 * Outcome of a ListSynchronizer run.
 *
 * @package HarunGecit\EmailValidator\Sync
 */
final class SyncResult implements JsonSerializable
{
    /**
     * @param string $path The synced list file.
     * @param bool $successful Whether at least one source was downloaded and the file is consistent.
     * @param bool $changed Whether the file was (re)written.
     * @param int $added Domains added to the synced list.
     * @param int $removed Domains removed from the synced list.
     * @param int $total Domains in the synced list after the run.
     * @param array<string, array{domains: int, error: string|null}> $sources Per-URL download results.
     * @param bool $replaced Whether the list was rebuilt from scratch (replace mode) instead of appended to.
     */
    public function __construct(
        private string $path,
        private bool $successful,
        private bool $changed,
        private int $added,
        private int $removed,
        private int $total,
        private array $sources,
        private bool $replaced = false
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function hasChanged(): bool
    {
        return $this->changed;
    }

    public function getAdded(): int
    {
        return $this->added;
    }

    public function getRemoved(): int
    {
        return $this->removed;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function wasReplaced(): bool
    {
        return $this->replaced;
    }

    /**
     * @return array<string, array{domains: int, error: string|null}>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @return array<string, string> URL => error message, for sources that failed.
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->sources as $url => $source) {
            if ($source['error'] !== null) {
                $errors[$url] = $source['error'];
            }
        }
        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'successful' => $this->successful,
            'changed' => $this->changed,
            'replaced' => $this->replaced,
            'added' => $this->added,
            'removed' => $this->removed,
            'total' => $this->total,
            'sources' => $this->sources,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
