<?php

declare(strict_types=1);

use Compose\Laravel\LaravelStep as Artisan;
use Compose\Step;
use Compose\StepConfig;

return compose('Example Laravel application')
    ->step('Clone Laravel', operations: function (Step $step): void {
        $step->git()->clone('https://github.com/laravel/laravel.git')->into('./.build/laravel');
    })
    ->cwd('./.build/laravel')
    ->step(
        name: 'Install Dependencies',
        configure: fn (StepConfig $step) => $step->timeout(minutes: 10),
        operations: function (Step $step): void {
            $step->composer()->install('--no-interaction');
            $step->node()->install();
            $step->git()->commit('Install dependencies');
        },
    )
    ->step('Configure', operations: function (Step $step, Artisan $artisan): void {
        $step->files()->copy('.env.example', '.env');
        $artisan->env()->set('APP_NAME', 'Compose Example');
        $artisan->env()->set('APP_URL', 'http://compose-example.test');
        $artisan->config('app')->configSet('timezone', 'UTC');
        $artisan->artisan()->keyGenerate();
        $step->git()->commit('Configure application');
        $artisan->env()->has('APP_KEY');
    });
