<?php

declare(strict_types=1);

use Compose\Execution\ActionExecutor;
use Compose\Execution\ProcessResult;
use Compose\Execution\Runner;
use Compose\Execution\RunOptions;
use Compose\Step;
use Compose\Testing\FakeProcessRunner;

it('retries actions and continues after optional failures', function (): void {
    $root = testDirectory('retry');
    $processes = new FakeProcessRunner([
        'tool flaky' => [new ProcessResult([], 1, errorOutput: 'first'), new ProcessResult([], 0, 'second')],
        'tool optional' => new ProcessResult([], 1, errorOutput: 'ignored'),
    ]);
    $composition = compose('Retry')->step('Commands', function (Step $step): void {
        $step->process()->run(['tool', 'flaky'])->retry(1);
        $step->process()->run(['tool', 'optional'])->optional();
    });

    $result = (new Runner(new ActionExecutor(processes: $processes)))->run($composition, new RunOptions($root));

    expect($result->successful)->toBeTrue()
        ->and($result->steps[0]->actions[0]->attempts)->toBe(2)
        ->and($result->steps[0]->actions[1]->warning)->toBeTrue()
        ->and($processes->executed)->toHaveCount(3);
});

it('rolls back the entire failed step in a clean Git worktree', function (): void {
    $root = testDirectory('rollback');
    runTestCommand(['git', 'init', '--quiet'], $root);
    runTestCommand(['git', 'config', 'user.email', 'compose@example.test'], $root);
    runTestCommand(['git', 'config', 'user.name', 'Compose Tests'], $root);
    file_put_contents($root.'/state.txt', 'before');
    runTestCommand(['git', 'add', 'state.txt'], $root);
    runTestCommand(['git', 'commit', '--quiet', '-m', 'initial'], $root);

    $composition = compose('Rollback')->step('Atomic step', function (Step $step): void {
        $step->files()->create('state.txt', 'after', overwrite: true);
        $step->files()->create('new.txt', 'temporary');
        $step->verify()->callback(static fn (): bool => false);
    });
    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeFalse()
        ->and($result->steps[0]->rolledBack)->toBeTrue()
        ->and(file_get_contents($root.'/state.txt'))->toBe('before')
        ->and(file_exists($root.'/new.txt'))->toBeFalse()
        ->and(trim(runTestCommand(['git', 'status', '--porcelain'], $root)))->toBe('');
});

it('allows composition-created dirtiness across later steps in the same run', function (): void {
    $root = testDirectory('cross-step');
    initializeTestRepository($root);

    $composition = compose('Cross-step changes')
        ->step('Generate', function (Step $step): void {
            $step->files()->create('generated.txt', 'created by Compose');
        })
        ->step('Consume', function (Step $step): void {
            $step->verify()->fileExists('generated.txt');
        });

    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeTrue()
        ->and(trim(runTestCommand(['git', 'status', '--short'], $root)))->toBe('?? generated.txt');
});

it('tracks a cloned repository even when the composition dirties it before the next step', function (): void {
    $source = testDirectory('clone-source');
    initializeTestRepository($source);
    $root = testDirectory('clone-target');

    $composition = compose('Clone lifecycle')
        ->step('Clone and generate', function (Step $step) use ($source): void {
            $step->git()->clone($source)->into('build/app');
            $step->files()->create('build/app/generated.txt', 'created after clone');
        })
        ->cwd('build/app')
        ->step('Consume', function (Step $step): void {
            $step->verify()->fileExists('generated.txt');
        });

    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeTrue()
        ->and(trim(runTestCommand(['git', 'status', '--short'], $root.'/build/app')))->toBe('?? generated.txt');
});

it('stages all changes when committing', function (): void {
    $root = testDirectory('commit');
    initializeTestRepository($root);

    $composition = compose('Commit')
        ->step('Generate and commit', function (Step $step): void {
            $step->files()->create('generated.txt', 'committed by Compose');
            $step->git()->commit('Generate file');
        });

    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeTrue()
        ->and(trim(runTestCommand(['git', 'status', '--short'], $root)))->toBe('')
        ->and(trim(runTestCommand(['git', 'log', '-1', '--format=%s'], $root)))->toBe('Generate file')
        ->and(runTestCommand(['git', 'show', 'HEAD:generated.txt'], $root))->toBe('committed by Compose');
});

function initializeTestRepository(string $root): void
{
    runTestCommand(['git', 'init', '--quiet'], $root);
    runTestCommand(['git', 'config', 'user.email', 'compose@example.test'], $root);
    runTestCommand(['git', 'config', 'user.name', 'Compose Tests'], $root);
    file_put_contents($root.'/README.md', 'initial');
    runTestCommand(['git', 'add', 'README.md'], $root);
    runTestCommand(['git', 'commit', '--quiet', '-m', 'initial'], $root);
}
