<?php

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection;

use Ayaou\CommandLoggerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[]]);

        $this->assertEquals([
            'enabled' => true,
            'purge_threshold' => 100,
            'log_output' => true,
            'log_errors' => true,
            'excluded_commands' => [],
            'included_commands' => [],
        ], $config);
    }

    public function testCustomValidConfiguration(): void
    {
        $inputConfig = [
            'enabled' => false,
            'purge_threshold' => 30,
            'log_output' => false,
            'log_errors' => false,
            'excluded_commands' => ['app:test'],
            'included_commands' => [],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals([
            'enabled' => false,
            'purge_threshold' => 30,
            'log_output' => false,
            'log_errors' => false,
            'excluded_commands' => ['app:test'],
            'included_commands' => [],
        ], $config);
    }

    public function testIncludedCommandsOnly(): void
    {
        $inputConfig = [
            'included_commands' => ['app:my-command'],
            'excluded_commands' => [], // Explicitly empty to avoid conflict
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals([
            'enabled' => true,
            'purge_threshold' => 100,
            'log_output' => true,
            'log_errors' => true,
            'excluded_commands' => [],
            'included_commands' => ['app:my-command'],
        ], $config);
    }

    public function testExcludedAndIncludedCommandsTogetherThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot configure both "included_commands" and "excluded_commands" at the same time.');

        $inputConfig = [
            'excluded_commands' => ['cache:clear'],
            'included_commands' => ['app:my-command'],
        ];

        $this->processor->processConfiguration($this->configuration, [$inputConfig]);
    }

    public function testNegativePurgeThresholdThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "command_logger.purge_threshold". Should be greater than or equal to 1');

        $inputConfig = [
            'purge_threshold' => -1,
        ];

        $this->processor->processConfiguration($this->configuration, [$inputConfig]);
    }

    public function testZeroPurgeThresholdThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 0 is too small for path "command_logger.purge_threshold". Should be greater than or equal to 1');

        $inputConfig = [
            'purge_threshold' => 0,
        ];

        $this->processor->processConfiguration($this->configuration, [$inputConfig]);
    }

    public function testInvalidTypeForEnabledThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid type for path "command_logger.enabled". Expected "bool", but got "string"');

        $inputConfig = [
            'enabled' => 'yes',
        ];

        $this->processor->processConfiguration($this->configuration, [$inputConfig]);
    }

    public function testEmptyExcludedCommands(): void
    {
        $inputConfig = [
            'excluded_commands' => [],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals([], $config['excluded_commands']);
    }

    public function testEmptyIncludedCommands(): void
    {
        $inputConfig = [
            'included_commands' => [],
        ];

        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        $this->assertEquals([], $config['included_commands']);
    }
}