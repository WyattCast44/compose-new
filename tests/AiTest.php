<?php

declare(strict_types=1);

use Compose\AI\AgentRegistry;
use Compose\AI\AiExecutor;
use Compose\Execution\ActionExecutor;
use Compose\Execution\GitRepository;
use Compose\Execution\Runner;
use Compose\Execution\RunOptions;
use Compose\Step;
use Compose\Testing\FakeAgent;

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
