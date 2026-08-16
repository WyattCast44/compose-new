<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Execution\ProcessResult;
use Compose\Execution\ProcessRunner;
use Compose\Execution\SymfonyProcessRunner;

final readonly class ClaudeCodeDriver implements AgentDriver
{
    public function __construct(private ProcessRunner $processes = new SymfonyProcessRunner) {}

    public function id(): string
    {
        return Agent::Claude->value;
    }

    public function available(): bool
    {
        return $this->processes->run(['claude', '--version'], getcwd() ?: '.', 10)->successful;
    }

    public function supportsResume(): bool
    {
        return true;
    }

    public function start(AgentRequest $request): AgentRunResult
    {
        $command = ['claude', '-p', '--output-format', 'json'];
        if ($request->model !== null) {
            array_push($command, '--model', $request->model);
        }
        $command[] = $request->prompt;

        return $this->parse($this->processes->run($command, $request->cwd, 3600), $request->model);
    }

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult
    {
        $command = ['claude', '-p', '--resume', $sessionId, '--output-format', 'json', $request->prompt];

        return $this->parse($this->processes->run($command, $request->cwd, 3600), $request->model);
    }

    private function parse(ProcessResult $process, ?string $model): AgentRunResult
    {
        $data = json_decode($process->output, true);
        if (! is_array($data)) {
            return new AgentRunResult($process->successful, $process->output, $process->errorOutput, model: $model);
        }

        return new AgentRunResult(
            $process->successful && ! ($data['is_error'] ?? false),
            (string) ($data['result'] ?? ''),
            $process->errorOutput,
            $data['session_id'] ?? null,
            $model,
            $data['usage']['input_tokens'] ?? null,
            $data['usage']['output_tokens'] ?? null,
            isset($data['total_cost_usd']) ? (float) $data['total_cost_usd'] : null,
        );
    }
}
