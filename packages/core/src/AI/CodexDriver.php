<?php

declare(strict_types=1);

namespace Compose\AI;

use Compose\Execution\ProcessResult;
use Compose\Execution\ProcessRunner;
use Compose\Execution\StreamingProcessRunner;
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

        return $this->parse($this->run($command, $request), $request->model);
    }

    public function resume(string $sessionId, AgentRequest $request): AgentRunResult
    {
        $command = ['codex', 'exec', 'resume', '--json', $sessionId, '-'];

        return $this->parse($this->run($command, $request), $request->model);
    }

    /** @param list<string> $command */
    private function run(array $command, AgentRequest $request): ProcessResult
    {
        if ($request->onOutput === null) {
            return $this->processes->run($command, $request->cwd, 3600, $request->prompt);
        }

        if (! $this->processes instanceof StreamingProcessRunner) {
            $result = $this->processes->run($command, $request->cwd, 3600, $request->prompt);
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
            $request->prompt,
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
        if (! is_array($event)) {
            return;
        }

        $item = is_array($event['item'] ?? null) ? $event['item'] : [];
        $itemType = $item['type'] ?? null;
        $eventType = $event['type'] ?? null;

        if ($eventType === 'item.started') {
            $summary = match ($itemType) {
                'command_execution' => isset($item['command']) ? '$ '.$item['command'] : 'Running a command…',
                'mcp_tool_call' => 'Calling '.($item['server'] ?? 'MCP').'.'.($item['tool'] ?? 'tool').'…',
                'web_search' => isset($item['query']) ? 'Searching: '.$item['query'] : 'Searching the web…',
                default => null,
            };
            if ($summary !== null) {
                ($request->onOutput)($summary);
            }

            return;
        }

        if ($eventType !== 'item.completed') {
            return;
        }

        $message = match ($itemType) {
            'agent_message', 'reasoning' => $item['text'] ?? null,
            'command_execution' => $item['aggregated_output'] ?? null,
            'file_change' => 'Updated files.',
            default => null,
        };
        if (is_string($message) && trim($message) !== '') {
            ($request->onOutput)($message);
        }
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
            $item = is_array($event['item'] ?? null) ? $event['item'] : [];
            if (($item['type'] ?? null) === 'agent_message') {
                $output = $item['text'] ?? $output;
            } elseif (isset($event['message'])) {
                $output = $event['message'];
            }
            $input = $event['usage']['input_tokens'] ?? $input;
            $tokens = $event['usage']['output_tokens'] ?? $tokens;
        }

        return new AgentRunResult($process->successful, (string) $output, $process->errorOutput, $session, $model, $input, $tokens);
    }
}
