<?php

declare(strict_types=1);

namespace Compose\Definition;

interface Finalizable
{
    public function assertFinalized(): void;
}
