# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

Only the latest `1.x` release receives fixes. There is no long-term support branch: if you
report an issue against an older tag, the fix will land on the current release line.

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Use GitHub's private vulnerability reporting on this repository: go to the *Security* tab,
then *Report a vulnerability*. That channel is private until an advisory is published, and
publishing an advisory from it also notifies Packagist, so `composer audit` warns everyone
who depends on this bundle.

This bundle is maintained by one person on their own time. Expect an acknowledgement within
a few days rather than within hours, and a fix on a best-effort basis. If a report goes
unanswered for two weeks, feel free to ping it publicly without disclosing details.

## What this bundle stores, and why it matters

The bundle writes a row per console command execution into the `command_log` table. That row
holds the command name, its arguments and options, timings, exit code and, on failure, the
exception message and stack traces. Two consequences are worth stating plainly.

**Command arguments can carry secrets.** A `--password` or `--token` passed on the command
line would otherwise be stored in clear. Since 1.7.0 the values of parameters whose name
matches `sensitive_parameters` are replaced with `[REDACTED]` before being persisted. The
default list covers the usual names, but it is a name-based heuristic: review it against your
own commands, and keep in mind that a secret passed under an unusual name will not be caught.

**Stored stack traces can carry secrets too.** PHP includes call arguments in a trace, so a
secret handed to a method that then throws can reach `errorMessage` without ever passing
through the argument redaction. Marking those parameters `#[\SensitiveParameter]` in your own
code makes PHP 8.2 and later redact them at the source.

**The REST API exposes all of it.** It is disabled by default and must be enabled explicitly.
It carries no access control of its own: protecting it belongs to your application, through
`access_control` in `security.yaml` or an equivalent firewall rule. Treat those endpoints as
you would treat direct read access to the table.
