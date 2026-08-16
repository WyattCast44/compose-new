<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function testDirectory(string $name = 'case'): string
{
    $path = sys_get_temp_dir().'/compose-tests-'.$name.'-'.bin2hex(random_bytes(6));
    mkdir($path, 0777, true);

    return $path;
}

function withinDirectory(string $directory, Closure $callback): mixed
{
    $previousDirectory = getcwd();
    if ($previousDirectory === false || ! chdir($directory)) {
        throw new RuntimeException("Unable to enter test directory: {$directory}");
    }

    try {
        return $callback();
    } finally {
        if (! chdir($previousDirectory)) {
            throw new RuntimeException("Unable to restore test directory: {$previousDirectory}");
        }
    }
}

/** @param list<string> $command */
function runTestCommand(array $command, string $cwd): string
{
    $process = new Process($command, $cwd);
    $process->mustRun();

    return $process->getOutput();
}
