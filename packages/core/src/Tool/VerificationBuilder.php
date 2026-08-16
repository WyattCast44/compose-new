<?php

declare(strict_types=1);

namespace Compose\Tool;

use Closure;
use Compose\PendingAction;
use Compose\Step;

final readonly class VerificationBuilder
{
    public function __construct(private Step $step) {}

    public function fileExists(string $path): PendingAction
    {
        return $this->step->queue('verify:file', "verify file exists: {$path}", ['path' => $path]);
    }

    /** @param list<string> $command */
    public function command(array $command): PendingAction
    {
        return $this->step->queue('verify:command', 'verify command: '.implode(' ', $command), ['command' => $command]);
    }

    public function callback(Closure $callback, string $description = 'verify callback'): PendingAction
    {
        return $this->step->queue('verify:callback', $description, ['callback' => $callback]);
    }
}
