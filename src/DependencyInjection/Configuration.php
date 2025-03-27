<?php

namespace Ayaou\CommandLoggerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('command_logger');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable command logging')
                ->end()
                ->integerNode('purge_threshold')
                    ->defaultValue(100)
                    ->min(1)
                    ->info('Number of days to keep logs before purging')
                ->end()
                ->booleanNode('log_output')
                    ->defaultTrue()
                    ->info('Capture command output (may increase resource usage)')
                ->end()
                ->booleanNode('log_errors')
                    ->defaultTrue()
                    ->info('Log detailed error messages and stack traces')
                ->end()
                ->arrayNode('excluded_commands')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('Commands to exclude from logging (cannot be used with included_commands)')
                ->end()
                ->arrayNode('included_commands')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('Commands to include in logging (if set, only these will be logged; cannot be used with excluded_commands)')
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($config) {
                    return !empty($config['included_commands']) && !empty($config['excluded_commands']);
                })
                ->thenInvalid('You cannot configure both "included_commands" and "excluded_commands" at the same time.')
            ->end();

        return $treeBuilder;
    }
}
