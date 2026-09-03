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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection\Compiler;

use Ayaou\CommandLoggerBundle\DependencyInjection\Compiler\CommandLoggerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\InvokableCommand;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandLoggerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
    }

    public function testInvokableCommandWithAttributeIsCollected(): void
    {
        $this->container->register('invokable.with.logger', InvokableCommandWithLogger::class)
            ->addTag('console.command');

        $this->process();

        $this->assertContains('app:invokable-command', $this->attributedCommands());
    }

    public function testInvokableCommandWithoutAttributeIsNotCollected(): void
    {
        $this->container->register('invokable.without.logger', InvokableCommandWithoutLogger::class)
            ->addTag('console.command');

        $this->process();

        $this->assertSame([], $this->attributedCommands());
    }

    public function testClassExtendingCommandWithAttributeIsCollected(): void
    {
        $this->container->register('class.with.logger', ClassCommandWithLogger::class)
            ->addTag('console.command');

        $this->process();

        $this->assertContains('app:class-command', $this->attributedCommands());
    }

    public function testAliasesAreCollected(): void
    {
        $this->container->register('invokable.with.logger', InvokableCommandWithLogger::class)
            ->addTag('console.command');

        $this->process();

        $this->assertContains('app:invokable-alias', $this->attributedCommands());
    }

    public function testParameterIsEmptyArrayWhenNoCommandsAreConcerned(): void
    {
        $this->process();

        $this->assertSame([], $this->attributedCommands());
    }

    public function testResolvesClassFromContainerParameter(): void
    {
        $this->container->setParameter('invokable_class', InvokableCommandWithLogger::class);
        $this->container->register('invokable.with.logger', '%invokable_class%')
            ->addTag('console.command');

        $this->process();

        $this->assertContains('app:invokable-command', $this->attributedCommands());
    }

    /**
     * Reproduces the reported bug's exact mechanism: Symfony's own AddConsoleCommandPass
     * rewrites an invokable-style "console.command" service's class to Command::class once it
     * runs, which is why reflecting on the runtime Command instance can never see
     * #[CommandLogger] again. CommandLoggerPass must run first and capture the name before that
     * happens - this test proves the captured name survives the rewrite, exactly as
     * CommandLoggerBundle::build() orders the two passes.
     */
    public function testAttributedCommandNameSurvivesAddConsoleCommandPassRewrite(): void
    {
        if (!class_exists(InvokableCommand::class)) {
            self::markTestSkipped(
                'Invokable commands landed in Symfony 7.3. On earlier branches AddConsoleCommandPass '
                .'rejects any console.command service that does not extend Command, so the rewrite '
                .'this test covers cannot happen there.',
            );
        }

        $this->container->register('invokable.with.logger', InvokableCommandWithLogger::class)
            ->addTag('console.command');

        $this->process();
        (new AddConsoleCommandPass())->process($this->container);

        $this->assertContains('app:invokable-command', $this->attributedCommands());
        $this->assertContains('app:invokable-alias', $this->attributedCommands());

        // Confirm the premise: the class really was rewritten to Command::class.
        $rewrittenId = 'invokable.with.logger.command';
        $this->assertTrue($this->container->hasDefinition($rewrittenId));
        $this->assertSame(Command::class, $this->container->getDefinition($rewrittenId)->getClass());
    }

    private function process(): void
    {
        (new CommandLoggerPass())->process($this->container);
    }

    /**
     * @return array<int, string>
     */
    private function attributedCommands(): array
    {
        return $this->container->getParameter('command_logger.attributed_commands');
    }
}
