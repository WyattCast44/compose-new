<?php

declare(strict_types=1);

namespace Compose\Execution;

use JsonSerializable;

final readonly class StepResult implements JsonSerializable
{
    /** @param list<ActionResult> $actions */
    public function __construct(
        public string $name,
        public bool $successful,
        public array $actions,
        public bool $rolledBack = false,
        public ?string $rollbackError = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
