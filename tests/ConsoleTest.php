<?php

declare(strict_types=1);

use Compose\AI\AgentRegistry;
use Compose\AI\AgentRunResult;
use Compose\AI\AiExecutor;
use Compose\Console\Application;
use Compose\Console\RunCommand;
use Compose\Execution\ActionExecutor;
use Compose\Execution\GitRepository;
use Compose\Execution\Runner;
use Compose\Testing\FakeAgent;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Tester\ApplicationTester;

it('prints the failed step action and underlying reason', function (): void {
    $root = testDirectory('console');
    $composition = $root.'/compose.php';
    file_put_contents($composition, <<<'PHP'
<?php

use Compose\Step;

return compose('Failure output')
    ->cwd('missing')
    ->step('Configure', function (Step $step): void {
        $step->files()->create('file.txt', 'contents');
    });
PHP);

    $application = new Application;
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);
    withinDirectory(dirname($composition), static function () use ($tester, $composition): void {
        $tester->run(['command' => 'run', 'file' => $composition, '--yes' => true], ['interactive' => false]);
    });

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Step: Configure')
        ->and($tester->getDisplay())->toContain('Action: create file.txt')
        ->and($tester->getDisplay())->toContain('Reason: Step directory does not exist: missing');
});

it('reports step and action progress while a composition runs', function (): void {
    $composition = consoleProgressComposition();
    $application = new Application;
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);
    withinDirectory(dirname($composition), static function () use ($tester, $composition): void {
        $tester->run(['command' => 'run', 'file' => $composition, '--yes' => true], [
            'interactive' => false,
            'decorated' => true,
        ]);
    });

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Running 1 step…')
        ->and($tester->getDisplay())->toContain('Step 1/1: Verify')
        ->and($tester->getDisplay())->toContain('Action 1/2: first check')
        ->and($tester->getDisplay())->toContain('Action 2/2: second check')
        ->and($tester->getDisplay())->toContain('✓ Completed')
        ->and($tester->getDisplay())->toContain('✓ Step completed');
});

it('keeps JSON run output free of progress messages', function (): void {
    $composition = consoleProgressComposition();
    $application = new Application;
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);
    withinDirectory(dirname($composition), static function () use ($tester, $composition): void {
        $tester->run([
            'command' => 'run',
            'file' => $composition,
            '--yes' => true,
            '--json' => true,
        ], ['interactive' => false]);
    });

    $result = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($tester->getStatusCode())->toBe(0)
        ->and($result['successful'])->toBeTrue()
        ->and($tester->getDisplay())->not->toContain('Running 1 step');
});

it('prints AI updates before the instruction completes', function (): void {
    $root = testDirectory('console-ai-output');
    runTestCommand(['git', 'init', '--quiet'], $root);
    runTestCommand(['git', 'config', 'user.email', 'compose@example.test'], $root);
    runTestCommand(['git', 'config', 'user.name', 'Compose Tests'], $root);
    file_put_contents($root.'/README.md', "# Test\n");
    file_put_contents($root.'/compose.php', <<<'PHP'
<?php

use Compose\Step;

return compose('AI output')->step('Instruction', function (Step $step): void {
    $step->instruct('Inspect the README', function ($instruction): void {
        $instruction->allowChanges('README.md');
    });
});
PHP);
    runTestCommand(['git', 'add', 'README.md', 'compose.php'], $root);
    runTestCommand(['git', 'commit', '--quiet', '-m', 'initial'], $root);

    $agent = new FakeAgent(
        new AgentRunResult(true, 'Finished the update.'),
        name: 'fake',
        updates: ['Checking README.md…', 'No changes needed.'],
    );
    $git = new GitRepository;
    $runner = new Runner(new ActionExecutor(ai: new AiExecutor(new AgentRegistry($agent), $git)), $git);
    $application = new SymfonyApplication;
    $application->addCommand(new RunCommand(runner: $runner));
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);
    withinDirectory($root, static function () use ($tester, $root): void {
        $tester->run([
            'command' => 'run',
            'file' => $root.'/compose.php',
            '--yes' => true,
            '--agent' => 'fake',
        ], ['interactive' => false]);
    });

    $display = $tester->getDisplay();
    $updatePosition = strpos($display, 'Checking README.md…');
    $completedPosition = strpos($display, '✓ Completed');
    if ($updatePosition === false || $completedPosition === false) {
        throw new RuntimeException('Expected streamed AI output and action completion output.');
    }

    expect($tester->getStatusCode())->toBe(0)
        ->and($display)->toContain('Checking README.md…')
        ->and($display)->toContain('No changes needed.')
        ->and($updatePosition)->toBeLessThan($completedPosition);
});

function consoleProgressComposition(): string
{
    $root = testDirectory('console-progress');
    $composition = $root.'/compose.php';
    file_put_contents($composition, <<<'PHP'
<?php

use Compose\Step;

return compose('Progress output')
    ->step('Verify', function (Step $step): void {
        $step->verify()->callback(static fn (): bool => true, 'first check');
        $step->verify()->callback(static fn (): bool => true, 'second check');
    });
PHP);

    return $composition;
}
