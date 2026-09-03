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

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Service;

use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;
use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Service\JsonLdFactory;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pins the single source of truth for pagination.
 *
 * The paginator handed to JsonLdFactory is built from the CommandLogFilter that
 * #[MapQueryString] has already validated, so page and limit must be read from that filter and
 * never re-read from the raw query string. Going through HTTP cannot prove this: the two agree
 * by construction there, because validation rejects anything else before the factory runs. The
 * only way to tell them apart is to call the factory directly with a request whose query string
 * deliberately contradicts the filter.
 */
class JsonLdFactoryTest extends AppKernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(CommandLog::class)]);
    }

    public function testPaginationIsReadFromTheFilterNotFromTheQueryString(): void
    {
        $this->persistLog('app:one', 'token-1');
        $this->persistLog('app:two', 'token-2');

        $filter = new CommandLogFilter(page: 1, limit: 1);

        /** @var CommandLogRepository $repository */
        $repository = self::getContainer()->get(CommandLogRepository::class);
        $paginator = $repository->getPaginatedResults($filter);

        /** @var JsonLdFactory $factory */
        $factory = self::getContainer()->get(JsonLdFactory::class);

        // The query string says 99 items per page, the validated filter says 1. With two rows,
        // reading the filter yields two pages and therefore a "next" link; reading the query
        // string would yield a single page and no "next" link at all.
        $request = Request::create('/command-logs?limit=99&page=1');
        $request->attributes->set('_route', 'command_logger_api_list');

        $payload = json_decode(
            (string) $factory->createCollectionResponse($paginator, $request, $filter, ['command_log:list'])->getContent(),
            true,
        );

        self::assertSame(2, $payload['hydra:totalItems']);
        self::assertArrayHasKey(
            'hydra:next',
            $payload['hydra:view'],
            'Pagination was computed from the query string instead of the validated filter.',
        );
        self::assertStringContainsString('page=2', $payload['hydra:view']['hydra:last']);
    }

    private function persistLog(string $name, string $token): void
    {
        $log = (new CommandLog())
            ->setCommandName($name)
            ->setStartTime(new \DateTimeImmutable())
            ->setExecutionToken($token)
            ->setExitCode(0);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
