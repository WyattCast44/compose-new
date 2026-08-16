<?php

declare(strict_types=1);

namespace Compose\Planning;

use Compose\Definition\StepDefinition;
use JsonSerializable;
use Stringable;

final readonly class Plan implements JsonSerializable, Stringable
{
    /** @param list<StepDefinition> $steps */
    public function __construct(public string $name, public array $steps) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['name' => $this->name, 'steps' => $this->steps];
    }

    public function __toString(): string
    {
        $lines = ["Compose — {$this->name}", ''];

        foreach ($this->steps as $index => $step) {
            $lines[] = sprintf('%d. %s  [%s]', $index + 1, $step->name, $step->cwd);

            foreach ($step->actions as $action) {
                $risks = $action->risks === []
                    ? ''
                    : ' ['.implode(', ', array_map(static fn ($risk): string => $risk->value, $action->risks)).']';
                $optional = $action->policy->optional ? ' (optional)' : '';
                $lines[] = "   - {$action->description}{$optional}{$risks}";
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }
}
