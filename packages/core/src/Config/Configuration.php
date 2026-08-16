<?php

declare(strict_types=1);

namespace Compose\Config;

final readonly class Configuration
{
    public function __construct(public string $agent = 'codex', public ?string $model = null) {}

    public static function load(string $root, ?string $agent = null, ?string $model = null): self
    {
        $values = [];
        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $values = self::read($home.'/.config/compose/config.json');
        }
        $values = array_replace($values, self::read($root.'/.compose/config.json'));

        return new self($agent ?? ($values['agent'] ?? 'codex'), $model ?? ($values['model'] ?? null));
    }

    /** @return array<string, string> */
    private static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode(file_get_contents($path) ?: '{}', true);

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
