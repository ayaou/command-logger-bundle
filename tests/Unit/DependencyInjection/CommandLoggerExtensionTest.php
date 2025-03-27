<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection;

use Ayaou\CommandLoggerBundle\DependencyInjection\CommandLoggerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandLoggerExtensionTest extends TestCase
{
    private CommandLoggerExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CommandLoggerExtension();
        $this->container = new ContainerBuilder();
    }

    public function testAlias(): void
    {
        $this->assertEquals('command_logger', $this->extension->getAlias());
    }

    public function testLoadWithDefaultConfig(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertTrue($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(100, $this->container->getParameter('command_logger.purge_threshold'));
        $this->assertTrue($this->container->getParameter('command_logger.log_output'));
        $this->assertTrue($this->container->getParameter('command_logger.log_errors'));
        $this->assertEquals([], $this->container->getParameter('command_logger.excluded_commands'));
        $this->assertEquals([], $this->container->getParameter('command_logger.included_commands'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $config = [
            'enabled' => false,
            'purge_threshold' => 50,
            'log_output' => false,
            'log_errors' => false,
            'excluded_commands' => ['app:test'],
            'included_commands' => [],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertFalse($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(50, $this->container->getParameter('command_logger.purge_threshold'));
        $this->assertFalse($this->container->getParameter('command_logger.log_output'));
        $this->assertFalse($this->container->getParameter('command_logger.log_errors'));
        $this->assertEquals(['app:test'], $this->container->getParameter('command_logger.excluded_commands'));
        $this->assertEquals([], $this->container->getParameter('command_logger.included_commands'));
    }

    public function testLoadWithIncludedCommands(): void
    {
        $config = [
            'included_commands' => ['app:my-command'],
        ];

        $this->extension->load([$config], $this->container);

        $this->assertTrue($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(100, $this->container->getParameter('command_logger.purge_threshold'));
        $this->assertTrue($this->container->getParameter('command_logger.log_output'));
        $this->assertTrue($this->container->getParameter('command_logger.log_errors'));
        $this->assertEquals([], $this->container->getParameter('command_logger.excluded_commands'));
        $this->assertEquals(['app:my-command'], $this->container->getParameter('command_logger.included_commands'));
    }

    public function testLoadWithBothIncludedAndExcludedCommandsFails(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot configure both "included_commands" and "excluded_commands" at the same time.');

        $config = [
            'excluded_commands' => ['cache:clear'],
            'included_commands' => ['app:my-command'],
        ];

        $this->extension->load([$config], $this->container);
    }

    public function testLoadWithNegativePurgeThresholdFails(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "command_logger.purge_threshold". Should be greater than or equal to 1');

        $config = [
            'purge_threshold' => -1,
        ];

        $this->extension->load([$config], $this->container);
    }
}