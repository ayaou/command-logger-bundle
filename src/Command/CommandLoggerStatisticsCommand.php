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

namespace Ayaou\CommandLoggerBundle\Command;

use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name       : 'command-logger:stats',
    description: 'Show command execution statistics',
)]
class CommandLoggerStatisticsCommand extends Command
{
    private CommandLogRepository $commandLogRepository;

    public function __construct(
        CommandLogRepository $commandLogRepository,
    ) {
        parent::__construct();
        $this->commandLogRepository = $commandLogRepository;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Filter by command name')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Filter by status ("success" or "error")')
            ->addOption('code', 'c', InputOption::VALUE_OPTIONAL, 'Filter by exit code')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Only include logs started on or after this date/time (Y-m-d or Y-m-d H:i:s)')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Only include logs started on or before this date/time (Y-m-d or Y-m-d H:i:s)')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of commands to show in the per-command breakdown', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $status = $input->getOption('status');
        $code = $input->getOption('code');
        $from = $input->getOption('from');
        $to = $input->getOption('to');
        $limit = $input->getOption('limit');

        if (null !== $status && !in_array($status, ['success', 'error'], true)) {
            throw new InvalidArgumentException('The --status option must be either "success" or "error".');
        }

        if (null !== $code) {
            if (!is_numeric($code)) {
                throw new InvalidArgumentException('The --code option must be a numeric value.');
            }

            $code = (int) $code;
        }

        if (null !== $status && null !== $code) {
            throw new InvalidArgumentException('The --status and --code options cannot be used together.');
        }

        foreach (['from' => $from, 'to' => $to] as $option => $value) {
            if (null !== $value && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                throw new InvalidArgumentException(sprintf('The --%s option must be formatted as "Y-m-d" or "Y-m-d H:i:s".', $option));
            }
        }

        if (null !== $limit) {
            if (!is_numeric($limit)) {
                throw new InvalidArgumentException('The --limit option must be a numeric value.');
            }

            $limit = (int) $limit;
        }

        $filter = new CommandLogFilter(
            name: $name,
            status: $status,
            code: $code,
            from: $from,
            to: $to,
        );

        $statistics = $this->commandLogRepository->getStatistics($filter);
        $byCommand = $this->commandLogRepository->getStatisticsByCommand($filter, $limit);

        $this->displaySummary($statistics, $io);
        $this->displayByExitCode($statistics['byExitCode'], $io);
        $this->displayByCommand($byCommand, $io);

        return Command::SUCCESS;
    }

    /**
     * @param array{
     *     total: int,
     *     successCount: int,
     *     failureCount: int,
     *     unfinishedCount: int,
     *     failureRate: float,
     *     durationMs: array{avg: float|null, min: int|null, max: int|null, count: int},
     *     byExitCode: array<int, int>,
     * } $statistics
     */
    private function displaySummary(array $statistics, SymfonyStyle $io): void
    {
        $io->section('Summary');
        $io->table(['Metric', 'Value'], [
            ['Total', $statistics['total']],
            ['Successes', $statistics['successCount']],
            ['Failures', $statistics['failureCount']],
            ['Unfinished', $statistics['unfinishedCount']],
            ['Failure rate', sprintf('%.2f%%', $statistics['failureRate'] * 100)],
            ['Avg duration (ms)', $this->formatDuration($statistics['durationMs']['avg'])],
            ['Min duration (ms)', $statistics['durationMs']['min'] ?? '-'],
            ['Max duration (ms)', $statistics['durationMs']['max'] ?? '-'],
            ['Measured executions', $statistics['durationMs']['count']],
        ]);
    }

    /**
     * @param array<int, int> $byExitCode
     */
    private function displayByExitCode(array $byExitCode, SymfonyStyle $io): void
    {
        $io->section('Breakdown by exit code');

        if ([] === $byExitCode) {
            $io->note('No exit codes recorded.');

            return;
        }

        $rows = [];
        foreach ($byExitCode as $exitCode => $count) {
            $rows[] = [$exitCode, $count];
        }

        $io->table(['Exit Code', 'Count'], $rows);
    }

    /**
     * @param array<int, array{
     *     commandName: string,
     *     total: int,
     *     successCount: int,
     *     failureCount: int,
     *     unfinishedCount: int,
     *     failureRate: float,
     *     durationMs: array{avg: float|null, min: int|null, max: int|null, count: int},
     * }> $byCommand
     */
    private function displayByCommand(array $byCommand, SymfonyStyle $io): void
    {
        $io->section('Breakdown by command');

        if ([] === $byCommand) {
            $io->note('No entries found matching the criteria.');

            return;
        }

        $rows = [];
        foreach ($byCommand as $entry) {
            $rows[] = [
                $entry['commandName'],
                $entry['total'],
                $entry['successCount'],
                $entry['failureCount'],
                $entry['unfinishedCount'],
                sprintf('%.2f%%', $entry['failureRate'] * 100),
                $this->formatDuration($entry['durationMs']['avg']),
                $entry['durationMs']['min'] ?? '-',
                $entry['durationMs']['max'] ?? '-',
            ];
        }

        $io->table(
            ['Command', 'Total', 'Success', 'Failure', 'Unfinished', 'Failure rate', 'Avg (ms)', 'Min (ms)', 'Max (ms)'],
            $rows,
        );
    }

    private function formatDuration(?float $value): string
    {
        return null !== $value ? (string) round($value, 2) : '-';
    }
}
