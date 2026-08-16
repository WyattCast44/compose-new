<?php

declare(strict_types=1);

namespace Compose\Execution;

use JsonSerializable;

final readonly class RunResult implements JsonSerializable
{
    /** @param list<StepResult> $steps */
    public function __construct(
        public bool $successful,
        public array $steps,
        public ?int $failedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
