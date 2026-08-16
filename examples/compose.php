<?php

declare(strict_types=1);

use Compose\Laravel\LaravelStep as Laravel;
use Compose\Step;
use Compose\StepConfig;

return compose('Setup Livewire in the Laravel application')
    ->cwd('./.build/laravel')
    ->step(
        name: 'Install Livewire',
        operations: function (Step $step): void {
            $step->composer()->require('livewire/livewire');
            $step->git()->commit('Install Livewire');
        },
    )
    ->step(
        name: 'Configure Livewire',
        operations: function (Step $step): void {
            $step->instruct(
                task: 'Configure Livewire using the latest documentation, available at https://laravel.com/docs/livewire',
            );
        },
    );

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
    ->step('Configure Application', operations: function (Step $step, Laravel $laravel): void {
        $step->files()->copy('.env.example', '.env');
        $laravel->env()->set('APP_NAME', 'Compose Example');
        $laravel->env()->set('APP_URL', 'http://compose-example.test');
        $laravel->config('app')->configSet('timezone', 'UTC');
        $laravel->artisan()->keyGenerate();
        $step->git()->commit('Configure application');
        $laravel->env()->has('APP_KEY');
    })
    ->step(
        name: 'Install Laravel Fortify',
        operations: function (Step $step, Laravel $laravel): void {
            $step->composer()->require('laravel/fortify');
            $step->process()->run(['php', 'artisan', 'fortify:install']);
            $laravel->artisan()->migrate(seed: true);
            $step->git()->commit('Install Laravel Fortify');
        },
    )
    ->step(
        name: 'Configure Laravel Fortify',
        operations: function (Step $step): void {
            $step->instruct(
                task: 'Configure Laravel Fortify using the latest documentation, available at https://laravel.com/docs/fortify',
            );
        },
    );
