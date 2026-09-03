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

namespace Ayaou\CommandLoggerBundle\Util;

/**
 * Masks the value of command arguments/options whose name looks sensitive (password,
 * token, ...) before they are persisted, so secrets never reach the command_log table.
 *
 * The configurable surface is the sensitive_parameters list, not this class.
 *
 * @internal
 */
class SensitiveParameterRedactor
{
    private const REDACTED_VALUE = '[REDACTED]';

    /**
     * @var array<int, string>
     */
    private array $sensitiveParameters;

    /**
     * @param array<int, string> $sensitiveParameters Case-insensitive substrings matched
     *                                                against parameter names. An empty
     *                                                array disables redaction entirely.
     */
    public function __construct(array $sensitiveParameters)
    {
        $this->sensitiveParameters = $sensitiveParameters;
    }

    /**
     * Replaces the value of every parameter whose name contains one of the configured
     * sensitive substrings (case-insensitive) with the [REDACTED] placeholder. Parameter
     * names are left untouched, only the value is masked.
     *
     * The #[\SensitiveParameter] attribute below has no effect on PHP 8.1 (the bundle's
     * floor); it only activates on PHP 8.2+, where it hides this array's raw values -
     * secrets included - from any exception stack trace generated while this method runs.
     *
     * @param array<int|string, mixed> $parameters
     *
     * @return array<int|string, mixed>
     */
    public function redact(#[\SensitiveParameter] array $parameters): array
    {
        if ([] === $this->sensitiveParameters) {
            return $parameters;
        }

        foreach ($parameters as $name => $value) {
            if ($this->isSensitive((string) $name)) {
                $parameters[$name] = self::REDACTED_VALUE;
            }
        }

        return $parameters;
    }

    private function isSensitive(string $parameterName): bool
    {
        foreach ($this->sensitiveParameters as $pattern) {
            if ('' !== $pattern && false !== stripos($parameterName, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
