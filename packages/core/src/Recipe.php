<?php

declare(strict_types=1);

namespace Compose;

abstract class Recipe
{
    abstract public function compose(Composition $composition): void;
}
