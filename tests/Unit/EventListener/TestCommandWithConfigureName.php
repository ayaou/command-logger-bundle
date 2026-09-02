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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Command\Command;

/**
 * A Command subclass that sets its name in configure() rather than via #[AsCommand].
 * CommandLoggerPass has no #[AsCommand] to read here, so this name can never appear in the
 * "command_logger.attributed_commands" parameter it builds. It exists to prove that
 * AbstractCommandListener's reflection-based fallback still covers this case on its own.
 */
#[CommandLogger]
class TestCommandWithConfigureName extends Command
{
    protected function configure(): void
    {
        $this->setName('app:configured-command');
    }
}
