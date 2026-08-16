<?php

declare(strict_types=1);

use Compose\Exception\DefinitionException;
use Compose\Laravel\LaravelStep;
use Compose\Step;
use Compose\StepConfig;

it('compiles core steps and keeps composer in the core API', function (): void {
    $composition = compose('Core API')
        ->step('Install', function (Step $step): void {
            $step->composer()->install('--no-interaction');
            $step->node()->install()->optional();
        }, function (StepConfig $step): void {
            $step->timeout(seconds: 30);
        });

    expect($composition->steps())->toHaveCount(1)
        ->and($composition->steps()[0]->actions[0]->payload['command'])->toBe(['composer', 'install', '--no-interaction'])
        ->and($composition->steps()[0]->actions[0]->policy->timeout)->toBe(30.0)
        ->and($composition->steps()[0]->actions[1]->policy->optional)->toBeTrue()
        ->and($composition->steps()[0]->jsonSerialize())->not->toHaveKey('description');
});

it('constructs step extensions by convention', function (): void {
    $composition = compose('Laravel')
        ->step('Configure', function (LaravelStep $laravel, Step $core): void {
            $laravel->artisan()->keyGenerate();
            $laravel->env()->set('APP_NAME', 'Compose App');
            $core->composer()->require('laravel/framework');
        });

    expect(array_column($composition->steps()[0]->actions, 'type'))
        ->toBe(['process:run', 'edit:env', 'process:run']);
});

it('requires git clone definitions to be finalized', function (): void {
    expect(fn () => compose('Clone')->step('Clone', function (Step $step): void {
        $step->git()->clone('https://example.com/repository.git');
    }))->toThrow(DefinitionException::class, 'must end with into() or here()');
});

it('rejects paths that escape the invocation directory', function (): void {
    expect(fn () => compose('Unsafe')->cwd('../outside'))->toThrow(DefinitionException::class);
});
