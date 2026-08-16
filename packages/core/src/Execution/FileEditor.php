<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;
use Compose\Exception\SafetyException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class FileEditor
{
    public function __construct(private PathResolver $paths = new PathResolver) {}

    public function create(string $cwd, string $path, string $contents, bool $overwrite): string
    {
        $target = $this->paths->resolve($cwd, $path);

        if ((file_exists($target) || is_link($target)) && ! $overwrite) {
            throw new ComposeException("File already exists: {$path}");
        }

        $this->atomicWrite($target, $contents);

        return "Created {$path}";
    }

    public function copy(string $cwd, string $from, string $to, bool $overwrite): string
    {
        $source = $this->paths->resolve($cwd, $from);
        $target = $this->paths->resolve($cwd, $to);

        if (! is_file($source) || is_link($source)) {
            throw new ComposeException("Copy source is not a regular file: {$from}");
        }

        if (file_exists($target) && ! $overwrite) {
            throw new ComposeException("Copy target already exists: {$to}");
        }

        $contents = file_get_contents($source);

        if ($contents === false) {
            throw new ComposeException("Unable to read {$from}");
        }

        $this->atomicWrite($target, $contents);

        return "Copied {$from} to {$to}";
    }

    public function move(string $cwd, string $from, string $to, bool $overwrite): string
    {
        $source = $this->paths->resolve($cwd, $from);
        $target = $this->paths->resolve($cwd, $to);

        if (! file_exists($source) && ! is_link($source)) {
            throw new ComposeException("Move source does not exist: {$from}");
        }

        if ((file_exists($target) || is_link($target)) && ! $overwrite) {
            throw new ComposeException("Move target already exists: {$to}");
        }

        if ($overwrite && (file_exists($target) || is_link($target))) {
            $this->deleteTarget($target);
        }

        $this->ensureParent($target);

        if (! rename($source, $target)) {
            throw new ComposeException("Unable to move {$from} to {$to}");
        }

        return "Moved {$from} to {$to}";
    }

    /** @param list<string> $paths */
    public function delete(string $cwd, array $paths): string
    {
        foreach ($paths as $path) {
            if ($path === '.' || $path === '') {
                throw new SafetyException('Deleting the step directory itself is not allowed.');
            }

            $target = $this->paths->resolve($cwd, $path);

            if (file_exists($target) || is_link($target)) {
                $this->deleteTarget($target);
            }
        }

        return 'Deleted '.implode(', ', $paths);
    }

    public function download(string $cwd, string $url, string $to, bool $overwrite): string
    {
        $contents = @file_get_contents($url);

        if ($contents === false) {
            throw new ComposeException("Unable to download {$url}");
        }

        return $this->create($cwd, $to, $contents, $overwrite);
    }

    public function atomicWrite(string $target, string $contents): void
    {
        $this->ensureParent($target);
        $temporary = tempnam(dirname($target), '.compose-');

        if ($temporary === false) {
            throw new ComposeException("Unable to create a temporary file for {$target}");
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $target)) {
                throw new ComposeException("Unable to write {$target}");
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function ensureParent(string $target): void
    {
        $parent = dirname($target);

        if (! is_dir($parent) && ! mkdir($parent, 0777, true) && ! is_dir($parent)) {
            throw new ComposeException("Unable to create directory {$parent}");
        }
    }

    private function deleteTarget(string $target): void
    {
        if (is_link($target) || is_file($target)) {
            if (! unlink($target)) {
                throw new ComposeException("Unable to delete {$target}");
            }

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink() || $item->isFile()) {
                unlink($path);
            } else {
                rmdir($path);
            }
        }

        rmdir($target);
    }
}
