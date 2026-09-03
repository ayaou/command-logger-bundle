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

namespace Ayaou\CommandLoggerBundle\Util;

/**
 * A fixed-size sink for the bytes a watched command writes.
 *
 * Memory is bounded by $maxLength and by nothing else: once that many bytes have been
 * accumulated the buffer stops retaining anything at all, so a command that writes a
 * gigabyte costs exactly the same as one that writes a kilobyte. That property is the
 * whole point of this class - the alternative, concatenating output until the command
 * ends, makes the logger's footprint a function of the observed command's verbosity,
 * which is precisely what an observability tool must never do.
 *
 * append() is called from inside a stream filter, once per write the command performs,
 * so it does no work beyond two length comparisons and one concatenation, and it never
 * throws - see OutputCaptureFilter for why that matters.
 *
 * @internal
 */
class OutputBuffer
{
    private string $contents = '';

    private bool $truncated = false;

    private int $maxLength;

    /**
     * @param int $maxLength Maximum number of bytes retained, and therefore the exact
     *                       upper bound on this object's memory footprint
     */
    public function __construct(int $maxLength)
    {
        $this->maxLength = max(0, $maxLength);
    }

    public function append(string $chunk): void
    {
        if ($this->truncated) {
            return;
        }

        $room = $this->maxLength - \strlen($this->contents);

        if ($room <= 0) {
            $this->truncated = true;

            return;
        }

        if (\strlen($chunk) > $room) {
            // Cut on a byte boundary here rather than a character one: this runs per write
            // and must stay cheap. OutputCapture repairs any character split once, at the end.
            $this->contents .= substr($chunk, 0, $room);
            $this->truncated = true;

            return;
        }

        $this->contents .= $chunk;
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function isEmpty(): bool
    {
        return '' === $this->contents;
    }
}
