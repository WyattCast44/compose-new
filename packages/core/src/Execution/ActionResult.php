<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\AI\AgentResult;
use Compose\Definition\ActionDefinition;
use JsonSerializable;

final readonly class ActionResult implements JsonSerializable
{
    public function __construct(
        public ActionDefinition $action,
        public bool $successful,
        public string $output = '',
        public string $errorOutput = '',
        public int $exitCode = 0,
        public float $duration = 0,
        public bool $warning = false,
        public int $attempts = 1,
        public ?AgentResult $agent = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action,
            'successful' => $this->successful,
            'output' => $this->output,
            'error_output' => $this->errorOutput,
            'exit_code' => $this->exitCode,
            'duration' => $this->duration,
            'warning' => $this->warning,
            'attempts' => $this->attempts,
            'agent' => $this->agent,
        ];
    }
}
