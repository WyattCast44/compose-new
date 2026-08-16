<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Exception\ComposeException;
use Compose\Execution\FileEditor;
use Compose\Execution\PathResolver;

final readonly class BakeStore
{
    public function __construct(private PathResolver $paths = new PathResolver, private FileEditor $files = new FileEditor) {}

    /** @param array<string, mixed> $payload */
    public function key(string $cwd, array $payload): string
    {
        $context = [];
        foreach ($payload['using'] ?? [] as $path) {
            $target = $this->paths->resolve($cwd, (string) $path);
            if (! is_file($target) || is_link($target)) {
                throw new ComposeException("AI context path must be a regular file: {$path}");
            }
            $context[(string) $path] = hash_file('sha256', $target);
        }

        return hash('sha256', json_encode([$payload, $context], JSON_THROW_ON_ERROR));
    }

    public function get(string $root, string $key): ?string
    {
        $path = $root.'/.compose/bakes/'.$key.'.patch';

        return is_file($path) ? file_get_contents($path) ?: null : null;
    }

    /** @param array<string, mixed> $metadata */
    public function put(string $root, string $key, string $patch, array $metadata): void
    {
        $directory = $root.'/.compose/bakes';
        $this->excludeFromGit($root);
        $this->files->atomicWrite($directory.'/'.$key.'.patch', $patch);
        $this->files->atomicWrite(
            $directory.'/'.$key.'.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    private function excludeFromGit(string $root): void
    {
        $exclude = $root.'/.git/info/exclude';
        if (! is_file($exclude)) {
            return;
        }
        $contents = file_get_contents($exclude) ?: '';
        if (preg_match('#^/?\.compose/bakes/?$#m', $contents) === 1) {
            return;
        }

        $this->files->atomicWrite($exclude, rtrim($contents).PHP_EOL.'/.compose/bakes/'.PHP_EOL);
    }
}
