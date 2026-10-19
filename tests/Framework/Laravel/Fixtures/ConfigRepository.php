<?php

declare(strict_types=1);

namespace HarunGecit\EmailValidator\Tests\Framework\Laravel\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;

/**
 * Minimal config repository (illuminate/config is not a dev dependency).
 */
class ConfigRepository implements Repository
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    public function has($key)
    {
        return Arr::has($this->items, $key);
    }

    public function get($key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    public function all()
    {
        return $this->items;
    }

    public function set($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $k => $v) {
            Arr::set($this->items, $k, $v);
        }
    }

    public function prepend($key, $value)
    {
        $array = (array) $this->get($key, []);
        array_unshift($array, $value);
        $this->set($key, $array);
    }

    public function push($key, $value)
    {
        $array = (array) $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }
}
