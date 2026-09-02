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
use Symfony\Component\Console\Command\Command;

/**
 * A classic Command subclass with #[CommandLogger] and #[AsCommand]. AddConsoleCommandPass
 * never rewrites this one, but CommandLoggerPass must still collect it the same way.
 */
#[CommandLogger]
#[AsCommand(name: 'app:class-command')]
class ClassCommandWithLogger extends Command
{
}
