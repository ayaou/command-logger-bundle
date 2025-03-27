<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit;

use Ayaou\CommandLoggerBundle\CommandLoggerBundle;
use Monolog\Test\TestCase;

class CommandLoggerBundleTest extends TestCase
{
    public function testGetPath(): void
    {
        $bundle = new CommandLoggerBundle();
        $this->assertTrue(is_dir($bundle->getPath()));
    }
}