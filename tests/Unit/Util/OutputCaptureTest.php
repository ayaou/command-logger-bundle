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

use Ayaou\CommandLoggerBundle\Util\OutputCapture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\StreamOutput;

class OutputCaptureTest extends TestCase
{
    /**
     * @var resource
     */
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://temp', 'r+');
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    private function streamOutput(bool $decorated = false): StreamOutput
    {
        return new StreamOutput($this->stream, StreamOutput::VERBOSITY_NORMAL, $decorated);
    }

    private function writtenToStream(): string
    {
        rewind($this->stream);

        return stream_get_contents($this->stream) ?: '';
    }

    public function testCapturesWhatTheCommandWrote(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln('[OK] 3 products loaded');
        $captured = $capture->stop();

        $this->assertSame("[OK] 3 products loaded\n", $captured);
    }

    /**
     * The non-negotiable one: whatever this bundle does, the bytes the command wrote still
     * reach their destination, unchanged and in full.
     */
    public function testTheCommandsOwnOutputReachesTheStreamUntouched(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln('first line');
        $output->writeln('second line');
        $capture->stop();

        $this->assertSame("first line\nsecond line\n", $this->writtenToStream());
    }

    public function testStreamStaysUntouchedEvenWhenTheBoundIsReachedImmediately(): void
    {
        $capture = new OutputCapture(true, 100);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln(str_repeat('a', 500));
        $output->writeln('written after the bound was hit');
        $capture->stop();

        // The capture stopped retaining; the command's output did not stop flowing.
        $this->assertStringContainsString(str_repeat('a', 500), $this->writtenToStream());
        $this->assertStringContainsString('written after the bound was hit', $this->writtenToStream());
    }

    public function testCapturesNothingWhenDisabled(): void
    {
        $capture = new OutputCapture(false, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln('not captured');

        $this->assertNull($capture->stop());
        $this->assertSame("not captured\n", $this->writtenToStream());
    }

    public function testIsEnabledReflectsTheConfiguredFlag(): void
    {
        $this->assertFalse((new OutputCapture(false, 4096))->isEnabled());
        $this->assertTrue((new OutputCapture(true, 4096))->isEnabled());
    }

    public function testDefaultsToDisabled(): void
    {
        $this->assertFalse((new OutputCapture())->isEnabled());
    }

    public function testStripsTheEscapeSequencesOfADecoratedOutput(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput(true);

        $capture->start($output);
        $output->writeln('<info>coloured</info>');
        $captured = $capture->stop();

        $this->assertNotNull($captured);
        $this->assertStringNotContainsString("\033", $captured);
        $this->assertStringContainsString('coloured', $captured);
        // The real stream did receive the colours: only what is stored is stripped.
        $this->assertStringContainsString("\033", $this->writtenToStream());
    }

    public function testMarksTruncationWithASuffix(): void
    {
        $capture = new OutputCapture(true, 120);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln(str_repeat('z', 500));
        $captured = $capture->stop();

        $this->assertNotNull($captured);
        $this->assertStringEndsWith(' [truncated]', $captured);
    }

    public function testReturnsNullWhenTheCommandPrintedNothing(): void
    {
        $capture = new OutputCapture(true, 4096);

        $capture->start($this->streamOutput());

        $this->assertNull($capture->stop());
    }

    public function testLeavesOutputsThatAreNotBackedByAStreamAlone(): void
    {
        $capture = new OutputCapture(true, 4096);
        $buffered = new BufferedOutput();

        $capture->start($buffered);
        $buffered->writeln('into the buffer');

        $this->assertNull($capture->stop());
        // The output object itself still works exactly as it did.
        $this->assertSame("into the buffer\n", $buffered->fetch());
    }

    public function testLeavesANullOutputAlone(): void
    {
        $capture = new OutputCapture(true, 4096);

        $capture->start(new NullOutput());

        $this->assertNull($capture->stop());
    }

    public function testStoppingTwiceIsHarmless(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln('once');

        $this->assertSame("once\n", $capture->stop());
        $this->assertNull($capture->stop());
    }

    public function testStoppingWithoutStartingIsHarmless(): void
    {
        $this->assertNull((new OutputCapture(true, 4096))->stop());
    }

    /**
     * A command that delegates to another one must not restart the capture: the second
     * start() would attach a second filter to the same stream and record every byte twice.
     */
    public function testASecondStartDoesNotDoubleRecord(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $capture->start($output);
        $output->writeln('written once');
        $captured = $capture->stop();

        $this->assertSame("written once\n", $captured);
    }

    public function testCaptureCanRunAgainAfterBeingStopped(): void
    {
        $capture = new OutputCapture(true, 4096);
        $output = $this->streamOutput();

        $capture->start($output);
        $output->writeln('first run');
        $this->assertSame("first run\n", $capture->stop());

        $capture->start($output);
        $output->writeln('second run');
        $this->assertSame("second run\n", $capture->stop());
    }
}
