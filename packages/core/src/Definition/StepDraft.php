<?php

declare(strict_types=1);

namespace Compose\Definition;

use Compose\Exception\DefinitionException;

final class StepDraft
{
    /** @var list<ActionDraft> */
    private array $actions = [];

    /** @var list<Finalizable> */
    private array $finalizables = [];

    public function __construct(
        public readonly string $name,
        public readonly string $cwd,
        public readonly StepConfigData $config,
    ) {}

    public function add(ActionDraft $action): ActionDraft
    {
        $this->actions[] = $action;

        return $action;
    }

    public function track(Finalizable $finalizable): void
    {
        $this->finalizables[] = $finalizable;
    }

    public function freeze(): StepDefinition
    {
        foreach ($this->finalizables as $finalizable) {
            $finalizable->assertFinalized();
        }

        if ($this->actions === []) {
            throw new DefinitionException("Step '{$this->name}' must define at least one action.");
        }

        return new StepDefinition(
            name: $this->name,
            cwd: $this->cwd,
            actions: array_map(
                fn (ActionDraft $action): ActionDefinition => $action->freeze($this->config),
                $this->actions,
            ),
            config: $this->config,
        );
    }
}
