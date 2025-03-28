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
            ->end();

        return $treeBuilder;
    }
}
