<?php

declare(strict_types=1);

/*
 * This file is part of the command logger bundle.
 *
 * (c) Mohamed AYAOU <github.com/ayaou>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection\Compiler;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * A standalone invokable-style command (does not extend Command) carrying both
 * #[AsCommand] and #[CommandLogger]. This is the exact shape that AddConsoleCommandPass
 * rewrites to Command::class, which is why CommandLoggerPass must reflect on the
 * service definition's class, never on an instance built from the compiled container.
 */
#[CommandLogger]
#[AsCommand(name: 'app:invokable-command', aliases: ['app:invokable-alias'])]
class InvokableCommandWithLogger
{
    public function __invoke(): int
    {
        return 0;
    }
}
