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

use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommand;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommandWithConfigureName;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommandWithoutAttribute;
use Ayaou\CommandLoggerBundle\Tests\Unit\EventListener\TestCommandWithoutName;
use Ayaou\CommandLoggerBundle\Util\SupportedCommandResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

class SupportedCommandResolverTest extends TestCase
{
    public function testSupportsCommandWithExactConfigMatch(): void
    {
        $resolver = new SupportedCommandResolver(['app:my-command']);

        $this->assertTrue($resolver->supports(new TestCommand()));
    }

    public function testSupportsCommandWithWildcardConfigMatch(): void
    {
        $resolver = new SupportedCommandResolver(['app:*']);

        $this->assertTrue($resolver->supports(new TestCommand()));
    }

    public function testSupportsCommandWithCommandLoggerAttribute(): void
    {
        $resolver = new SupportedCommandResolver([]);

        $this->assertTrue($resolver->supports(new TestCommand()));
    }

    public function testDoesNotSupportCommandWithoutCommandLoggerAttribute(): void
    {
        $resolver = new SupportedCommandResolver([]);

        $this->assertFalse($resolver->supports(new TestCommandWithoutAttribute()));
    }

    public function testDoesNotSupportCommandWithNoName(): void
    {
        $resolver = new SupportedCommandResolver(['app:my-command-without-name']);

        $this->assertFalse($resolver->supports(new TestCommandWithoutName()));
    }

    public function testDoesNotSupportLazyCommandWithEmptyCommandMap(): void
    {
        $resolver = new SupportedCommandResolver([]);
        $lazyCommand = new LazyCommand('app:my-command', [], '', false, fn () => new TestCommand());

        $this->assertFalse($resolver->supports($lazyCommand));
    }

    public function testSupportsCommandFromAttributedCommandsList(): void
    {
        $resolver = new SupportedCommandResolver([], ['app:command-without-attribute']);

        $this->assertTrue($resolver->supports(new TestCommandWithoutAttribute()));
    }

    public function testReflectionFallbackAppliesWhenCommandIsNotInAttributedCommandsList(): void
    {
        $resolver = new SupportedCommandResolver([], ['some:other-command']);

        $this->assertTrue($resolver->supports(new TestCommandWithConfigureName()));
    }

    public function testWildcardMatchesCommandName(): void
    {
        $resolver = new SupportedCommandResolver(['app:*']);

        $this->assertTrue($resolver->supports(new Command('app:test')));
    }

    public function testWildcardDoesNotMatchDifferentNamespace(): void
    {
        $resolver = new SupportedCommandResolver(['app:*']);

        $this->assertFalse($resolver->supports(new Command('other:test')));
    }

    public function testWildcardMatchesComplexPattern(): void
    {
        $resolver = new SupportedCommandResolver(['app:test.*']);

        $this->assertTrue($resolver->supports(new Command('app:test.command')));
    }
}
