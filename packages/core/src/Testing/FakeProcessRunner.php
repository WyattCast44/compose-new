<?php

declare(strict_types=1);

namespace Compose\Testing;

use Compose\Execution\ProcessResult;
use Compose\Execution\ProcessRunner;

final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<array{command: list<string>|string, cwd: string, timeout: ?float, input: ?string}> */
    public array $executed = [];

    /** @param array<string, ProcessResult|list<ProcessResult>> $responses */
    public function __construct(private array $responses = []) {}

    public function run(array $command, string $cwd, ?float $timeout = null, ?string $input = null, array $environment = []): ProcessResult
    {
        return $this->handle($command, $cwd, $timeout, $input);
    }

    public function shell(string $command, string $cwd, ?float $timeout = null): ProcessResult
    {
        return $this->handle($command, $cwd, $timeout, null);
    }

    /** @param list<string>|string $command */
    private function handle(array|string $command, string $cwd, ?float $timeout, ?string $input): ProcessResult
    {
        $this->executed[] = compact('command', 'cwd', 'timeout', 'input');
        $text = is_array($command) ? implode(' ', $command) : $command;

        foreach ($this->responses as $pattern => &$response) {
            if (! fnmatch($pattern, $text)) {
                continue;
            }

            if (is_array($response)) {
                return array_shift($response) ?? new ProcessResult($command, 0);
            }

            return $response;
        }

        return new ProcessResult($command, 0);
    }
}
