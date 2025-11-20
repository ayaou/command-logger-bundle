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

namespace Ayaou\CommandLoggerBundle\Tests\Integration;

use Ayaou\CommandLoggerBundle\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AppKernelTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
