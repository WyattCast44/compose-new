<?php

declare(strict_types=1);

namespace Compose\Console;

use Compose\Composition;
use Compose\Exception\ComposeException;

final class CompositionLoader
{
    public function load(string $path): Composition
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved)) {
            throw new ComposeException("Composition file does not exist: {$path}");
        }

        $composition = (static fn (string $file): mixed => require $file)($resolved);
        if (! $composition instanceof Composition) {
            throw new ComposeException('Composition file must return a '.Composition::class.'.');
        }

        return $composition;
    }
}
