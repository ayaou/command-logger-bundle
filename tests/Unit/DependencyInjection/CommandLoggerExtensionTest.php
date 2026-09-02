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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection;

use Ayaou\CommandLoggerBundle\DependencyInjection\CommandLoggerExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        $this->assertEquals(
            ['password', 'passwd', 'secret', 'token', 'api-key', 'api_key', 'apikey', 'credential', 'auth'],
            $this->container->getParameter('command_logger.sensitive_parameters'),
        );
        $this->assertEquals(65535, $this->container->getParameter('command_logger.max_error_message_length'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $config = [
            'enabled' => false,
            'purge_threshold' => 50,
        ];

        $this->extension->load([$config], $this->container);

        $this->assertFalse($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(50, $this->container->getParameter('command_logger.purge_threshold'));
    }

    public function testLoadWithNegativePurgeThresholdFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "command_logger.purge_threshold". Should be greater than or equal to 1');

        $config = [
            'purge_threshold' => -1,
        ];

        $this->extension->load([$config], $this->container);
    }
}
