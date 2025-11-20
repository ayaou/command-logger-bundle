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

namespace Ayaou\CommandLoggerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CommandLoggerBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
