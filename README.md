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
  enabled: true  # Enable or disable logging
  purge_threshold: 100  # Number of days from which old logs will be deleted. For example, if set to 30, logs older than 30 days will be removed.
```

## Usage

### Enabling Logging on a Command
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
A built-in mechanism automatically purges logs older than the configured threshold, which is configurable and can be adjusted in the bundle's settings. You can manually trigger the purge with:
```bash
bin/console command-logger:purge
```
If the `--threshold` option is not provided, the default threshold value from the configuration will be used.
Optionally, you can specify a custom threshold (in days) using the `--threshold` or `-t` option. This represents the number of days from which old logs will be deleted. For example, if set to 30, logs older than 30 days will be removed:
```bash
bin/console command-logger:purge --threshold=30
```
If no threshold is provided, the default configured value will be used.
A built-in mechanism automatically purges logs older than the configured threshold. You can manually trigger the purge with:
```bash
bin/console command-logger:purge
```

## License
MIT License

