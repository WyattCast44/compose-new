<?php

declare(strict_types=1);

namespace Compose\Tool;

use Compose\Definition\Risk;
use Compose\PendingAction;
use Compose\Step;

final readonly class FilesBuilder
{
    public function __construct(private Step $step) {}

    public function create(string $path, string $contents, bool $overwrite = false): PendingAction
    {
        return $this->step->queue(
            'file:create',
            "create {$path}",
            compact('path', 'contents', 'overwrite'),
            $overwrite ? [Risk::Destructive] : [],
        );
    }

    public function copy(string $from, string $to, bool $overwrite = false): PendingAction
    {
        return $this->step->queue(
            'file:copy',
            "copy {$from} to {$to}",
            compact('from', 'to', 'overwrite'),
            $overwrite ? [Risk::Destructive] : [],
        );
    }

    public function move(string $from, string $to, bool $overwrite = false): PendingAction
    {
        return $this->step->queue(
            'file:move',
            "move {$from} to {$to}",
            compact('from', 'to', 'overwrite'),
            [Risk::Destructive],
        );
    }

    public function delete(string ...$paths): PendingAction
    {
        return $this->step->queue(
            'file:delete',
            'delete '.implode(', ', $paths),
            ['paths' => $paths],
            [Risk::Destructive],
        );
    }

    public function download(string $url, string $to, bool $overwrite = false): PendingAction
    {
        return $this->step->queue(
            'file:download',
            "download {$url} to {$to}",
            compact('url', 'to', 'overwrite'),
            [Risk::Network, ...($overwrite ? [Risk::Destructive] : [])],
        );
    }
}
