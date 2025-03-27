<?php

namespace Ayaou\CommandLoggerBundle\Command;

use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'command-logger:purge',
    description: 'Purge the command log table',
)]
class PurgeCommandLoggerTableCommand extends Command
{
    private int $defaultPurgeThreshold;

    private CommandLogRepository $commandLogRepository;

    public function __construct(int $defaultPurgeThreshold, CommandLogRepository $commandLogRepository)
    {
        $this->defaultPurgeThreshold = $defaultPurgeThreshold;
        $this->commandLogRepository  = $commandLogRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'threshold',
                't',
                InputOption::VALUE_REQUIRED,
                'Number of days to keep logs (must be a positive integer)',
                $this->defaultPurgeThreshold,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get and validate threshold
        $threshold = $input->getOption('threshold');
        if (!is_numeric($threshold) || (int) $threshold <= 0) {
            $io->error('The threshold must be a positive integer.');

            return Command::INVALID;
        }

        $thresholdDays = (int) $threshold;
        $cutoffDate    = new \DateTimeImmutable("-$thresholdDays days");

        // Purge logs older than the cutoff date
        $deletedCount = $this->commandLogRepository->purgeLogsOlderThan($cutoffDate);

        $io->success(sprintf('Purged %d log entries older than %d days.', $deletedCount, $thresholdDays));

        return Command::SUCCESS;
    }
}
