<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;

final readonly class JsonFileEditor
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

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new ComposeException("JSON root must be an object or array: {$path}");
        }

        $segments = explode('.', $arguments['key']);

        match ($operation) {
            'set' => $this->set($data, $segments, $arguments['value']),
            'merge' => $this->merge($data, $segments, $arguments['values']),
            'push' => $this->push($data, $segments, $arguments['value']),
            'remove' => $this->remove($data, $segments),
            default => throw new ComposeException("Unknown JSON edit: {$operation}"),
        };

        $this->files->atomicWrite($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        return "Edited {$path}";
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $segments
     */
    private function set(array &$data, array $segments, mixed $value): void
    {
        $key = array_pop($segments);
        $cursor = &$data;

        foreach ($segments as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor[$key] = $value;
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $segments
     * @param  array<mixed>  $values
     */
    private function merge(array &$data, array $segments, array $values): void
    {
        $current = $this->get($data, $segments);
        $this->set($data, $segments, array_merge(is_array($current) ? $current : [], $values));
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $segments
     */
    private function push(array &$data, array $segments, mixed $value): void
    {
        $current = $this->get($data, $segments);
        $values = is_array($current) ? array_values($current) : [];
        $values[] = $value;
        $this->set($data, $segments, $values);
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $segments
     */
    private function remove(array &$data, array $segments): void
    {
        $key = array_pop($segments);
        $cursor = &$data;

        foreach ($segments as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                return;
            }

            $cursor = &$cursor[$segment];
        }

        unset($cursor[$key]);
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $segments
     */
    private function get(array $data, array $segments): mixed
    {
        foreach ($segments as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}
