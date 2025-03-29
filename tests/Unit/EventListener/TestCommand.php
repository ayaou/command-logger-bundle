<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[CommandLogger]
#[AsCommand('app:my-command')]
class TestCommand extends Command
{
}
#[CommandLogger]
#[AsCommand('app:my-command-without-name')]
class TestCommandWithoutName extends Command
{
    public function getName(): ?string
    {
        return null;
    }
}

#[AsCommand('app:command-without-attribute')]
class TestCommandWithoutAttribute extends Command
{
}
