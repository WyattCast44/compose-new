<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\PendingAction;
use Compose\Step;

final readonly class TextEditor
{
    public function __construct(private Step $step, private string $path) {}

    public function replace(string $search, string $replacement, int $expected = 1): PendingAction
    {
        return $this->edit('replace', compact('search', 'replacement', 'expected'));
    }

    public function replaceRegex(string $pattern, string $replacement, ?int $expected = null): PendingAction
    {
        return $this->edit('replace_regex', compact('pattern', 'replacement', 'expected'));
    }

    public function append(string $contents): PendingAction
    {
        return $this->edit('append', compact('contents'));
    }

    public function prepend(string $contents): PendingAction
    {
        return $this->edit('prepend', compact('contents'));
    }

    public function insertAfter(string $marker, string $contents): PendingAction
    {
        return $this->edit('insert_after', compact('marker', 'contents'));
    }

    public function insertBefore(string $marker, string $contents): PendingAction
    {
        return $this->edit('insert_before', compact('marker', 'contents'));
    }

    /** @param array<string, mixed> $arguments */
    private function edit(string $operation, array $arguments): PendingAction
    {
        return $this->step->queue(
            'edit:text',
            "{$operation} text in {$this->path}",
            ['path' => $this->path, 'operation' => $operation, 'arguments' => $arguments],
        );
    }
}
