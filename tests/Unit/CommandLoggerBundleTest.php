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

namespace Ayaou\CommandLoggerBundle\Tests\Unit;

use Ayaou\CommandLoggerBundle\CommandLoggerBundle;
use PHPUnit\Framework\TestCase;

class CommandLoggerBundleTest extends TestCase
{
    public function testGetPath(): void
    {
        $bundle = new CommandLoggerBundle();
        $this->assertTrue(is_dir($bundle->getPath()));
    }
}
