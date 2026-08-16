<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\PendingAction;
use Compose\Step;

final readonly class JsonEditor
{
    public function __construct(private Step $step, private string $path) {}

    public function set(string $key, mixed $value): PendingAction
    {
        return $this->edit('set', compact('key', 'value'));
    }

    /** @param array<mixed> $values */
    public function merge(string $key, array $values): PendingAction
    {
        return $this->edit('merge', compact('key', 'values'));
    }

    public function push(string $key, mixed $value): PendingAction
    {
        return $this->edit('push', compact('key', 'value'));
    }

    public function remove(string $key): PendingAction
    {
        return $this->edit('remove', compact('key'));
    }

    /** @param array<string, mixed> $arguments */
    private function edit(string $operation, array $arguments): PendingAction
    {
        return $this->step->queue(
            'edit:json',
            "{$operation} {$this->path}:{$arguments['key']}",
            ['path' => $this->path, 'operation' => $operation, 'arguments' => $arguments],
        );
    }
}
