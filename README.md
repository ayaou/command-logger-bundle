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
  commands:            # List of commands to log if they are not annotated, we can also use wildcards (This can be useful for commands located in third-party bundles)
    - app:example-command
    - app:another-command
    - make:*
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

## Show Command Logs
The `command-logger:show` command displays logged command executions from the `command_log` table. It supports filtering, pagination, and viewing specific entries by ID.

```bash
bin/console command-logger:show [name] [--limit=LIMIT] [--code=CODE] [--id=ID] [--error] [--success]
```
### Description
This command retrieves and displays command execution logs. By default, it shows the latest 10 entries, ordered by startTime in descending order,
in a tabular format. You can filter by command name, exit code, or success/error status, and view a single entry by ID. 

The command supports pagination, allowing you to press Enter to view more entries interactively.

### Arguments
* name (optional): Filters logs by the command name (e.g., app:example-command).

### Options

* --limit|-l (optional): Specifies the number of entries to show per page (default: 10).
* --code|-c (optional): Filters logs by a specific exit code (e.g., --code=0 for successful commands).
* --id (optional): Displays a single log entry by its ID (e.g., --id=123). When used, no other arguments or options are allowed.
* --error (optional): Filters logs to show only entries with non-zero exit codes (indicating errors). Cannot be used with --success or --code.
* --success (optional): Filters logs to show only entries with an exit code of 0 (indicating success). Cannot be used with --error or --code.

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

