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
                ->arrayNode('commands')
                    ->scalarPrototype()
                        ->info('List of commands to log. Example: ["app:example", "app:another-example"]')
                    ->end()
                ->defaultValue([])
            ->end();

        return $treeBuilder;
    }
}
