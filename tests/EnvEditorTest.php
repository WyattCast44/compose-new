<?php

declare(strict_types=1);

use Compose\Execution\Runner;
use Compose\Execution\RunOptions;
use Compose\Laravel\LaravelStep;

it('queues env editor operations including has', function (): void {
    $composition = compose('Env editor')
        ->step('Configure', function (LaravelStep $laravel): void {
            $laravel->env()->set('APP_NAME', 'Compose App');
            $laravel->env()->has('APP_KEY');
            $laravel->env()->remove('OLD_KEY');
            $laravel->env()->comment('APP_DEBUG');
            $laravel->env()->uncomment('APP_DEBUG');
            $laravel->env()->section('Custom', ['FOO' => 'bar']);
            $laravel->env('.env.testing')->has('APP_ENV');
        });

    $actions = $composition->steps()[0]->actions;

    expect(array_column($actions, 'type'))->toBe(array_fill(0, 7, 'edit:env'))
        ->and(array_map(static fn ($action): string => $action->payload['operation'], $actions))
        ->toBe(['set', 'has', 'remove', 'comment', 'uncomment', 'section', 'has'])
        ->and($actions[1]->payload)->toBe([
            'path' => '.env',
            'operation' => 'has',
            'arguments' => ['key' => 'APP_KEY'],
        ])
        ->and($actions[6]->payload)->toBe([
            'path' => '.env.testing',
            'operation' => 'has',
            'arguments' => ['key' => 'APP_ENV'],
        ]);
});

it('confirms existing env keys and leaves the file unchanged', function (): void {
    $root = testDirectory('env-has');
    $original = "APP_NAME=Old\n# APP_DEBUG=true\n";
    file_put_contents($root.'/.env', $original);

    $composition = compose('Env has')
        ->step('Check', function (LaravelStep $laravel): void {
            $laravel->env()->has('APP_NAME');
            $laravel->env()->has('APP_DEBUG');
        });

    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeTrue()
        ->and(file_get_contents($root.'/.env'))->toBe($original);
});

it('fails when an env key is missing', function (): void {
    $root = testDirectory('env-missing');
    file_put_contents($root.'/.env', "APP_NAME=Compose\n");

    $composition = compose('Env missing')
        ->step('Check', function (LaravelStep $laravel): void {
            $laravel->env()->has('APP_KEY');
        });

    $result = (new Runner)->run($composition, new RunOptions($root));

    expect($result->successful)->toBeFalse()
        ->and($result->steps[0]->actions[0]->errorOutput)->toBe('Environment key APP_KEY does not exist.');
});
