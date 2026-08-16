<?php

declare(strict_types=1);

namespace Compose\Execution;

use Compose\Exception\ComposeException;

final readonly class GitCloner
{
    public function __construct(
        private ProcessRunner $processes = new SymfonyProcessRunner,
        private PathResolver $paths = new PathResolver,
        private FileEditor $files = new FileEditor,
    ) {}

    public function clone(string $cwd, string $repository, ?string $branch, string $mode, ?string $path): ProcessResult
    {
        $parent = $mode === 'here' ? dirname($cwd) : $cwd;
        $target = $mode === 'here' ? $cwd : $this->paths->resolve($cwd, (string) $path);

        if ($mode === 'here' && iterator_count(new \FilesystemIterator($cwd, \FilesystemIterator::SKIP_DOTS)) !== 0) {
            throw new ComposeException('git clone()->here() requires an empty directory.');
        }

        if ($mode !== 'here' && (file_exists($target) || is_link($target))) {
            throw new ComposeException("Clone target already exists: {$path}");
        }

        $temporary = $parent.'/.compose-clone-'.bin2hex(random_bytes(8));
        $command = ['git', 'clone'];
        if ($branch !== null) {
            array_push($command, '--branch', $branch);
        }
        array_push($command, '--', $repository, $temporary);
        $result = $this->processes->run($command, $parent, 600);

        if (! $result->successful) {
            $this->cleanup($parent, $temporary);

            return $result;
        }

        try {
            if ($mode === 'here') {
                $iterator = new \FilesystemIterator($temporary, \FilesystemIterator::SKIP_DOTS);
                for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
                    if (! rename($iterator->getPathname(), $target.'/'.$iterator->getFilename())) {
                        throw new ComposeException('Unable to move the cloned repository into the current directory.');
                    }
                }
                rmdir($temporary);
            } else {
                $targetParent = dirname($target);
                if (! is_dir($targetParent) && ! mkdir($targetParent, 0777, true) && ! is_dir($targetParent)) {
                    throw new ComposeException("Unable to create clone target directory: {$targetParent}");
                }
                if (! rename($temporary, $target)) {
                    throw new ComposeException("Unable to move cloned repository to {$path}");
                }
            }
        } catch (\Throwable $exception) {
            $this->cleanup($parent, $temporary);
            throw $exception;
        }

        return $result;
    }

    private function cleanup(string $parent, string $temporary): void
    {
        if (! str_starts_with($temporary, $parent.'/.compose-clone-')) {
            return;
        }
        if (file_exists($temporary) || is_link($temporary)) {
            $this->files->delete($parent, [basename($temporary)]);
        }
    }
}
