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
                ->end()
                ->arrayNode('sensitive_parameters')
                    ->scalarPrototype()
                        ->info('Case-insensitive substring matched against argument/option names, e.g. "password" also catches "db-password".')
                    ->end()
                    ->defaultValue(['password', 'passwd', 'secret', 'token', 'api-key', 'api_key', 'apikey', 'credential', 'auth'])
                    ->info('Argument/option names matching one of these substrings have their value replaced with [REDACTED] before being logged. Set to [] to disable redaction.')
                ->end()
                ->integerNode('max_error_message_length')
                    ->defaultValue(65535)
                    ->min(100)
                    ->info('Maximum byte length of the stored error message. Longer messages are truncated (multi-byte safe) and suffixed with " [truncated]".')
                ->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->info('Read-only JSON-LD/Hydra REST API exposing the command log history. See the "REST API" section of README.md.')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Registers the API services (controller, JsonLdFactory, exception listener). Disabled by default: on a Symfony 7.4/8.x skeleton using the default "routing.controllers" resource, merely registering the controller as a service is enough to expose its routes with no access control, so this must stay an explicit opt-in.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
