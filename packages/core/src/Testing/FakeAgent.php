<?php

declare(strict_types=1);

namespace Compose\Testing;

use Compose\AI\AgentDriver;
use Compose\AI\AgentRequest;
use Compose\AI\AgentRunResult;

final class FakeAgent implements AgentDriver
{
    /** @var list<AgentRequest> */
    public array $requests = [];

    public function __construct(private readonly AgentRunResult $result = new AgentRunResult(true, 'done'), private readonly string $name = 'fake') {}

    public function id(): string
    {
        return $this->name;
    }

    public function available(): bool
    {
        return true;
    }

    public function supportsResume(): bool
    {
        return true;
    }

    public function start(AgentRequest $request): AgentRunResult
    {
        $this->requests[] = $request;

        return $this->result;
    }

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult
    {
        $this->requests[] = $request;

        return $this->result;
    }
}
