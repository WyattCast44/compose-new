<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Execution\ProcessResult;
use Compose\Execution\ProcessRunner;
use Compose\Execution\StreamingProcessRunner;
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
        $command = ['claude', '-p', '--output-format', 'stream-json', '--verbose'];
        if ($request->model !== null) {
            array_push($command, '--model', $request->model);
        }
        $command[] = $request->prompt;

        return $this->parse($this->run($command, $request), $request->model);
    }

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult
    {
        $command = ['claude', '-p', '--resume', $sessionId, '--output-format', 'stream-json', '--verbose', $request->prompt];

        return $this->parse($this->run($command, $request), $request->model);
    }

    /** @param list<string> $command */
    private function run(array $command, AgentRequest $request): ProcessResult
    {
        if ($request->onOutput === null) {
            return $this->processes->run($command, $request->cwd, 3600);
        }

        if (! $this->processes instanceof StreamingProcessRunner) {
            $result = $this->processes->run($command, $request->cwd, 3600);
            $this->emitJsonLines($result->output, $request);

            return $result;
        }

        $buffer = '';
        $result = $this->processes->runStreaming(
            $command,
            $request->cwd,
            function (string $type, string $chunk) use (&$buffer, $request): void {
                if ($type !== 'out') {
                    return;
                }

                $buffer .= $chunk;
                while (($newline = strpos($buffer, "\n")) !== false) {
                    $this->emitEvent(substr($buffer, 0, $newline), $request);
                    $buffer = substr($buffer, $newline + 1);
                }
            },
            3600,
        );
        $this->emitEvent($buffer, $request);

        return $result;
    }

    private function emitJsonLines(string $output, AgentRequest $request): void
    {
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $this->emitEvent($line, $request);
        }
    }

    private function emitEvent(string $line, AgentRequest $request): void
    {
        if ($request->onOutput === null || trim($line) === '') {
            return;
        }

        $event = json_decode($line, true);
        if (! is_array($event) || ($event['type'] ?? null) !== 'assistant') {
            return;
        }

        foreach ($event['message']['content'] ?? [] as $content) {
            if (! is_array($content)) {
                continue;
            }
            if (($content['type'] ?? null) === 'text' && is_string($content['text'] ?? null) && trim($content['text']) !== '') {
                ($request->onOutput)($content['text']);
            }
            if (($content['type'] ?? null) === 'tool_use') {
                ($request->onOutput)($this->toolSummary($content));
            }
        }
    }

    /** @param array<string, mixed> $content */
    private function toolSummary(array $content): string
    {
        $name = (string) ($content['name'] ?? 'tool');
        $input = is_array($content['input'] ?? null) ? $content['input'] : [];
        $detail = $input['command'] ?? $input['file_path'] ?? $input['pattern'] ?? null;

        return is_string($detail) && $detail !== '' ? "{$name}: {$detail}" : "Using {$name}…";
    }

    private function parse(ProcessResult $process, ?string $model): AgentRunResult
    {
        $data = null;
        foreach (preg_split('/\R/', trim($process->output)) ?: [] as $line) {
            $event = json_decode($line, true);
            if (is_array($event) && ($event['type'] ?? null) === 'result') {
                $data = $event;
            }
        }
        if ($data === null) {
            $decoded = json_decode($process->output, true);
            $data = is_array($decoded) ? $decoded : null;
        }
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
