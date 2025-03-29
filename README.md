# Command Logger Bundle

## Overview
The **Command Logger Bundle** is a Symfony bundle that logs executed console commands. It provides insights into command execution, including arguments, execution time, exit codes, and errors.

## Installation

Install the bundle via Composer:
```bash
composer require ayaou/command-logger-bundle
```

Register the bundle in `config/bundles.php` if not automatically added:
```php
Ayaou\CommandLoggerBundle\AyaouCommandLoggerBundle::class => ['all' => true],
```

## Configuration

Add the following configuration in `config/packages/command_logger.yaml`:
```yaml
command_logger:
  enabled: true         # Enable or disable logging (default: true)
  purge_threshold: 100  # Days after which old logs are deleted (e.g., 100 means logs older than 100 days are removed)
  commands:            # List of commands to log if they are not annotated (This can be useful for commands located in third-party bundles)
    - app:example-command
    - app:another-command
```

## Usage

### Enabling Logging on a Command
Use configuration `commands` array (if not using attributes) Or:

Use the `CommandLogger` attribute on any Symfony command to enable logging:
```php
use Ayaou\CommandLoggerBundle\Attribute\CommandLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:example-command')]
#[CommandLogger]
class ExampleCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Executing example command...');
        return Command::SUCCESS;
    }
}
```

## Entity Structure
The logs are stored in the `command_log` table with the following fields:
- `commandName` – Name of the executed command
- `arguments` – Command arguments in JSON format
- `startTime` – Execution start time
- `endTime` – Execution end time
- `exitCode` – Command exit code
- `errorMessage` – Error message if applicable
- `executionToken` – Unique identifier for execution tracking

## Purging Old Logs
The bundle includes an automatic mechanism to purge logs older than the configured `purge_threshold`. You can also manually trigger log cleanup using the following command:
```bash
bin/console command-logger:purge
```
By default, this uses the `purge_threshold` value from the configuration. To override it, specify a custom threshold (in days) with the `--threshold` or `-t` option:
```bash
bin/console command-logger:purge --threshold=30
```
For example, `--threshold=30` removes logs older than 30 days

## License
MIT License

