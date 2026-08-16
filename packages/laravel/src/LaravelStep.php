<?php

declare(strict_types=1);

namespace Compose\Laravel;

use Compose\Step;
use Compose\Tool\PhpEditor;

final class LaravelStep extends Step
{
    public function artisan(): ArtisanBuilder
    {
        return new ArtisanBuilder($this);
    }

    public function config(string $file): PhpEditor
    {
        $path = str_ends_with($file, '.php') ? $file : $file.'.php';

        return $this->php('config/'.$path);
    }

    public function env(string $path = '.env'): EnvEditor
    {
        return new EnvEditor($this, $path);
    }
}
