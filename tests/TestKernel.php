<?php

namespace Ayaou\CommandLoggerBundle\Tests;

use Ayaou\CommandLoggerBundle\CommandLoggerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

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
        // Load doctrine.yaml from root config/ (as per tree)
        $rootDir = rtrim($this->getProjectDir(), '/');
        $loader->load($rootDir.'/config/services.yaml');
        //        $loader->load($rootDir . '/config/packages/doctrine.yaml');

        $loader->load(function ($container) {
            $container->loadFromExtension('framework', [
                'secret'                => 'test',
                'test'                  => true,
                'http_method_override'  => false, // Symfony 6.1
                'handle_all_throwables' => true, // Symfony 6.4
                'php_errors'            => ['log' => true], // Symfony 6.4
                'uid'                   => [
                    'default_uuid_version'    => 7, // Symfony 6.4
                    'time_based_uuid_version' => 7, // Symfony 6.4
                ],
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver'  => 'pdo_sqlite',
                    'path'    => ':memory:',
                    'charset' => 'UTF8',
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'enable_lazy_ghost_objects'   => true, // Doctrine 2.11
                    'controller_resolver'         => ['auto_mapping' => false], // Doctrine 2.12
                    'mappings'                    => [
                        'CommandLoggerBundle' => [
                            'is_bundle' => true,
                            'type'      => 'attribute',
                            'dir'       => 'src/Entity',
                            'prefix'    => 'Ayaou\CommandLoggerBundle\Entity',
                            'alias'     => 'CommandLogger',
                        ],
                    ],
                ],
            ]);

            // Override the default metadata driver to set reportFieldsWhereDeclared
            $container->autowire('doctrine.orm.default_metadata_driver', AttributeDriver::class)
                ->setArguments([['%kernel.project_dir%/src/Entity'], true]) // Paths, reportFieldsWhereDeclared
                ->setPublic(true); // Ensure it’s accessible
        });
    }

    // Override to ensure correct config path for bundle extension
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
}
