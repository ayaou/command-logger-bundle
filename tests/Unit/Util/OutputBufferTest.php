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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\Util;

use Ayaou\CommandLoggerBundle\Util\OutputBuffer;
use PHPUnit\Framework\TestCase;

class OutputBufferTest extends TestCase
{
    public function testAccumulatesWhatFitsUnderTheBound(): void
    {
        $buffer = new OutputBuffer(100);

        $buffer->append('hello ');
        $buffer->append('world');

        $this->assertSame('hello world', $buffer->getContents());
        $this->assertFalse($buffer->isTruncated());
    }

    public function testStartsEmpty(): void
    {
        $buffer = new OutputBuffer(100);

        $this->assertTrue($buffer->isEmpty());
        $this->assertSame('', $buffer->getContents());
    }

    public function testStopsExactlyAtTheBound(): void
    {
        $buffer = new OutputBuffer(10);

        $buffer->append('0123456789EXTRA');

        $this->assertSame('0123456789', $buffer->getContents());
        $this->assertTrue($buffer->isTruncated());
    }

    public function testKeepsNothingBeyondTheBoundAcrossManyAppends(): void
    {
        $buffer = new OutputBuffer(10);

        $buffer->append('01234');
        $buffer->append('56789');
        $buffer->append('this is dropped entirely');
        $buffer->append('so is this');

        $this->assertSame('0123456789', $buffer->getContents());
        $this->assertTrue($buffer->isTruncated());
    }

    /**
     * The guarantee the whole feature rests on: what the buffer holds is a function of the
     * configured bound, never of how much the observed command printed. A command that
     * writes ten megabytes must cost exactly what one writing ten bytes costs.
     */
    public function testMemoryIsBoundedByTheLimitNotByTheVolumeWritten(): void
    {
        $bound = 4096;
        $buffer = new OutputBuffer($bound);

        $chunk = str_repeat('x', 64 * 1024);
        for ($i = 0; $i < 160; ++$i) { // 10 MB pushed through
            $buffer->append($chunk);
        }

        $this->assertSame($bound, \strlen($buffer->getContents()));
        $this->assertTrue($buffer->isTruncated());
    }

    public function testAZeroBoundRetainsNothing(): void
    {
        $buffer = new OutputBuffer(0);

        $buffer->append('anything at all');

        $this->assertSame('', $buffer->getContents());
        $this->assertTrue($buffer->isTruncated());
        $this->assertTrue($buffer->isEmpty());
    }

    public function testANegativeBoundIsTreatedAsZeroRatherThanInverted(): void
    {
        $buffer = new OutputBuffer(-50);

        $buffer->append('anything at all');

        $this->assertSame('', $buffer->getContents());
        $this->assertTrue($buffer->isTruncated());
    }

    public function testAppendingNothingLeavesTheBufferUntouched(): void
    {
        $buffer = new OutputBuffer(10);

        $buffer->append('');

        $this->assertTrue($buffer->isEmpty());
        $this->assertFalse($buffer->isTruncated());
    }
}
