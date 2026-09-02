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
  sensitive_parameters: # Argument/option names matching one of these substrings (case-insensitive) have their value replaced with [REDACTED] before being logged. Set to [] to disable redaction. Default:
    - password
    - passwd
    - secret
    - token
    - api-key
    - api_key
    - apikey
    - credential
    - auth
  max_error_message_length: 65535  # Maximum byte length of the stored error message; longer messages are truncated (multi-byte safe) and suffixed with " [truncated]" (default: 65535, minimum: 100)
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

## REST API

The bundle can optionally expose the command log history over a read-only JSON-LD/Hydra REST API.

> **:warning: Security warning**
>
> These endpoints expose the full command execution history, including command arguments. They
> ship with **no access control of any kind** — anyone who can reach the routes can read every
> logged command and its arguments. Protecting them is entirely the consuming application's
> responsibility: restrict the path with `access_control` in `security.yaml` (or an equivalent
> firewall rule) before exposing this API outside of trusted networks. This is the single most
> important thing to get right before enabling this section — read it before you import the
> routes below.

### Enabling the API

The bundle never loads its routes automatically. Import them explicitly and choose a prefix:

```yaml
# config/routes/command_logger.yaml
command_logger:
    resource: '@CommandLoggerBundle/config/routes.yaml'
    prefix: /api
```

Once imported, the API is reachable under the chosen prefix, e.g. `GET /api/command-logs`. Combine
this with an `access_control` rule scoped to the same prefix, for example:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/api/command-logs, roles: ROLE_ADMIN }
```

### Endpoints

| Method | Path                 | Description                                                          |
|--------|----------------------|------------------------------------------------------------------------|
| `GET`  | `/command-logs`      | Paginated, filterable collection of command logs (JSON-LD Hydra collection). |
| `GET`  | `/command-logs/{id}` | A single command log, looked up by its numeric `id` or its `executionToken`. |

Route names are prefixed with `command_logger_api_` (e.g. `command_logger_api_list`,
`command_logger_api_item`), following Symfony's naming convention for bundle-provided routes.

### Query parameters

All parameters below apply to `GET /command-logs` and can be combined, unless stated otherwise.

* `page` (optional, integer, default `1`) — Page number to return; must be positive.
* `limit` (optional, integer, default `10`) — Number of entries per page; must be between `1` and `100`.
* `name` (optional, string, minimum length `2`) — Filters logs whose command name contains this value.
* `status` (optional, string, one of `success` or `error`) — Filters by outcome. Cannot be combined with `code`.
* `code` (optional, integer) — Filters by exact exit code. Cannot be combined with `status`.
* `from` (optional, string, `Y-m-d` or `Y-m-d H:i:s`) — Only logs started on or after this date/time.
* `to` (optional, string, `Y-m-d` or `Y-m-d H:i:s`) — Only logs started on or before this date/time. Must not be earlier than `from`.

Invalid or conflicting parameters return `422 Unprocessable Entity` with a
[Problem Details](https://www.rfc-editor.org/rfc/rfc9457) (`application/problem+json`) body listing
the offending `violations`, each with its `propertyPath`. Unknown ids return `404 Not Found` in the
same Problem Details format.

## License
MIT License

