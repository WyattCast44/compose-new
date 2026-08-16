<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Exception\ComposeException;

final class AgentRegistry
{
    /** @var array<string, AgentDriver> */
    private array $drivers = [];

    public function __construct(AgentDriver ...$drivers)
    {
        foreach ($drivers ?: [new CodexDriver, new ClaudeCodeDriver] as $driver) {
            $this->register($driver);
        }
    }

    public function register(AgentDriver $driver): self
    {
        $this->drivers[$driver->id()] = $driver;

        return $this;
    }

    public function get(string $id): AgentDriver
    {
        return $this->drivers[$id] ?? throw new ComposeException("Unknown AI agent driver: {$id}");
    }
}
