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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CommandLoggerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $configs);

        $container->setParameter('command_logger.enabled', $processedConfig['enabled']);
        $container->setParameter('command_logger.purge_threshold', $processedConfig['purge_threshold']);
        $container->setParameter('command_logger.commands', $processedConfig['commands']);
        $container->setParameter('command_logger.sensitive_parameters', $processedConfig['sensitive_parameters']);
        $container->setParameter('command_logger.max_error_message_length', $processedConfig['max_error_message_length']);
        $container->setParameter('command_logger.entity_manager', $processedConfig['entity_manager']);

        // Default value for the parameter that CommandLoggerPass populates at compile time
        // (see CommandLoggerBundle::build()). It must exist here already: services.yaml
        // references it as "%command_logger.attributed_commands%", and Symfony's own
        // ResolveParameterPlaceHoldersPass (TYPE_OPTIMIZE) checks that every referenced
        // parameter exists *before* CommandLoggerPass (TYPE_BEFORE_REMOVING) has a chance to
        // set its real value - an undeclared parameter here fails the container compilation.
        // Because the parameter is array-valued, Symfony intentionally leaves the placeholder
        // unresolved in the service argument (see ResolveParameterPlaceHoldersPass's
        // $resolveArrays flag) instead of inlining it, so the value CommandLoggerPass sets
        // later is still the one that reaches the listeners.
        $container->setParameter('command_logger.attributed_commands', []);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        // config/api.yaml declares the API's controller, JsonLdFactory and exception listener.
        // Loading it only when explicitly enabled is what keeps CommandLogController from
        // existing as a service at all otherwise - see config/services.yaml's exclude list and
        // the "REST API" section of README.md for why that matters on Symfony 7.4/8.x.
        if ($processedConfig['api']['enabled']) {
            $loader->load('api.yaml');
        }
    }

    public function getAlias(): string
    {
        return 'command_logger';
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $configuration = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $container->getExtensionConfig($this->getAlias()));

        $mapping = [
            'is_bundle' => true,
            'type' => 'attribute',
            'dir' => 'src/Entity',
            'prefix' => 'Ayaou\CommandLoggerBundle\Entity',
            'alias' => 'CommandLogger',
        ];

        $entityManagerName = $processedConfig['entity_manager'];

        // Null (the default) keeps this in exactly the shape it always had: the short
        // "orm.mappings" form, which targets the default entity manager. A named entity
        // manager instead targets "orm.entity_managers.<name>.mappings" - the long form.
        // DoctrineBundle refuses to see both forms at once, so which one gets written here
        // must depend on whether a name was configured, not be added unconditionally.
        if (null === $entityManagerName) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'CommandLoggerBundle' => $mapping,
                    ],
                ],
            ]);

            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'entity_managers' => [
                    $entityManagerName => [
                        'mappings' => [
                            'CommandLoggerBundle' => $mapping,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
