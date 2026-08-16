<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\SafetyException;

final class PathResolver
{
    public function resolve(string $cwd, string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            throw new SafetyException("Unsafe path: {$path}");
        }

        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new SafetyException("Path escapes the step directory: {$path}");
            }

            $segments[] = $segment;
        }

        $root = realpath($cwd);

        if ($root === false || ! is_dir($root)) {
            throw new SafetyException("Step directory does not exist: {$cwd}");
        }

        $target = $root.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
        $ancestor = $target;

        while (! file_exists($ancestor) && ! is_link($ancestor)) {
            $parent = dirname($ancestor);

            if ($parent === $ancestor) {
                break;
            }

            $ancestor = $parent;
        }

        $resolvedAncestor = realpath($ancestor);

        if ($resolvedAncestor !== false && ! $this->inside($root, $resolvedAncestor)) {
            throw new SafetyException("Path resolves outside the step directory: {$path}");
        }

        return $target;
    }

    public function assertInside(string $root, string $target): void
    {
        $root = realpath($root) ?: $root;
        $target = realpath($target) ?: $target;

        if (! $this->inside($root, $target)) {
            throw new SafetyException("Target is outside the allowed directory: {$target}");
        }
    }

    private function inside(string $root, string $target): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $target = rtrim(str_replace('\\', '/', $target), '/');

        return $target === $root || str_starts_with($target, $root.'/');
    }
}
