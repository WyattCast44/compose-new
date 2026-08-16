<?php

declare(strict_types=1);

use Compose\Composition;

if (! function_exists('compose')) {
    function compose(string $name): Composition
    {
        return new Composition($name);
    }
}
