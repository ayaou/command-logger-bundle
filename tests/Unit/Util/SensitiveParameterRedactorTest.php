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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\Util;

use Ayaou\CommandLoggerBundle\Util\SensitiveParameterRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveParameterRedactorTest extends TestCase
{
    public function testRedactsExactMatchParameterName(): void
    {
        $redactor = new SensitiveParameterRedactor(['password']);

        $result = $redactor->redact(['password' => 'hunter2', 'username' => 'ada']);

        $this->assertSame(['password' => '[REDACTED]', 'username' => 'ada'], $result);
    }

    public function testRedactsWhenPatternIsSubstringOfParameterName(): void
    {
        $redactor = new SensitiveParameterRedactor(['password']);

        $result = $redactor->redact(['db-password' => 'hunter2', 'passwordFile' => '/etc/secret']);

        $this->assertSame('[REDACTED]', $result['db-password']);
        $this->assertSame('[REDACTED]', $result['passwordFile']);
    }

    public function testRedactsMatchCaseInsensitively(): void
    {
        $redactor = new SensitiveParameterRedactor(['password']);

        $result = $redactor->redact(['PASSWORD' => 'hunter2']);

        $this->assertSame(['PASSWORD' => '[REDACTED]'], $result);
    }

    public function testDoesNotRedactNonSensitiveParameterName(): void
    {
        $redactor = new SensitiveParameterRedactor(['password', 'token']);

        $result = $redactor->redact(['username' => 'ada', 'verbose' => true]);

        $this->assertSame(['username' => 'ada', 'verbose' => true], $result);
    }

    public function testEmptyPatternListDisablesRedaction(): void
    {
        $redactor = new SensitiveParameterRedactor([]);

        $result = $redactor->redact(['password' => 'hunter2', 'token' => 'abc123']);

        $this->assertSame(['password' => 'hunter2', 'token' => 'abc123'], $result);
    }

    public function testPreservesParameterNamesOnlyMasksValues(): void
    {
        $redactor = new SensitiveParameterRedactor(['secret']);

        $result = $redactor->redact(['api-secret' => 'value']);

        $this->assertArrayHasKey('api-secret', $result);
        $this->assertSame('[REDACTED]', $result['api-secret']);
    }
}
