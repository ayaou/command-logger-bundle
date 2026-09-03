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
- `durationMs` – Execution duration in milliseconds, or `null` when unknown (see "Command Execution Statistics" below)
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

## Command Execution Statistics

> **:warning: Schema update required.** The `durationMs` column is new. After upgrading, run
> `doctrine:schema:update --force` (or apply an equivalent migration) before using this
> feature. Rows logged before the upgrade have no way to recover a duration and will keep
> `durationMs = null` forever; they are excluded from the duration averages/min/max below, not
> counted as zero.

The `command-logger:stats` command aggregates the `command_log` table into a summary, a
breakdown by exit code, and a breakdown by command name.

```bash
bin/console command-logger:stats [name] [--status=STATUS] [--code=CODE] [--from=FROM] [--to=TO] [--limit=LIMIT]
```

### Arguments

* `name` (optional): Filters statistics by command name (exact match on the "name contains" filter, same as the `name` query parameter of the REST API).

### Options

These mirror the REST API's `CommandLogFilter` exactly, so the numbers here are computed over
the same rows a matching `GET /command-logs` request would list:

* `--status` (optional): `success` or `error`. Cannot be combined with `--code`.
* `--code|-c` (optional): Filters by a specific exit code.
* `--from` (optional, `Y-m-d` or `Y-m-d H:i:s`): Only include logs started on or after this date/time.
* `--to` (optional, `Y-m-d` or `Y-m-d H:i:s`): Only include logs started on or before this date/time.
* `--limit|-l` (optional, default `10`): Number of commands to show in the per-command breakdown.

### Metrics

* **Total** – Number of logs matching the filter.
* **Successes** – Logs with `exitCode = 0`.
* **Failures** – Logs with `exitCode != 0`.
* **Unfinished** – Logs with `endTime IS NULL`. This means the command **never reached
  `console.terminate`**: it may have been killed, crashed, or it may simply still be running
  right now, at the moment the statistics were computed. There is no way to tell the two apart
  from this table alone.
* **Failure rate** – `Failures / Total`, computed in PHP so an empty result set never divides
  by zero.
* **Duration (avg / min / max)** – In milliseconds, computed only over logs that actually have
  a `durationMs` value. Unfinished logs and logs predating the schema update are excluded from
  these figures rather than treated as `0`.
* **Measured executions** – How many of the matched logs contributed a `durationMs` value to
  the duration figures above.
* **Breakdown by exit code** – Count of logs for each distinct, non-null `exitCode`.
* **Breakdown by command** – The same set of metrics grouped by `commandName`, sorted by total
  volume (descending) and capped at `--limit` entries.

These aggregations only use `COUNT`, `AVG`, `MIN`, `MAX` and `GROUP BY`, so they run unchanged
on every database engine this bundle supports — there is no median, no percentile, and no
time-bucketed series in this feature.

## REST API

The bundle can optionally expose the command log history over a read-only JSON-LD/Hydra REST API.
It is **disabled by default** and must be opted into explicitly.

> **:warning: Security warning**
>
> These endpoints expose the full command execution history, including command arguments. They
> ship with **no access control of any kind** — anyone who can reach the routes can read every
> logged command and its arguments. Protecting them is entirely the consuming application's
> responsibility: restrict the path with `access_control` in `security.yaml` (or an equivalent
> firewall rule) before exposing this API outside of trusted networks. This is the single most
> important thing to get right before enabling this section — read it before you do.
>
> **Enabling `api.enabled` may be all it takes to expose the routes.** On a Symfony 7.4 or 8.x
> application (any skeleton generated with the default `config/routes.yaml`, which imports
> `resource: routing.controllers`), that loader automatically routes the `#[Route]` attributes of
> **every controller registered as a service** — including this bundle's, the moment `api.enabled`
> turns it into one. The routes then appear in `debug:router` with **no import of
> `config/routes.yaml` required at all**. Symfony 6.4 skeletons scope that same loader to
> `App\Controller` only, so there the explicit import below is still what exposes the API. Either
> way, put the `access_control` rule in place *before* setting `api.enabled: true` in any
> environment you don't fully control — do not rely on "I haven't imported the routes yet".

### Enabling the API

Two things gate the API, and both are opt-in — and a third, protecting it, is on you:

**1. Turn on the API services** — this is the flag that matters most, since it is what can expose
the routes on its own on Symfony 7.4/8.x (see the warning above):

```yaml
# config/packages/command_logger.yaml
command_logger:
    api:
        enabled: true
```

**2. Find out where the routes actually are**, with `bin/console debug:router`. Do not assume —
the answer depends on your application's `config/routes.yaml`, and it decides which path you have
to protect.

*If they are already listed* (Symfony 7.4/8.x default skeleton), step 1 was enough: the
`routing.controllers` loader picked the controller up the moment it became a service, and the
endpoints are served at `/command-logs`. **Importing the routes file cannot move them.** That
import is read after the routes already exist, so a `prefix` set there is silently ignored — you
would get a 404 on the prefixed path while the real one stays open. Do not import anything.

*If nothing is listed* (Symfony 6.4 skeleton, or any application whose controller import is scoped
to `App\Controller`), import the routes yourself. Here the prefix does apply:

```yaml
# config/routes/command_logger.yaml
command_logger:
    resource: '@CommandLoggerBundle/config/routes.yaml'
    prefix: /api
```

**3. Protect the path `debug:router` printed, not the one you expected.** Guarding
`^/api/command-logs` while the routes are served at `/command-logs` leaves the API wide open and
looks protected, which is worse than leaving it visibly unprotected:

```yaml
# config/packages/security.yaml
security:
    access_control:
        # Match this against `debug:router` output. Without an import, that is /command-logs;
        # with one on a 6.4-style skeleton, it is your prefix + /command-logs.
        - { path: ^/command-logs, roles: ROLE_ADMIN }
```

### Endpoints

| Method | Path                    | Description                                                          |
|--------|-------------------------|------------------------------------------------------------------------|
| `GET`  | `/command-logs`         | Paginated, filterable collection of command logs (JSON-LD Hydra collection). |
| `GET`  | `/command-logs/stats`   | Aggregate statistics (summary, exit code breakdown, per-command breakdown) over the same filterable set of logs. |
| `GET`  | `/command-logs/{id}`    | A single command log, looked up by its numeric `id` or its `executionToken`. |

Route names are prefixed with `command_logger_api_` (e.g. `command_logger_api_list`,
`command_logger_api_stats`, `command_logger_api_item`), following Symfony's naming convention for
bundle-provided routes.

Like the rest of this REST API, `/command-logs/stats` exposes exactly the same aggregated data as
the `command-logger:stats` CLI command and the log list above — it stays behind the same
`command_logger.api.enabled` flag and the same security warning at the top of this section: put
your own `access_control` rule in place before enabling it.

### Query parameters

The parameters below apply to both `GET /command-logs` and `GET /command-logs/stats`, and can be
combined, unless stated otherwise. On `/command-logs/stats`, `page` is not used (there is nothing
to paginate) and `limit` instead caps how many commands appear in the per-command breakdown.

* `page` (optional, integer, default `1`) — Page number to return; must be positive. Ignored by `/stats`.
* `limit` (optional, integer, default `10`) — Number of entries per page; must be between `1` and `100`. On `/stats`, the number of commands returned in the per-command breakdown.
* `name` (optional, string, minimum length `2`) — Filters logs whose command name contains this value.
* `status` (optional, string, one of `success` or `error`) — Filters by outcome. Cannot be combined with `code`.
* `code` (optional, integer) — Filters by exact exit code. Cannot be combined with `status`.
* `from` (optional, string, `Y-m-d` or `Y-m-d H:i:s`) — Only logs started on or after this date/time.
* `to` (optional, string, `Y-m-d` or `Y-m-d H:i:s`) — Only logs started on or before this date/time. Must not be earlier than `from`.

Invalid or conflicting parameters return `422 Unprocessable Entity` with a
[Problem Details](https://www.rfc-editor.org/rfc/rfc9457) (`application/problem+json`) body listing
the offending `violations`, each with its `propertyPath`. Unknown ids return `404 Not Found` in the
same Problem Details format.

### Statistics response

`GET /command-logs/stats` wraps the same figures as the `command-logger:stats` CLI command in a
JSON-LD envelope: a `summary` (volume by outcome, failure rate, duration extrema), a `byExitCode`
breakdown, and a `byCommand` breakdown bounded by `limit`.

```json
{
    "@context": "/contexts/CommandLogStatistics",
    "@id": "/command-logs/stats",
    "@type": "CommandLogStatistics",
    "summary": {
        "total": 42,
        "successCount": 39,
        "failureCount": 3,
        "unfinishedCount": 0,
        "failureRate": 0.0714,
        "durationMs": {
            "avg": 182.4,
            "min": 12,
            "max": 950,
            "count": 42
        }
    },
    "byExitCode": {
        "0": 39,
        "1": 3
    },
    "byCommand": [
        {
            "commandName": "app:example",
            "total": 20,
            "successCount": 19,
            "failureCount": 1,
            "unfinishedCount": 0,
            "failureRate": 0.05,
            "durationMs": { "avg": 150.2, "min": 20, "max": 400, "count": 20 }
        }
    ]
}
```

## License
MIT License

