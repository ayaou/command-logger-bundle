<?php

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
