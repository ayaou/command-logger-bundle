<?php

declare(strict_types=1);

namespace Ayaou\CommandLoggerBundle\Tests;

use Ayaou\CommandLoggerBundle\CommandLoggerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollection;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new CommandLoggerBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Check for Doctrine ORM 3.x compatibility class/interface
        // Doctrine ORM 3 introduced the MappingAttribute interface (since ORM 2.14)
        // which helps differentiate from ORM 2.x configs.
        $isOrm3 = interface_exists(\Doctrine\ORM\Mapping\MappingAttribute::class);

        // Load services.yaml from root config/
        $rootDir = rtrim($this->getProjectDir(), '/');
        $loader->load($rootDir.'/config/services.yaml');

        $loader->load(function ($container) use ($isOrm3) {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'uid' => [
                    'default_uuid_version' => 7,
                    'time_based_uuid_version' => 7,
                ],
                'serializer' => [
                    'enabled' => true,
                ],
                'router' => [
                    'utf8' => true,
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ]);

            $doctrineConfig = [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'path' => ':memory:',
                    'charset' => 'UTF8',
                ],
                'orm' => [
                    'controller_resolver' => ['auto_mapping' => false],
                    'mappings' => [
                        'CommandLoggerBundle' => [
                            'is_bundle' => true,
                            'type' => 'attribute',
                            'dir' => 'src/Entity',
                            'prefix' => 'Ayaou\CommandLoggerBundle\Entity',
                            'alias' => 'CommandLogger',
                        ],
                    ],
                ],
            ];

            // --- CONDITIONAL ORM 2.x CONFIGURATION ---
            if (!$isOrm3) {
                // These options are REQUIRED for ORM 2.x (DoctrineBundle 2.x)
                // but are unrecognized/removed in ORM 3.x (DoctrineBundle 3.x)
                $doctrineConfig['orm'] = array_merge($doctrineConfig['orm'], [
                    'auto_generate_proxy_classes' => true,
                    'enable_lazy_ghost_objects' => true,
                ]);
            }
            // --- END CONDITIONAL CONFIGURATION ---

            $container->loadFromExtension('doctrine', $doctrineConfig);


            // This autowire is also ORM version dependent:
            // For ORM 3.x, you should use "doctrine.orm.default_metadata_driver"
            // For ORM 2.x, the default name is often just "doctrine.orm.default_metadata_driver"
            // but the proxy generation and entity manager class changed in ORM 3.

            // Since AttributeDriver exists in both, we only need conditional logic
            // if the service name changes or if specific ORM 2 arguments are needed.
            // Keeping it simple for now as the main error was configuration.
            $container->autowire('doctrine.orm.default_metadata_driver', AttributeDriver::class)
                ->setArguments([['%kernel.project_dir%/src/Entity'], true])
                ->setPublic(true);
        });
    }

    /**
     * Required by the 'router' config above.
     */
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        return new RouteCollection();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}
