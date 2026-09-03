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
use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name       : 'command-logger:show',
    description: 'Show entries of command logger table',
)]
class ShowCommandLoggerEntriesCommand extends Command
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
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of entries to show per page', 10)
            ->addOption('code', 'c', InputOption::VALUE_OPTIONAL, 'Filter by exit code')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Show specific entry by ID')
            ->addOption('error', null, InputOption::VALUE_NONE, 'Filter entries with non-zero exit code (errors)')
            ->addOption('success', null, InputOption::VALUE_NONE, 'Filter entries with zero exit code (success)')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Only include logs started on or after this date/time (Y-m-d or Y-m-d H:i:s)')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Only include logs started on or before this date/time (Y-m-d or Y-m-d H:i:s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');

        $errorFlag = $input->getOption('error');
        $successFlag = $input->getOption('success');
        $exitCode = $input->getOption('code');
        $from = $input->getOption('from');
        $to = $input->getOption('to');
        $limit = $input->hasOption('limit') ? $input->getOption('limit') : 10;

        if (null !== $id) {
            if (!is_numeric($id)) {
                throw new InvalidArgumentException('The --id option must be a numeric value.');
            }

            $id = (int) $id;
        }

        if (null !== $exitCode) {
            if (!is_numeric($exitCode)) {
                throw new InvalidArgumentException('The --code option must be a numeric value.');
            }

            $exitCode = (int) $exitCode;
        }

        if (null !== $limit) {
            if (!is_numeric($limit)) {
                throw new InvalidArgumentException('The --limit option must be a numeric value.');
            }

            $limit = (int) $limit;
        } else {
            $limit = 10;
        }

        foreach (['from' => $from, 'to' => $to] as $option => $value) {
            if (null !== $value && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                throw new InvalidArgumentException(sprintf('The --%s option must be formatted as "Y-m-d" or "Y-m-d H:i:s".', $option));
            }
        }

        if ($errorFlag && $successFlag) {
            throw new InvalidArgumentException('The --error and --success options cannot be used together.');
        }

        if (($errorFlag || $successFlag) && null !== $exitCode) {
            throw new InvalidArgumentException('The --error or --success options cannot be used with the --code option.');
        }

        if (null !== $id) {
            if (null !== $input->getArgument('name')
                || null !== $exitCode
                || $errorFlag
                || $successFlag
                || null !== $from
                || null !== $to
                || (null !== $input->getOption('limit') && 10 != $input->getOption('limit'))) {
                throw new InvalidArgumentException('When ID is specified, no other options or arguments are allowed, except the default limit.');
            }

            $entry = $this->commandLogRepository->find($id);
            if (!$entry) {
                $io->error("No entry found with ID: $id");

                return Command::FAILURE;
            }

            $this->displayEntries([$entry], $io, true);

            return Command::SUCCESS;
        }

        $status = null;
        if ($errorFlag) {
            $status = 'error';
        } elseif ($successFlag) {
            $status = 'success';
        }

        // --id is a separate lookup path, never a filter (see the early return above): the
        // CommandLogFilter built here is only ever reached for the list view.
        $filter = new CommandLogFilter(
            page: 1,
            limit: $limit,
            name: $input->getArgument('name'),
            status: $status,
            code: null === $status ? $exitCode : null,
            from: $from,
            to: $to,
        );

        while (true) {
            $entries = iterator_to_array($this->commandLogRepository->getPaginatedResults($filter));

            if ([] === $entries) {
                if (1 === $filter->page) {
                    $io->note('No entries found matching the criteria.');
                }
                break;
            }

            $this->displayEntries($entries, $io, 1 === $limit);

            if (count($entries) < $limit) {
                break;
            }

            $response = $io->ask('[Press Enter to show more entries, or type anything to exit]', '');
            if (is_string($response) && '' !== trim($response)) {
                break;
            }

            $io->newLine();
            ++$filter->page;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<CommandLog> $entries
     */
    private function displayEntries(array $entries, SymfonyStyle $io, bool $isSingleEntry = false): void
    {
        if ($isSingleEntry && 1 === count($entries)) {
            $entry = $entries[0];
            $fields = [
                'ID' => $entry->getId(),
                'Command' => $entry->getCommandName(),
                'Arguments' => json_encode($entry->getArguments()),
                'Start Time' => $entry->getStartTime() ? $entry->getStartTime()->format('Y-m-d H:i:s') : '-',
                'End Time' => $entry->getEndTime() ? $entry->getEndTime()->format('Y-m-d H:i:s') : '-',
                'Exit Code' => $entry->getExitCode() ?? '-',
                'Error Message' => $entry->getErrorMessage() ?? '-',
                'Execution Token' => $entry->getExecutionToken(),
            ];

            foreach ($fields as $label => $value) {
                $io->write("<info>$label:</info> $value\n");
            }

            // Multi-line by nature, so it gets its own block rather than being squeezed
            // onto a "label: value" line. Escaped because it is arbitrary text a command
            // wrote: an unescaped <fg=red> or </> in it would be read as console markup
            // here instead of being shown as the command printed it.
            $capturedOutput = $entry->getOutput();
            if (null !== $capturedOutput && '' !== $capturedOutput) {
                $io->newLine();
                $io->write("<info>Output:</info>\n");
                $io->write(OutputFormatter::escape($capturedOutput));
                $io->newLine();
            }

            $io->newLine();
        } else {
            $rows = [];
            foreach ($entries as $entry) {
                $status = null === $entry->getExitCode() ? '❓' : (0 === $entry->getExitCode() ? '✅' : '❌');
                $rows[] = [
                    $status,
                    $entry->getId(),
                    $entry->getCommandName(),
                    $entry->getStartTime() ? $entry->getStartTime()->format('Y-m-d H:i:s') : '-',
                    $entry->getEndTime() ? $entry->getEndTime()->format('Y-m-d H:i:s') : '-',
                    $entry->getExitCode() ?? '-',
                    $entry->getExecutionToken(),
                ];
            }

            $io->table(
                ['Status', 'ID', 'Command', 'Start Time', 'End Time', 'Exit Code', 'Execution Token'],
                $rows,
            );
        }
    }
}
