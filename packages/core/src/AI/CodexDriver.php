<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Execution\ProcessResult;
use Compose\Execution\ProcessRunner;
use Compose\Execution\SymfonyProcessRunner;

final readonly class CodexDriver implements AgentDriver
{
    public function __construct(private ProcessRunner $processes = new SymfonyProcessRunner) {}

    public function id(): string
    {
        return Agent::Codex->value;
    }

    public function available(): bool
    {
        return $this->processes->run(['codex', '--version'], getcwd() ?: '.', 10)->successful;
    }

    public function supportsResume(): bool
    {
        return true;
    }

    public function start(AgentRequest $request): AgentRunResult
    {
        $command = ['codex', 'exec', '--json', '--sandbox', 'workspace-write', '--cd', $request->cwd];
        if ($request->model !== null) {
            array_push($command, '--model', $request->model);
        }
        $command[] = '-';

        return $this->parse($this->processes->run($command, $request->cwd, 3600, $request->prompt), $request->model);
    }

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult
    {
        $command = ['codex', 'exec', 'resume', '--json', $sessionId, '-'];

        return $this->parse($this->processes->run($command, $request->cwd, 3600, $request->prompt), $request->model);
    }

    private function parse(ProcessResult $process, ?string $model): AgentRunResult
    {
        $session = null;
        $output = '';
        $input = $tokens = null;

        foreach (preg_split('/\R/', trim($process->output)) ?: [] as $line) {
            $event = json_decode($line, true);
            if (! is_array($event)) {
                continue;
            }
            $session ??= $event['thread_id'] ?? $event['session_id'] ?? null;
            $output = $event['item']['text'] ?? $event['message'] ?? $output;
            $input = $event['usage']['input_tokens'] ?? $input;
            $tokens = $event['usage']['output_tokens'] ?? $tokens;
        }

        return new AgentRunResult($process->successful, (string) $output, $process->errorOutput, $session, $model, $input, $tokens);
    }
}
