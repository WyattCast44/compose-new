<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;

final readonly class GitRepository
{
    public function __construct(private ProcessRunner $processes = new SymfonyProcessRunner) {}

    public function root(string $cwd): ?string
    {
        $result = $this->processes->run(['git', 'rev-parse', '--show-toplevel'], $cwd, 10);

        return $result->successful ? trim($result->output) : null;
    }

    public function assertClean(string $root): void
    {
        $status = $this->must(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        if (trim($status) !== '') {
            throw new ComposeException("Git worktree must be clean before Compose starts:\n{$status}");
        }
    }

    public function checkpoint(string $root): GitCheckpoint
    {
        $head = trim($this->must(['git', 'rev-parse', 'HEAD'], $root));
        $index = trim($this->must(['git', 'write-tree'], $root));
        $worktree = $this->captureWorktree($root, $head);

        return new GitCheckpoint($root, $head, $worktree, $index);
    }

    public function currentTree(GitCheckpoint $checkpoint): string
    {
        return $this->captureWorktree($checkpoint->root, $checkpoint->head);
    }

    public function currentHead(GitCheckpoint $checkpoint): string
    {
        return trim($this->must(['git', 'rev-parse', 'HEAD'], $checkpoint->root));
    }

    public function patch(GitCheckpoint $checkpoint): string
    {
        $current = $this->currentTree($checkpoint);

        return $this->must(['git', 'diff', '--binary', '--no-ext-diff', $checkpoint->worktree, $current], $checkpoint->root);
    }

    /** @return list<string> */
    public function changedPaths(GitCheckpoint $checkpoint): array
    {
        $current = $this->currentTree($checkpoint);
        $output = trim($this->must(['git', 'diff', '--name-only', $checkpoint->worktree, $current], $checkpoint->root));

        return $output === '' ? [] : (preg_split('/\R/', $output) ?: []);
    }

    public function restore(GitCheckpoint $checkpoint): void
    {
        if ($this->currentHead($checkpoint) !== $checkpoint->head) {
            throw new ComposeException('Cannot automatically roll back a changed Git HEAD.');
        }

        $current = $this->currentTree($checkpoint);
        $patch = $this->must(['git', 'diff', '--binary', '--no-ext-diff', $current, $checkpoint->worktree], $checkpoint->root);

        if ($patch !== '') {
            $this->must(['git', 'apply', '--binary', '--whitespace=nowarn', '-'], $checkpoint->root, $patch);
        }

        $this->must(['git', 'read-tree', $checkpoint->index], $checkpoint->root);
    }

    public function applyPatch(string $root, string $patch): void
    {
        $this->must(['git', 'apply', '--check', '--binary', '-'], $root, $patch);
        $this->must(['git', 'apply', '--binary', '--whitespace=nowarn', '-'], $root, $patch);
    }

    private function captureWorktree(string $root, string $head): string
    {
        $index = tempnam(sys_get_temp_dir(), 'compose-index-');
        if ($index === false) {
            throw new ComposeException('Unable to create a temporary Git index.');
        }
        unlink($index);

        try {
            $environment = ['GIT_INDEX_FILE' => $index];
            $this->must(['git', 'read-tree', $head], $root, environment: $environment);
            $this->must(['git', 'add', '-A', '--', '.'], $root, environment: $environment);

            return trim($this->must(['git', 'write-tree'], $root, environment: $environment));
        } finally {
            if (file_exists($index)) {
                unlink($index);
            }
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function must(array $command, string $cwd, ?string $input = null, array $environment = []): string
    {
        $result = $this->processes->run($command, $cwd, 60, $input, $environment);
        if (! $result->successful) {
            throw new ComposeException(trim($result->errorOutput) ?: 'Git command failed: '.implode(' ', $command));
        }

        return $result->output;
    }
}
