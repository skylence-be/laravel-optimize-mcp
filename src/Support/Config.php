<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Support;

use Illuminate\Support\Str;

class Config
{
    protected const FILE = 'optimize-mcp.json';

    /**
     * @return array<int, string>
     */
    public function getEditors(): array
    {
        return $this->get('editors', []);
    }

    /**
     * @param  array<int, string>  $editors
     */
    public function setEditors(array $editors): void
    {
        $this->set('editors', $editors);
    }

    public function setSail(bool $useSail): void
    {
        $this->set('sail', $useSail);
    }

    public function getSail(): bool
    {
        return $this->get('sail', false);
    }

    public function flush(): void
    {
        $path = base_path(self::FILE);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        $config = $this->all();

        return data_get($config, $key, $default);
    }

    protected function set(string $key, mixed $value): void
    {
        $config = array_filter($this->all());

        data_set($config, $key, $value);

        ksort($config);

        $path = base_path(self::FILE);

        file_put_contents($path, Str::of(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->append(PHP_EOL));
    }

    protected function all(): array
    {
        $path = base_path(self::FILE);

        if (! file_exists($path)) {
            return [];
        }

        $config = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $config ?? [];
    }
}
