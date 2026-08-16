# Compose

Compose is a greenfield PHP library for declaring, reviewing, and safely running project setup workflows. A composition is ordinary PHP: it compiles into an immutable plan before anything runs, asks for approval once, and executes each step with retries, results, and Git-backed rollback.

## Packages

- `compose/compose` is the core. It includes `composer()`, Node, Git, files, processes, structured editors, verification, AI instructions, planning, execution, and the CLI.
- `compose/laravel` adds `LaravelStep`, Artisan, Laravel config, and `.env` helpers.

`composer()` deliberately belongs to the core API.

## A composition

Create `compose.php` in the project you want to configure:

```php
<?php

use Compose\AI\Agent;
use Compose\Laravel\LaravelStep;
use Compose\Step;
use Compose\StepConfig;

return compose('My application')
    ->step(
        'Install dependencies',
        fn (StepConfig $step) => $step->timeout(minutes: 10)->retry(1, delay: 2),
        function (Step $step): void {
            $step->composer()->install('--no-interaction');
            $step->node()->install();
            $step->git()->commit('Install dependencies');
        },
    )
    ->step('Configure Laravel', null, function (LaravelStep $laravel, Step $core): void {
        $laravel->env()->set('APP_NAME', 'My Application');
        $laravel->config('app')->configSet('timezone', 'UTC');
        $laravel->artisan()->keyGenerate();

        // Core APIs remain available beside Laravel APIs.
        $core->composer()->run('test')->optional();
    })
    ->step('Polish the README', null, function (Step $step): void {
        $step->instruct('Add a concise local-development section', function ($instruction): void {
            $instruction
                ->using('README.md', 'composer.json')
                ->allowChanges('README.md')
                ->rules('Keep existing installation instructions')
                ->agent(Agent::Codex)
                ->review();
        });
    });
```

A step closure may request the core `Step` and any installed `Step` subclass. Compose constructs each concrete type over the same step definition and compiles the calls immediately.

## CLI

```shell
vendor/bin/compose plan compose.php
vendor/bin/compose run compose.php
vendor/bin/compose run compose.php --yes
vendor/bin/compose run compose.php --yes --json
```

Interactive and ordinary terminal runs report each step and action as it starts, followed by completion, retry, warning, rollback, or failure status and elapsed time. AI instructions also stream readable agent messages, tool calls, and command output as the agent works. Add `-v` to include captured output from other commands. `--json` suppresses all progress rendering so stdout remains valid JSON.

AI defaults can be selected with `--agent=codex|claude` and `--model=...`. Project defaults live in `.compose/config.json`; user defaults live in `~/.config/compose/config.json`. CLI options take precedence. `--accept-ai` accepts review-gated AI work in non-interactive runs.

## Core API

Each queued action returns a `PendingAction`, so it can be marked `optional()`, given a `timeout()`, or assigned `retry()` behavior.

```php
$step->composer()->require('vendor/package');
$step->node()->addDev('vite');
$step->git()->clone($repository)->into('project');
$step->git()->commit('Install dependencies'); // stages all changes first
$step->files()->create('README.md', '# App');
$step->process()->run(['php', '-v']);
$step->process()->shell('explicit shell syntax');
$step->text('README.md')->replace('old', 'new', expected: 1);
$step->json('composer.json')->set('extra.branch-alias.dev-main', '1.x-dev');
$step->php('config/app.php')->configSet('timezone', 'UTC');
$step->verify()->fileExists('composer.json');
```

Node selects pnpm, Yarn, Bun, or npm from the lockfile unless a `NodeManager` is supplied. PHP edits use an AST. Text replacements require explicit match counts, and JSON/config/environment edits operate on exact keys.

Reusable workflows extend `Compose\Recipe`. Recipes are configured as objects, can be applied repeatedly, restore the caller's working directory after compilation, and reject active extension cycles.

## Safety and execution

- All action paths are relative to the step directory. Absolute paths, `..` escapes, and symlink escapes are rejected.
- File writes are atomic. Recursive deletion never follows symlinks.
- `process()->run()` uses argument arrays; shell execution is a separate, visibly risky action.
- Git clone stages into a temporary sibling and only moves into place after success. `here()` requires an empty target.
- A repository that predates the run must be clean when Compose first encounters it. Dirtiness created by successful actions is then accepted by later steps in that same run. Repositories created by `git:init` or `git:clone` are registered as run-owned immediately, so their composition-created changes are not mistaken for pre-existing dirtiness.
- Compose snapshots the worktree and index for every step and action once a committed repository is available. A failed required action restores the whole step; failed retries and optional actions restore only that action.
- Git reference mutations are reported as non-reversible. Compose will not pretend it can roll back a changed `HEAD`.
- Plans and results are JSON serializable. Lifecycle events are exposed for custom output and telemetry.

AI instructions require a Git repository with a commit. Compose validates that the agent did not change `HEAD`, restricts changed paths to `allowChanges()` globs, and supports review, rollback, and steering. Codex and Claude Code are built-in drivers; the driver contract and fakes are public for testing and additional integrations.

## Development

```shell
composer install
composer check
```

The test suite covers composition compilation, typed step extensions, structured editors, path safety, retries, optional actions, actual Git rollback, and replaceable AI drivers.
