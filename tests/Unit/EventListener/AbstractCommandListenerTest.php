<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\EventListener;

use Ayaou\CommandLoggerBundle\EventListener\CommandStartListener;
use Ayaou\CommandLoggerBundle\Util\CommandExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LazyCommand;

class AbstractCommandListenerTest extends TestCase
{
    private CommandStartListener $listener;

    private EntityManagerInterface|MockObject $entityManager;

    private CommandExecutionTracker|MockObject $commandExecutionTracker;

    private iterable $commandMap;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager           = $this->createMock(EntityManagerInterface::class);
        $this->commandExecutionTracker = $this->createMock(CommandExecutionTracker::class);
        $this->commandMap              = [];
        $this->listener                = new CommandStartListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            $this->commandMap,
            true, // enabled
            [], // otherCommands
        );
    }

    public function testSupportsCommandWithExactConfigMatch(): void
    {
        $command = new TestCommand();
        $result  = $this->invokeIsSupportedCommand($command, ['app:my-command']);

        $this->assertTrue($result);
    }

    public function testSupportsCommandWithWildcardConfigMatch(): void
    {
        $command = new TestCommand();
        $result  = $this->invokeIsSupportedCommand($command, ['app:*']);

        $this->assertTrue($result);
    }

    public function testSupportsCommandWithCommandLoggerAttribute(): void
    {
        $command = new TestCommand();
        $result  = $this->invokeIsSupportedCommand($command, []);

        $this->assertTrue($result);
    }

    public function testDoesNotSupportCommandWithoutCommandLoggerAttribute(): void
    {
        $command = new TestCommandWithoutAttribute();
        $result  = $this->invokeIsSupportedCommand($command, []);

        $this->assertFalse($result);
    }

    public function testDoesNotSupportCommandWithNoName(): void
    {
        $command = new TestCommandWithoutName();
        $result  = $this->invokeIsSupportedCommand($command, ['app:my-command-without-name']);

        $this->assertFalse($result);
    }

    public function testSupportsLazyCommandWithCommandLoggerAttributeInCommandMap(): void
    {
        $lazyCommand      = new LazyCommand('app:my-command', [], '', false, fn () => new TestCommand());
        $this->commandMap = ['app:my-command' => new TestCommand()];
        $this->listener   = new CommandStartListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            $this->commandMap,
            true,
            [],
        );

        $result = $this->invokeIsSupportedCommand($lazyCommand, []);

        $this->assertTrue($result);
    }

    public function testDoesNotSupportLazyCommandWithEmptyCommandMapExposesBug(): void
    {
        $lazyCommand      = new LazyCommand('app:my-command', [], '', false, fn () => new TestCommand());
        $this->commandMap = [];
        $this->listener   = new CommandStartListener(
            $this->entityManager,
            $this->commandExecutionTracker,
            $this->commandMap,
            true,
            [],
        );

        $result = $this->invokeIsSupportedCommand($lazyCommand, []);

        $this->assertFalse($result, 'LazyCommand not supported due to empty commandMap, indicating a potential bug.');
    }

    public function testWildcardMatchesCommandName(): void
    {
        $result = $this->invokeMethod($this->listener, 'matchWithWildcard', ['app:*', 'app:test']);

        $this->assertTrue($result);
    }

    public function testWildcardDoesNotMatchDifferentNamespace(): void
    {
        $result = $this->invokeMethod($this->listener, 'matchWithWildcard', ['app:*', 'other:test']);

        $this->assertFalse($result);
    }

    public function testWildcardMatchesComplexPattern(): void
    {
        $result = $this->invokeMethod($this->listener, 'matchWithWildcard', ['app:test.*', 'app:test.command']);

        $this->assertTrue($result);
    }

    private function invokeIsSupportedCommand($command, array $otherCommands)
    {
        return $this->invokeMethod($this->listener, 'isSupportedCommand', [$command, $otherCommands]);
    }

    /**
     * @throws \ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method     = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
