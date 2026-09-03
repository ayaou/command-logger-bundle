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

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Util\CommandLogWriter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the assumption the whole "configurable entity manager" feature rests on: once
 * "command_logger.entity_manager" names a manager other than the default one, BOTH the
 * write path (CommandLogWriter) and the read path (CommandLogRepository, through
 * ServiceEntityRepository::getManagerForClass()) resolve to that SAME named manager -
 * neither silently falls back to the default one.
 *
 * The proof is deliberately structural, not just behavioural: the "default" entity manager
 * in MultiEntityManagerKernel owns no CommandLog mapping whatsoever, so if either path
 * resolved to it by mistake, this test would fail loudly (an unmapped-class exception, or a
 * row that can never be found) rather than passing by accident.
 */
class EntityManagerRoutingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return MultiEntityManagerKernel::class;
    }

    public function testRepositoryAndWriterResolveTheSameNamedEntityManagerAsTheMapping(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        $defaultManager = $registry->getManager('default');
        $secondaryManager = $registry->getManager('secondary');
        self::assertNotSame($defaultManager, $secondaryManager);

        // This is exactly the resolution ServiceEntityRepository::__construct() performs
        // internally - proving it lands on "secondary" proves the repository will too.
        self::assertSame(
            $secondaryManager,
            $registry->getManagerForClass(CommandLog::class),
            'CommandLogRepository resolves its manager through ManagerRegistry::getManagerForClass(), which must follow the mapping to "secondary".',
        );

        self::assertInstanceOf(EntityManagerInterface::class, $secondaryManager);
        $schemaTool = new SchemaTool($secondaryManager);
        $schemaTool->createSchema([$secondaryManager->getClassMetadata(CommandLog::class)]);

        $writer = $container->get(CommandLogWriter::class);
        self::assertInstanceOf(CommandLogWriter::class, $writer);
        $writer->create('app:test', [], new \DateTimeImmutable(), 'routing-token');

        $repository = $container->get(CommandLogRepository::class);
        self::assertInstanceOf(CommandLogRepository::class, $repository);

        $log = $repository->findOneBy(['executionToken' => 'routing-token']);

        self::assertNotNull($log, 'The row written through CommandLogWriter must be visible through CommandLogRepository.');
        self::assertSame('app:test', $log->getCommandName());
    }
}
