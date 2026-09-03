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

namespace Ayaou\CommandLoggerBundle\Tests\Unit\DependencyInjection;

use Ayaou\CommandLoggerBundle\Controller\Api\CommandLogController;
use Ayaou\CommandLoggerBundle\DependencyInjection\CommandLoggerExtension;
use Ayaou\CommandLoggerBundle\EventListener\Api\ApiExceptionListener;
use Ayaou\CommandLoggerBundle\Service\JsonLdFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandLoggerExtensionTest extends TestCase
{
    private CommandLoggerExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CommandLoggerExtension();
        $this->container = new ContainerBuilder();
        $this->container->registerExtension($this->extension);
    }

    public function testAlias(): void
    {
        $this->assertEquals('command_logger', $this->extension->getAlias());
    }

    public function testLoadWithDefaultConfig(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertTrue($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(100, $this->container->getParameter('command_logger.purge_threshold'));
        $this->assertEquals(
            ['password', 'passwd', 'secret', 'token', 'api-key', 'api_key', 'apikey', 'credential', 'auth'],
            $this->container->getParameter('command_logger.sensitive_parameters'),
        );
        $this->assertEquals(65535, $this->container->getParameter('command_logger.max_error_message_length'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $config = [
            'enabled' => false,
            'purge_threshold' => 50,
        ];

        $this->extension->load([$config], $this->container);

        $this->assertFalse($this->container->getParameter('command_logger.enabled'));
        $this->assertEquals(50, $this->container->getParameter('command_logger.purge_threshold'));
    }

    public function testLoadWithNegativePurgeThresholdFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value -1 is too small for path "command_logger.purge_threshold". Should be greater than or equal to 1');

        $config = [
            'purge_threshold' => -1,
        ];

        $this->extension->load([$config], $this->container);
    }

    /**
     * The whole point of "api.enabled" defaulting to false: with the default configuration, the
     * container must not contain any of the bundle's API services. Otherwise, a Symfony 7.4/8.x
     * skeleton using the default "routing.controllers" resource would auto-expose the controller's
     * routes the moment it becomes a service - with no access control and without the consuming
     * application importing anything.
     */
    public function testApiServicesAreNotRegisteredByDefault(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertFalse($this->container->hasDefinition(CommandLogController::class));
        $this->assertFalse($this->container->hasDefinition(JsonLdFactory::class));
        $this->assertFalse($this->container->hasDefinition(ApiExceptionListener::class));
    }

    public function testApiServicesAreRegisteredWhenApiEnabled(): void
    {
        $this->extension->load([['api' => ['enabled' => true]]], $this->container);

        $this->assertTrue($this->container->hasDefinition(CommandLogController::class));
        $this->assertTrue($this->container->hasDefinition(JsonLdFactory::class));
        $this->assertTrue($this->container->hasDefinition(ApiExceptionListener::class));
    }

    public function testEntityManagerParameterDefaultsToNull(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertNull($this->container->getParameter('command_logger.entity_manager'));
    }

    public function testEntityManagerParameterIsSetWhenConfigured(): void
    {
        $this->extension->load([['entity_manager' => 'reporting']], $this->container);

        $this->assertSame('reporting', $this->container->getParameter('command_logger.entity_manager'));
    }

    /**
     * With no "entity_manager" configured, prepend() must inject the mapping in exactly the
     * same shape it always has: the short "orm.mappings" form targeting the default entity
     * manager. This is the non-regression guarantee for every existing installation.
     */
    public function testPrependWithDefaultEntityManagerInjectsShortFormMapping(): void
    {
        $this->container->registerExtension(new FakeDoctrineExtension());
        $this->container->loadFromExtension('command_logger', []);

        $this->extension->prepend($this->container);

        $this->assertSame([
            [
                'orm' => [
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
            ],
        ], $this->container->getExtensionConfig('doctrine'));
    }

    /**
     * With a named "entity_manager", the mapping must be injected under
     * "orm.entity_managers.<name>.mappings" instead - and NOT under the short "orm.mappings"
     * form, since DoctrineBundle refuses to see both at once.
     */
    public function testPrependWithNamedEntityManagerInjectsLongFormMapping(): void
    {
        $this->container->registerExtension(new FakeDoctrineExtension());
        $this->container->loadFromExtension('command_logger', ['entity_manager' => 'reporting']);

        $this->extension->prepend($this->container);

        $this->assertSame([
            [
                'orm' => [
                    'entity_managers' => [
                        'reporting' => [
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
                    ],
                ],
            ],
        ], $this->container->getExtensionConfig('doctrine'));

        $this->assertArrayNotHasKey('mappings', $this->container->getExtensionConfig('doctrine')[0]['orm']);
    }

    public function testPrependDoesNothingWithoutDoctrineExtension(): void
    {
        $this->extension->prepend($this->container);

        $this->assertSame([], $this->container->getExtensionConfig('doctrine'));
    }
}
