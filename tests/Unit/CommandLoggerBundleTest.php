<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit;

use Ayaou\CommandLoggerBundle\AyaouCommandLoggerBundle;
use PHPUnit\Framework\TestCase;

class AyaouCommandLoggerBundleTest extends TestCase
{
    public function testGetPath(): void
    {
        $bundle = new AyaouCommandLoggerBundle();
        $this->assertTrue(is_dir($bundle->getPath()));
    }
}
