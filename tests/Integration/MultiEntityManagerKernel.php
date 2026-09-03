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

namespace Ayaou\CommandLoggerBundle\Tests\Integration;

use Ayaou\CommandLoggerBundle\CommandLoggerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\MappingAttribute;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * A second, self-contained kernel used only by EntityManagerRoutingTest to prove that when
 * "command_logger.entity_manager" names a non-default entity manager, both the write path
 * (CommandLogWriter, via ManagerRegistry::getManager($name)) and the read path
 * (CommandLogRepository, via ServiceEntityRepository::getManagerForClass()) resolve to that
 * SAME entity manager - not the default one.
 *
 * Deliberately kept separate from TestKernel, which every other integration test relies on
 * and which must keep exercising the single-default-entity-manager (existing,
 * non-regression) path unchanged.
 */
class MultiEntityManagerKernel extends Kernel
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
        $isOrm3 = interface_exists(MappingAttribute::class);

        $loader->load(function ($container) use ($isOrm3) {
            $container->loadFromExtension('command_logger', [
                'entity_manager' => 'secondary',
            ]);

            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ]);

            $ormConfig = [
                'default_entity_manager' => 'default',
                'controller_resolver' => ['auto_mapping' => false],
                'entity_managers' => [
                    // Owns no CommandLog mapping at all - if the feature were broken and the
                    // writer/repository fell back to this manager instead of "secondary", any
                    // query against CommandLog would fail outright (unmapped class) rather
                    // than silently succeed against the wrong database.
                    'default' => [
                        'connection' => 'default',
                        'mappings' => [],
                    ],
                    'secondary' => [
                        'connection' => 'secondary',
                        'mappings' => [],
                    ],
                ],
            ];

            if (!$isOrm3) {
                $ormConfig = array_merge($ormConfig, [
                    'auto_generate_proxy_classes' => true,
                    'enable_lazy_ghost_objects' => true,
                ]);
            }

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => ['driver' => 'pdo_sqlite', 'path' => ':memory:', 'charset' => 'UTF8'],
                        'secondary' => ['driver' => 'pdo_sqlite', 'path' => ':memory:', 'charset' => 'UTF8'],
                    ],
                ],
                'orm' => $ormConfig,
            ]);
        });
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
