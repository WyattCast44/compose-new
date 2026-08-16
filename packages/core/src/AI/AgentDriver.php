<?php

declare(strict_types=1);

namespace Compose\AI;

interface AgentDriver
{
    public function id(): string;

    public function available(): bool;

    public function start(AgentRequest $request): AgentRunResult;

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult;

    public function supportsResume(): bool;
}
