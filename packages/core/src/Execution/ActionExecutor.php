<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\AI\AiExecutor;
use Compose\Definition\ActionDefinition;
use Compose\Exception\ComposeException;
use Compose\Tool\NodeManager;
use Throwable;

final readonly class ActionExecutor
{
    public function __construct(
        private ProcessRunner $processes = new SymfonyProcessRunner,
        private FileEditor $files = new FileEditor,
        private TextFileEditor $text = new TextFileEditor,
        private JsonFileEditor $json = new JsonFileEditor,
        private PhpFileEditor $php = new PhpFileEditor,
        private DotenvFileEditor $dotenv = new DotenvFileEditor,
        private GitCloner $cloner = new GitCloner,
        private AiExecutor $ai = new AiExecutor,
        private PathResolver $paths = new PathResolver,
    ) {}

    public function execute(ActionDefinition $action, string $cwd, ?GitCheckpoint $checkpoint, RunOptions $options, ?float $timeout = null): ActionResult
    {
        if ($action->type === 'ai:instruct') {
            if ($checkpoint === null) {
                return new ActionResult($action, false, errorOutput: 'AI instructions require a Git repository with at least one commit.', exitCode: 1);
            }

            return $this->ai->execute($action, $cwd, $checkpoint, $options);
        }

        $started = microtime(true);

        try {
            $result = match ($action->type) {
                'process:run' => $this->process($action, $cwd, $timeout),
                'process:shell' => $this->shell($action, $cwd, $timeout),
                'node:install', 'node:add', 'node:add-dev', 'node:remove', 'node:run', 'node:exec' => $this->node($action, $cwd, $timeout),
                'git:clone' => $this->clone($action, $cwd),
                'git:init' => $this->process($action, $cwd, $timeout),
                'git:commit' => $this->commit($action, $cwd, $timeout),
                'file:create' => $this->value($action, $this->files->create($cwd, $action->payload['path'], $action->payload['contents'], $action->payload['overwrite'])),
                'file:copy' => $this->value($action, $this->files->copy($cwd, $action->payload['from'], $action->payload['to'], $action->payload['overwrite'])),
                'file:move' => $this->value($action, $this->files->move($cwd, $action->payload['from'], $action->payload['to'], $action->payload['overwrite'])),
                'file:delete' => $this->value($action, $this->files->delete($cwd, $action->payload['paths'])),
                'file:download' => $this->value($action, $this->files->download($cwd, $action->payload['url'], $action->payload['to'], $action->payload['overwrite'])),
                'edit:text' => $this->value($action, $this->text->edit($cwd, $action->payload['path'], $action->payload['operation'], $action->payload['arguments'])),
                'edit:json' => $this->value($action, $this->json->edit($cwd, $action->payload['path'], $action->payload['operation'], $action->payload['arguments'])),
                'edit:php' => $this->value($action, $this->php->edit($cwd, $action->payload['path'], $action->payload['operation'], $action->payload['arguments'])),
                'edit:env' => $this->value($action, $this->dotenv->edit($cwd, $action->payload['path'], $action->payload['operation'], $action->payload['arguments'])),
                'verify:file' => $this->verifyFile($action, $cwd),
                'verify:command' => $this->process($action, $cwd, $timeout),
                'verify:callback' => $this->verifyCallback($action),
                default => throw new ComposeException("Unknown action type: {$action->type}"),
            };

            return new ActionResult(
                $action,
                $result->successful,
                $result->output,
                $result->errorOutput,
                $result->exitCode,
                microtime(true) - $started,
            );
        } catch (Throwable $exception) {
            return new ActionResult($action, false, errorOutput: $exception->getMessage(), exitCode: 1, duration: microtime(true) - $started);
        }
    }

    private function process(ActionDefinition $action, string $cwd, ?float $timeout): ProcessResult
    {
        /** @var list<string> $command */
        $command = $action->payload['command'];

        return $this->processes->run($command, $cwd, $timeout);
    }

    private function shell(ActionDefinition $action, string $cwd, ?float $timeout): ProcessResult
    {
        return $this->processes->shell($action->payload['command'], $cwd, $timeout);
    }

    private function node(ActionDefinition $action, string $cwd, ?float $timeout): ProcessResult
    {
        $manager = NodeManager::tryFrom((string) ($action->payload['manager'] ?? '')) ?? $this->detectNodeManager($cwd);
        $operation = substr($action->type, strlen('node:'));
        /** @var list<string> $arguments */
        $arguments = $action->payload['arguments'];

        $command = match ([$manager, $operation]) {
            [NodeManager::Npm, 'install'] => ['npm', 'install'],
            [NodeManager::Npm, 'add'] => ['npm', 'install', ...$arguments],
            [NodeManager::Npm, 'add-dev'] => ['npm', 'install', '--save-dev', ...$arguments],
            [NodeManager::Npm, 'remove'] => ['npm', 'uninstall', ...$arguments],
            [NodeManager::Npm, 'run'] => ['npm', 'run', ...$arguments],
            [NodeManager::Npm, 'exec'] => ['npx', '--yes', ...$arguments],
            [NodeManager::Pnpm, 'install'] => ['pnpm', 'install'],
            [NodeManager::Pnpm, 'add'] => ['pnpm', 'add', ...$arguments],
            [NodeManager::Pnpm, 'add-dev'] => ['pnpm', 'add', '--save-dev', ...$arguments],
            [NodeManager::Pnpm, 'remove'] => ['pnpm', 'remove', ...$arguments],
            [NodeManager::Pnpm, 'run'] => ['pnpm', 'run', ...$arguments],
            [NodeManager::Pnpm, 'exec'] => ['pnpm', 'exec', ...$arguments],
            [NodeManager::Yarn, 'install'] => ['yarn', 'install'],
            [NodeManager::Yarn, 'add'] => ['yarn', 'add', ...$arguments],
            [NodeManager::Yarn, 'add-dev'] => ['yarn', 'add', '--dev', ...$arguments],
            [NodeManager::Yarn, 'remove'] => ['yarn', 'remove', ...$arguments],
            [NodeManager::Yarn, 'run'] => ['yarn', 'run', ...$arguments],
            [NodeManager::Yarn, 'exec'] => ['yarn', 'exec', ...$arguments],
            [NodeManager::Bun, 'install'] => ['bun', 'install'],
            [NodeManager::Bun, 'add'] => ['bun', 'add', ...$arguments],
            [NodeManager::Bun, 'add-dev'] => ['bun', 'add', '--dev', ...$arguments],
            [NodeManager::Bun, 'remove'] => ['bun', 'remove', ...$arguments],
            [NodeManager::Bun, 'run'] => ['bun', 'run', ...$arguments],
            [NodeManager::Bun, 'exec'] => ['bunx', ...$arguments],
            default => throw new ComposeException("Unsupported {$manager->value} operation: {$operation}"),
        };

        return $this->processes->run($command, $cwd, $timeout);
    }

    private function detectNodeManager(string $cwd): NodeManager
    {
        return match (true) {
            file_exists($cwd.'/pnpm-lock.yaml') => NodeManager::Pnpm,
            file_exists($cwd.'/yarn.lock') => NodeManager::Yarn,
            file_exists($cwd.'/bun.lock'), file_exists($cwd.'/bun.lockb') => NodeManager::Bun,
            default => NodeManager::Npm,
        };
    }

    private function clone(ActionDefinition $action, string $cwd): ProcessResult
    {
        return $this->cloner->clone(
            $cwd,
            $action->payload['repository'],
            $action->payload['branch'],
            $action->payload['here'] ? 'here' : 'into',
            $action->payload['path'],
        );
    }

    private function commit(ActionDefinition $action, string $cwd, ?float $timeout): ProcessResult
    {
        $stage = $this->processes->run(['git', 'add', '-A'], $cwd, $timeout);
        if (! $stage->successful) {
            return $stage;
        }

        return $this->processes->run(['git', 'commit', '-m', $action->payload['message']], $cwd, $timeout);
    }

    private function verifyFile(ActionDefinition $action, string $cwd): ProcessResult
    {
        $target = $this->paths->resolve($cwd, $action->payload['path']);

        return file_exists($target)
            ? new ProcessResult([], 0, "Found {$action->payload['path']}")
            : new ProcessResult([], 1, errorOutput: "File does not exist: {$action->payload['path']}");
    }

    private function verifyCallback(ActionDefinition $action): ProcessResult
    {
        $successful = ($action->payload['callback'])();

        return $successful === false
            ? new ProcessResult([], 1, errorOutput: 'Verification callback returned false.')
            : new ProcessResult([], 0, 'Verification callback passed.');
    }

    private function value(ActionDefinition $action, string $output): ProcessResult
    {
        return new ProcessResult([$action->type], 0, $output);
    }
}
