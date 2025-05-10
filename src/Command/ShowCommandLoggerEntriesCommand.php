<?php

namespace Ayaou\CommandLoggerBundle\Command;

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
            ->addOption('success', null, InputOption::VALUE_NONE, 'Filter entries with zero exit code (success)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');

        $errorFlag   = $input->getOption('error');
        $successFlag = $input->getOption('success');
        $exitCode    = null !== $input->getOption('code') ? (int) $input->getOption('code') : null;

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

        $limit       = (int) ($input->getOption('limit') ?? 10);
        $commandName = $input->getArgument('name');
        $offset      = 0;

        while (true) {
            $qb = $this->commandLogRepository->createQueryBuilder('cl');

            if (null !== $commandName) {
                $qb->andWhere('cl.commandName = :commandName')
                    ->setParameter('commandName', $commandName);
            }

            if ($errorFlag) {
                $qb->andWhere('cl.exitCode != :exitCode')
                    ->setParameter('exitCode', 0);
            } elseif ($successFlag) {
                $qb->andWhere('cl.exitCode = :exitCode')
                    ->setParameter('exitCode', 0);
            } elseif (null !== $exitCode) {
                $qb->andWhere('cl.exitCode = :exitCode')
                    ->setParameter('exitCode', $exitCode);
            }

            $entries = $qb->addOrderBy('cl.startTime', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->getQuery()
                ->getResult();

            if (empty($entries)) {
                if (0 === $offset) {
                    $io->note('No entries found matching the criteria.');
                }
                break;
            }

            $this->displayEntries($entries, $io, 1 === $limit);
            $offset += $limit;

            if (count($entries) < $limit) {
                break;
            }

            $io->write('[Press Enter to show more entries, or type anything to exit]: ');
            $response = trim(fgets(STDIN) ?: '');
            if ('' !== $response) {
                break;
            }
            $io->newLine();
        }

        return Command::SUCCESS;
    }

    private function displayEntries(array $entries, SymfonyStyle $io, bool $isSingleEntry = false): void
    {
        if ($isSingleEntry && 1 === count($entries)) {
            $entry  = $entries[0];
            $fields = [
                'ID'              => $entry->getId(),
                'Command'         => $entry->getCommandName(),
                'Arguments'       => json_encode($entry->getArguments()),
                'Start Time'      => $entry->getStartTime()->format('Y-m-d H:i:s'),
                'End Time'        => $entry->getEndTime() ? $entry->getEndTime()->format('Y-m-d H:i:s') : '-',
                'Exit Code'       => $entry->getExitCode() ?? '-',
                'Error Message'   => $entry->getErrorMessage() ?? '-',
                'Execution Token' => $entry->getExecutionToken(),
            ];

            foreach ($fields as $label => $value) {
                $io->write("<info>$label:</info> $value\n");
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
                    $entry->getStartTime()->format('Y-m-d H:i:s'),
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
