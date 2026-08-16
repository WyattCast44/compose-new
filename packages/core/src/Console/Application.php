<?php

declare(strict_types=1);

namespace Compose\Console;

use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Compose', '0.1.0-dev');
        $this->addCommand(new PlanCommand);
        $this->addCommand(new RunCommand);
        $this->setDefaultCommand('run');
    }
}
