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

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Same invokable shape as InvokableCommandWithLogger, but without #[CommandLogger]:
 * must never be collected by CommandLoggerPass.
 */
#[AsCommand(name: 'app:invokable-without-logger')]
class InvokableCommandWithoutLogger
{
    public function __invoke(): int
    {
        return 0;
    }
}
