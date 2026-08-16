<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;

final readonly class DotenvFileEditor
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

        $lines = preg_split('/\R/', rtrim($contents, "\r\n")) ?: [];
        $key = (string) ($arguments['key'] ?? '');
        $matches = $key === '' ? [] : $this->matches($lines, $key);
        if (count($matches) > 1) {
            throw new ComposeException("Environment key {$key} occurs more than once.");
        }

        $lines = match ($operation) {
            'set' => $this->set($lines, $key, $arguments['value'], $matches),
            'has' => $this->has($lines, $key, $matches),
            'remove' => $this->remove($lines, $matches),
            'comment' => $this->comment($lines, $key, $matches, true),
            'uncomment' => $this->comment($lines, $key, $matches, false),
            'section' => $this->section($lines, (string) $arguments['title'], $arguments['values']),
            default => throw new ComposeException("Unknown .env edit: {$operation}"),
        };

        $this->files->atomicWrite($target, implode(PHP_EOL, $lines).PHP_EOL);

        return "Edited {$path}";
    }

    /**
     * @param  list<string>  $lines
     * @return list<int>
     */
    private function matches(array $lines, string $key): array
    {
        $matches = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*#?\s*'.preg_quote($key, '/').'\s*=/', $line) === 1) {
                $matches[] = $index;
            }
        }

        return $matches;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $matches
     * @return list<string>
     */
    private function set(array $lines, string $key, mixed $value, array $matches): array
    {
        $line = $key.'='.$this->renderValue($value);
        if ($matches === []) {
            $lines[] = $line;
        } else {
            $lines[$matches[0]] = $line;
        }

        return array_values($lines);
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $matches
     * @return list<string>
     */
    private function has(array $lines, string $key, array $matches): array
    {
        if ($matches === []) {
            throw new ComposeException("Environment key {$key} does not exist.");
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $matches
     * @return list<string>
     */
    private function remove(array $lines, array $matches): array
    {
        if ($matches !== []) {
            unset($lines[$matches[0]]);
            $lines = array_values($lines);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $matches
     * @return list<string>
     */
    private function comment(array $lines, string $key, array $matches, bool $comment): array
    {
        if ($matches === []) {
            throw new ComposeException("Environment key {$key} does not exist.");
        }
        $index = $matches[0];
        $lines[$index] = $comment
            ? (str_starts_with(ltrim($lines[$index]), '#') ? $lines[$index] : '# '.$lines[$index])
            : preg_replace('/^\s*#\s?/', '', $lines[$index], 1) ?? $lines[$index];

        return array_values($lines);
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function section(array $lines, string $title, array $values): array
    {
        if ($lines !== [] && end($lines) !== '') {
            $lines[] = '';
        }
        $lines[] = '# '.$title;
        foreach ($values as $key => $value) {
            $matches = $this->matches($lines, (string) $key);
            $lines = $this->set($lines, (string) $key, $value, $matches);
        }

        return $lines;
    }

    private function renderValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $value = (string) $value;

        return preg_match('/^[A-Za-z0-9_\.\-:\/]*$/', $value) === 1
            ? $value
            : '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"';
    }
}
