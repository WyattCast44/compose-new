<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;

final readonly class TextFileEditor
{
    public function __construct(private PathResolver $paths = new PathResolver, private FileEditor $files = new FileEditor) {}

    /** @param array<string, mixed> $arguments */
    public function edit(string $cwd, string $path, string $operation, array $arguments): string
    {
        $target = $this->paths->resolve($cwd, $path);
        $contents = file_get_contents($target);

        if ($contents === false) {
            throw new ComposeException("Unable to read {$path}");
        }

        $updated = match ($operation) {
            'replace' => $this->replace($contents, $arguments),
            'replace_regex' => $this->replaceRegex($contents, $arguments),
            'append' => $contents.$arguments['contents'],
            'prepend' => $arguments['contents'].$contents,
            'insert_after' => $this->insert($contents, $arguments['marker'], $arguments['contents'], true),
            'insert_before' => $this->insert($contents, $arguments['marker'], $arguments['contents'], false),
            default => throw new ComposeException("Unknown text edit: {$operation}"),
        };

        $this->files->atomicWrite($target, $updated);

        return "Edited {$path}";
    }

    /** @param array<string, mixed> $arguments */
    private function replace(string $contents, array $arguments): string
    {
        $count = substr_count($contents, $arguments['search']);

        if ($count !== $arguments['expected']) {
            throw new ComposeException("Expected {$arguments['expected']} exact matches; found {$count}.");
        }

        return str_replace($arguments['search'], $arguments['replacement'], $contents);
    }

    /** @param array<string, mixed> $arguments */
    private function replaceRegex(string $contents, array $arguments): string
    {
        $updated = preg_replace($arguments['pattern'], $arguments['replacement'], $contents, -1, $count);

        if ($updated === null) {
            throw new ComposeException('Invalid regular expression.');
        }

        if ($arguments['expected'] !== null && $count !== $arguments['expected']) {
            throw new ComposeException("Expected {$arguments['expected']} regex matches; found {$count}.");
        }

        return $updated;
    }

    private function insert(string $contents, string $marker, string $addition, bool $after): string
    {
        if (substr_count($contents, $marker) !== 1) {
            throw new ComposeException('Insert marker must occur exactly once.');
        }

        return $after
            ? str_replace($marker, $marker.$addition, $contents)
            : str_replace($marker, $addition.$marker, $contents);
    }
}
