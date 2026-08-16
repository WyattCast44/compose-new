<?php

declare(strict_types=1);

use Compose\Composition;
use Compose\Laravel\LaravelStep as Laravel;
use Compose\Recipe;
use Compose\Step;
use Compose\StepConfig;

/**
 * A complete starter-kit recipe using Compose's current API.
 *
 * The recipe keeps framework installation, authentication, configuration, and
 * verification deterministic. AI is reserved for the application-specific UI
 * and may only change an explicit set of presentation files.
 */
final class LivewireStarterKit extends Recipe
{
    public function __construct(
        private readonly string $applicationName,
        private readonly string $applicationUrl,
        private readonly string $timezone = 'UTC',
    ) {}

    public function compose(Composition $composition): void
    {
        $composition
            ->step(
                name: 'Install application dependencies',
                operations: function (Step $step): void {
                    $step->composer()->install('--no-interaction');
                    $step->composer()->require(
                        'laravel/fortify:^1.0',
                        'livewire/livewire:^4.0',
                    );
                    $step->node()->install();
                    $step->git()->commit('Install Livewire starter-kit dependencies');
                },
                configure: fn (StepConfig $step) => $step
                    ->timeout(minutes: 10)
                    ->retry(times: 1, delay: 2),
            )
            ->step('Install authentication', function (Step $step): void {
                // Fortify owns the security-sensitive authentication backend.
                $step->process()->run(['php', 'artisan', 'fortify:install']);
                $step->git()->commit('Install Fortify authentication backend');
            })
            ->step('Configure application', function (Step $step, Laravel $laravel): void {
                $step->files()->copy('.env.example', '.env');
                $step->files()->create('database/database.sqlite', '', overwrite: true);

                $laravel->env()->set('APP_NAME', $this->applicationName);
                $laravel->env()->set('APP_URL', $this->applicationUrl);
                $laravel->env()->set('DB_CONNECTION', 'sqlite');
                $laravel->env()->remove('DB_HOST');
                $laravel->env()->remove('DB_PORT');
                $laravel->env()->remove('DB_DATABASE');
                $laravel->env()->remove('DB_USERNAME');
                $laravel->env()->remove('DB_PASSWORD');

                $laravel->config('app')->configSet('timezone', $this->timezone);
                $laravel->artisan()->keyGenerate();
                $laravel->artisan()->migrate();
                $laravel->env()->has('APP_KEY');

                $step->git()->commit('Configure starter application');
            })
            ->step('Customize the application experience', function (Step $step): void {
                $step->instruct(
                    task: <<<PROMPT
                    Create the user-facing experience for {$this->applicationName}, a polished
                    Laravel application using the already-installed Livewire and Tailwind stack.

                    Provide Fortify-compatible login, registration, forgot-password,
                    reset-password, email-verification, and confirm-password views. Create a
                    responsive guest welcome page and an authenticated dashboard at
                    resources/views/dashboard.blade.php. The visual direction should feel
                    calm, professional, accessible, and distinct from Laravel's default screen.
                    PROMPT,
                    configure: function ($instruction): void {
                        $instruction
                            ->using(
                                'composer.json',
                                'package.json',
                                'config/fortify.php',
                                'app/Providers/FortifyServiceProvider.php',
                                'routes/web.php',
                                'resources/css/app.css',
                            )
                            ->allowChanges(
                                'app/Livewire/**',
                                'app/Providers/FortifyServiceProvider.php',
                                'resources/css/app.css',
                                'resources/js/app.js',
                                'resources/views/**',
                                'routes/web.php',
                                'tests/Feature/**',
                            )
                            ->rules(
                                'Use Fortify for authentication; do not reimplement authentication logic.',
                                'Do not change Fortify actions, the User model, migrations, configuration, or dependencies.',
                                'Protect the dashboard with both auth and verified middleware.',
                                'Use the installed Tailwind and Vite toolchain; do not load assets from a CDN.',
                                'Meet WCAG AA expectations for semantics, focus states, contrast, and validation errors.',
                                'Add focused feature tests for guest access, authentication views, and dashboard authorization.',
                            )
                            ->review()
                            ->bake();
                    },
                );
            })
            ->step(
                name: 'Verify the generated starter kit',
                operations: function (Step $step): void {
                    $step->verify()->fileExists('resources/views/auth/login.blade.php');
                    $step->verify()->fileExists('resources/views/dashboard.blade.php');
                    $step->verify()->command(['php', 'artisan', 'route:list']);
                    $step->verify()->command(['php', 'artisan', 'test', '--compact']);
                    $step->node()->run('build');
                    $step->git()->commit('Generate customized Livewire starter kit');
                },
                configure: fn (StepConfig $step) => $step->timeout(minutes: 10),
            );
    }
}

return compose('Create a customized Laravel starter application')
    ->step('Create Laravel application', function (Step $step): void {
        $step->git()
            ->clone('https://github.com/laravel/laravel.git', branch: '13.x')
            ->into('.build/acme');
    })
    ->cwd('.build/acme')
    ->extend(new LivewireStarterKit(
        applicationName: 'Acme',
        applicationUrl: 'http://acme.test',
        timezone: 'America/Los_Angeles',
    ));

/*
|--------------------------------------------------------------------------
| Potential future recipe API
|--------------------------------------------------------------------------
|
| A starter-kit product will eventually need typed requirements, conditional
| recipes, compatibility resolution, and a portable lockfile. The exact API is
| intentionally speculative, but the desired authoring experience could be:
|
| return compose('Create a customized Laravel application')
|     ->requirements(function (Requirements $requirements): void {
|         $requirements->choice('frontend', ['livewire', 'react', 'vue', 'svelte'])
|             ->prompt('Which frontend stack do you prefer?');
|         $requirements->features('authentication', [
|             'registration',
|             'password-reset',
|             'email-verification',
|             'two-factor-authentication',
|         ]);
|         $requirements->boolean('teams')->default(false);
|         $requirements->choice('app-layout', ['sidebar', 'header']);
|         $requirements->choice('auth-layout', ['simple', 'card', 'split']);
|         $requirements->text('visual-direction')
|             ->describe('Brand, audience, tone, colors, and accessibility needs.');
|     })
|     ->resolveWithAi()
|     ->extend(LaravelApplication::class)
|     ->extend(Authentication::class, when: feature('authentication'))
|     ->extend(Teams::class, when: enabled('teams'))
|     ->extend(Frontend::from(answer('frontend')))
|     ->customizeWithAi(
|         brief: answer('visual-direction'),
|         allowChanges: ['resources/**'],
|         verify: ['composer test', 'npm run build'],
|     )
|     ->lock('.compose/compose.lock');
|
| The lockfile should record resolved recipe versions, the Laravel revision,
| package locks, normalized answers, tool versions, and approved AI patch hashes
| so the same starter can be reproduced without calling an agent again.
|
*/
