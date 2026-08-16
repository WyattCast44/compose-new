<?php

declare(strict_types=1);

namespace Compose\Definition;

use JsonSerializable;

final readonly class StepDefinition implements JsonSerializable
{
    /** @param list<ActionDefinition> $actions */
    public function __construct(
        public string $name,
        public string $cwd,
        public array $actions,
        public StepConfigData $config,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'cwd' => $this->cwd,
            'actions' => $this->actions,
        ];
    }
}
