<?php

declare(strict_types=1);

namespace Compose\Laravel;

use Compose\PendingAction;

final readonly class EnvEditor
{
    public function __construct(private LaravelStep $step, private string $path) {}

    public function set(string $key, mixed $value): PendingAction
    {
        return $this->edit('set', compact('key', 'value'));
    }

    public function has(string $key): PendingAction
    {
        return $this->edit('has', compact('key'));
    }

    public function remove(string $key): PendingAction
    {
        return $this->edit('remove', compact('key'));
    }

    public function comment(string $key): PendingAction
    {
        return $this->edit('comment', compact('key'));
    }

    public function uncomment(string $key): PendingAction
    {
        return $this->edit('uncomment', compact('key'));
    }

    /** @param array<string, mixed> $values */
    public function section(string $title, array $values): PendingAction
    {
        return $this->edit('section', compact('title', 'values'));
    }

    /** @param array<string, mixed> $arguments */
    private function edit(string $operation, array $arguments): PendingAction
    {
        return $this->step->queue('edit:env', "{$operation} in {$this->path}", [
            'path' => $this->path,
            'operation' => $operation,
            'arguments' => $arguments,
        ]);
    }
}
