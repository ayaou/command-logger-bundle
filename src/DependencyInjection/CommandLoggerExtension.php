<?php

namespace Ayaou\CommandLoggerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CommandLoggerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration   = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $configs);

        $container->setParameter('command_logger.enabled', $processedConfig['enabled']);
        $container->setParameter('command_logger.purge_threshold', $processedConfig['purge_threshold']);
        $container->setParameter('command_logger.log_output', $processedConfig['log_output']);
        $container->setParameter('command_logger.log_errors', $processedConfig['log_errors']);
        $container->setParameter('command_logger.excluded_commands', $processedConfig['excluded_commands']);
        $container->setParameter('command_logger.included_commands', $processedConfig['included_commands']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'command_logger';
    }
}
