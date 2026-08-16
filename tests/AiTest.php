<?php

declare(strict_types=1);

use Compose\AI\AgentRegistry;
use Compose\AI\AgentRequest;
use Compose\AI\AiExecutor;
use Compose\AI\ClaudeCodeDriver;
use Compose\AI\CodexDriver;
use Compose\Execution\ActionExecutor;
use Compose\Execution\GitRepository;
use Compose\Execution\ProcessResult;
use Compose\Execution\Runner;
use Compose\Execution\RunOptions;
use Compose\Step;
use Compose\Testing\FakeAgent;
use Compose\Testing\FakeProcessRunner;

it('runs AI instructions through a replaceable core driver', function (): void {
    $root = testDirectory('ai');
    runTestCommand(['git', 'init', '--quiet'], $root);
    runTestCommand(['git', 'config', 'user.email', 'compose@example.test'], $root);
    runTestCommand(['git', 'config', 'user.name', 'Compose Tests'], $root);
    file_put_contents($root.'/README.md', "# Test\n");
    runTestCommand(['git', 'add', 'README.md'], $root);
    runTestCommand(['git', 'commit', '--quiet', '-m', 'initial'], $root);

    $agent = new FakeAgent(name: 'fake');
    $git = new GitRepository;
    $ai = new AiExecutor(new AgentRegistry($agent), $git);
    $runner = new Runner(new ActionExecutor(ai: $ai), $git);
    $composition = compose('AI')->step('Instruction', function (Step $step): void {
        $step->instruct('Inspect the README', function ($instruction): void {
            $instruction->using('README.md')->allowChanges('README.md');
        });
    });

    $result = $runner->run($composition, new RunOptions($root, agent: 'fake'));

    expect($result->successful)->toBeTrue()
        ->and($agent->requests)->toHaveCount(1)
        ->and($agent->requests[0]->task)->toBe('Inspect the README')
        ->and($result->steps[0]->actions[0]->agent?->driver)->toBe('fake');
});

it('forwards readable Codex events while the agent runs', function (): void {
    $events = implode("\n", [
        json_encode(['type' => 'thread.started', 'thread_id' => 'thread-1'], JSON_THROW_ON_ERROR),
        json_encode(['type' => 'item.started', 'item' => ['type' => 'command_execution', 'command' => 'php -v']], JSON_THROW_ON_ERROR),
        json_encode(['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'aggregated_output' => 'PHP 8.4']], JSON_THROW_ON_ERROR),
        json_encode(['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Finished the update.']], JSON_THROW_ON_ERROR),
    ]);
    $processes = new FakeProcessRunner([
        'codex exec *' => new ProcessResult(['codex'], 0, $events),
    ]);
    $updates = [];
    $driver = new CodexDriver($processes);

    $result = $driver->start(new AgentRequest('Update', 'Update', getcwd() ?: '.', onOutput: static function (string $message) use (&$updates): void {
        $updates[] = $message;
    }));

    expect($result->output)->toBe('Finished the update.')
        ->and($result->sessionId)->toBe('thread-1')
        ->and($updates)->toBe(['$ php -v', 'PHP 8.4', 'Finished the update.']);
});

it('forwards readable Claude events while the agent runs', function (): void {
    $events = implode("\n", [
        json_encode(['type' => 'assistant', 'message' => ['content' => [
            ['type' => 'text', 'text' => 'Checking the file.'],
            ['type' => 'tool_use', 'name' => 'Read', 'input' => ['file_path' => 'README.md']],
        ]]], JSON_THROW_ON_ERROR),
        json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'result' => 'Finished the update.',
            'session_id' => 'session-1',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ], JSON_THROW_ON_ERROR),
    ]);
    $processes = new FakeProcessRunner([
        'claude -p *' => new ProcessResult(['claude'], 0, $events),
    ]);
    $updates = [];
    $driver = new ClaudeCodeDriver($processes);

    $result = $driver->start(new AgentRequest('Update', 'Update', getcwd() ?: '.', onOutput: static function (string $message) use (&$updates): void {
        $updates[] = $message;
    }));

    expect($result->output)->toBe('Finished the update.')
        ->and($result->sessionId)->toBe('session-1')
        ->and($updates)->toBe(['Checking the file.', 'Read: README.md'])
        ->and($processes->executed[0]['command'])->toContain('stream-json');
});
