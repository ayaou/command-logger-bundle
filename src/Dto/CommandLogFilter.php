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

namespace Ayaou\CommandLoggerBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CommandLogFilter
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,
        #[Assert\Length(min: 2)]
        public ?string $name = null,
        #[Assert\Choice(choices: ['success', 'error'], message: 'Status must be either "success" or "error".')]
        public ?string $status = null,
        public ?int $code = null,

        // Regex: Matches YYYY-MM-DD optionally followed by HH:MM:SS
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/',
            message: 'Format must be "Y-m-d" or "Y-m-d H:i:s"',
        )]
        public ?string $from = null,
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/',
            message: 'Format must be "Y-m-d" or "Y-m-d H:i:s"',
        )]
        public ?string $to = null,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->status && null !== $this->code) {
            $context->buildViolation('You cannot use "status" and "code" filters simultaneously.')
                ->atPath('status')
                ->addViolation();
        }

        // Validate logical order using the helper methods below
        if ($this->from && $this->to) {
            if ($this->getFromDate() > $this->getToDate()) {
                $context->buildViolation('The "from" date cannot be later than the "to" date.')
                    ->atPath('from')
                    ->addViolation();
            }
        }
    }

    public function getFromDate(): ?\DateTimeImmutable
    {
        if (!$this->from) {
            return null;
        }

        // If it looks like just a date (10 chars: 2023-01-01), append Start of Day
        $dateStr = 10 === strlen($this->from) ? $this->from.' 00:00:00' : $this->from;

        return new \DateTimeImmutable($dateStr);
    }

    public function getToDate(): ?\DateTimeImmutable
    {
        if (!$this->to) {
            return null;
        }

        $dateStr = 10 === strlen($this->to) ? $this->to.' 23:59:59' : $this->to;

        return new \DateTimeImmutable($dateStr);
    }
}
