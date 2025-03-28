<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection;

use Ayaou\CommandLoggerBundle\DependencyInjection\CommandLoggerExtension;
use PHPUnit\Framework\TestCase;
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
    }

    public function testLoadWithCustomConfig(): void
    {
        $config = [
            'enabled'         => false,
            'purge_threshold' => 50,
        ];

        $this->extension->load([$config], $this->container);

        $this->assertFalse($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(50, $this->container->getParameter('command_logger.purge_threshold'));
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
