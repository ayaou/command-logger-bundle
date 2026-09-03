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

namespace Ayaou\CommandLoggerBundle\Tests\Integration\Api;

use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Tests\Integration\AppKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Functional coverage for the REST API exposed under src/Controller/Api/.
 *
 * Requests go through the real HTTP kernel (routing, argument resolvers,
 * ApiExceptionListener) via KernelInterface::handle(), exactly as they would in a real
 * application once it imports config/routes.yaml (see README.md). Symfony's browser-kit
 * component is not a dependency of this bundle, so WebTestCase's client is intentionally not
 * used here; driving the kernel directly needs nothing beyond what the bundle already requires.
 */
class CommandLogControllerTest extends AppKernelTestCase
{
    private KernelInterface $httpKernel;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->httpKernel = self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(CommandLog::class);
        $schemaTool->createSchema([$metadata]);
    }

    public function testListReturns200WithJsonLdCollectionOfLogs(): void
    {
        $this->persistLog('app:example', 0, 'token-a');
        $this->persistLog('app:other', 0, 'token-b');

        $response = $this->httpKernel->handle(Request::create('/command-logs'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame('hydra:Collection', $payload['@type']);
        $this->assertSame(2, $payload['hydra:totalItems']);
        $this->assertCount(2, $payload['hydra:member']);
    }

    public function testItemReturns200ForKnownId(): void
    {
        $log = $this->persistLog('app:example', 0, 'token-a');

        $response = $this->httpKernel->handle(Request::create('/command-logs/'.$log->getId()));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame('app:example', $payload['commandName']);
    }

    public function testItemExposesTheCapturedOutput(): void
    {
        $log = $this->persistLog('app:load', 0, 'token-output');
        $log->setOutput("[OK] 3 products loaded\n");
        $this->entityManager->flush();

        $response = $this->httpKernel->handle(Request::create('/command-logs/'.$log->getId()));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame("[OK] 3 products loaded\n", $payload['output']);
    }

    /**
     * The list endpoint is a listing, not a log viewer: putting arbitrarily long command
     * output on every row of every page would be paid for on requests that never wanted it.
     */
    public function testListDoesNotCarryTheCapturedOutput(): void
    {
        $log = $this->persistLog('app:load', 0, 'token-output-list');
        $log->setOutput("[OK] 3 products loaded\n");
        $this->entityManager->flush();

        $response = $this->httpKernel->handle(Request::create('/command-logs'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertArrayNotHasKey('output', $payload['hydra:member'][0]);
    }

    public function testItemReturns404ForUnknownId(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/999999'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame('about:blank', $payload['type']);
        $this->assertSame('Not Found', $payload['title']);
        $this->assertSame(404, $payload['status']);
        $this->assertNotEmpty($payload['detail']);
    }

    public function testListReturns422ForInvalidStatusFilter(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['status' => 'nimportequoi']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertNotEmpty($payload['violations']);
        $this->assertSame('status', $payload['violations'][0]['propertyPath']);
    }

    public function testListReturns422WhenStatusAndCodeAreCombined(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs', 'GET', [
            'status' => 'success',
            'code' => 0,
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertNotEmpty($payload['violations']);
        $this->assertSame('status', $payload['violations'][0]['propertyPath']);
    }

    public function testListReturns405ForUnsupportedMethod(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs', 'POST'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    #[DataProvider('provideLimitBoundaries')]
    public function testListLimitBoundaries(int $limit, int $expectedStatus): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['limit' => $limit]));

        $this->assertSame($expectedStatus, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideLimitBoundaries(): iterable
    {
        yield 'lower bound accepted' => [1, 200];
        yield 'upper bound accepted' => [100, 200];
        yield 'below lower bound rejected' => [0, 422];
        yield 'above upper bound rejected' => [101, 422];
    }

    public function testEmptyCollectionHydraLastPointsToPageOneNotZero(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(0, $payload['hydra:totalItems']);
        $this->assertStringContainsString('page=1', $payload['hydra:view']['hydra:last']);
        $this->assertStringNotContainsString('page=0', $payload['hydra:view']['hydra:last']);
    }

    public function testHydraViewOmitsPreviousAndNextWhenNotApplicable(): void
    {
        $this->persistLog('app:example', 0, 'token-a');
        $this->persistLog('app:other', 0, 'token-b');
        $this->persistLog('app:third', 0, 'token-c');

        $firstPage = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['limit' => 2, 'page' => 1]));
        $firstPayload = json_decode((string) $firstPage->getContent(), true);

        $this->assertSame(3, $firstPayload['hydra:totalItems']);
        $this->assertArrayNotHasKey('hydra:previous', $firstPayload['hydra:view']);
        $this->assertArrayHasKey('hydra:next', $firstPayload['hydra:view']);

        $lastPage = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['limit' => 2, 'page' => 2]));
        $lastPayload = json_decode((string) $lastPage->getContent(), true);

        $this->assertCount(1, $lastPayload['hydra:member']);
        $this->assertArrayHasKey('hydra:previous', $lastPayload['hydra:view']);
        $this->assertArrayNotHasKey('hydra:next', $lastPayload['hydra:view']);
    }

    public function testContextIsNotHardcodedToApiPrefix(): void
    {
        $log = $this->persistLog('app:example', 0, 'token-a');

        $itemResponse = $this->httpKernel->handle(Request::create('/command-logs/'.$log->getId()));
        $itemPayload = json_decode((string) $itemResponse->getContent(), true);

        $this->assertSame('/contexts/CommandLog', $itemPayload['@context']);

        $listResponse = $this->httpKernel->handle(Request::create('/command-logs'));
        $listPayload = json_decode((string) $listResponse->getContent(), true);

        $this->assertSame('/contexts/Collection', $listPayload['@context']);
    }

    public function testListFiltersByCommandName(): void
    {
        $this->persistLog('app:example', 0, 'token-a');
        $this->persistLog('app:other', 0, 'token-b');

        $response = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['name' => 'example']));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertCount(1, $payload['hydra:member']);
        $this->assertSame('app:example', $payload['hydra:member'][0]['commandName']);
    }

    /**
     * The route /{id} in item() carries no format constraint on {id}. Declaring stats()
     * after item() would let "/command-logs/stats" be captured as item(id: 'stats') instead,
     * silently returning 404 (findOneByIdOrToken('stats') matches nothing) rather than the
     * statistics payload. This test is the guard against that regression: it fails loudly if
     * stats() is ever reordered (or re-declared) after item().
     */
    public function testStatsPathIsNotSwallowedByItemRoute(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/stats'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame('CommandLogStatistics', $payload['@type']);
        $this->assertArrayHasKey('summary', $payload);
    }

    public function testStatsReturns200WithSummaryByExitCodeAndByCommandBreakdown(): void
    {
        $this->persistLog('app:example', 0, 'token-a', 100);
        $this->persistLog('app:example', 1, 'token-b', 300);
        $this->persistLog('app:other', 0, 'token-c', 200);

        $response = $this->httpKernel->handle(Request::create('/command-logs/stats'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $summary = $payload['summary'];
        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['successCount']);
        $this->assertSame(1, $summary['failureCount']);
        $this->assertSame(0, $summary['unfinishedCount']);
        $this->assertEqualsWithDelta(1 / 3, $summary['failureRate'], 0.0001);
        // A whole-number float (200.0) is encoded by json_encode() as "200", with no
        // fractional part, so it comes back from json_decode() as an int: compare
        // numerically rather than with assertSame(), which would fail on the type alone.
        $this->assertEqualsWithDelta(200.0, $summary['durationMs']['avg'], 0.001);
        $this->assertSame(100, $summary['durationMs']['min']);
        $this->assertSame(300, $summary['durationMs']['max']);
        $this->assertSame(3, $summary['durationMs']['count']);

        $this->assertSame(2, $payload['byExitCode'][0]);
        $this->assertSame(1, $payload['byExitCode'][1]);

        $this->assertCount(2, $payload['byCommand']);
        $this->assertSame('app:example', $payload['byCommand'][0]['commandName']);
        $this->assertSame(2, $payload['byCommand'][0]['total']);
    }

    public function testStatsFiltersByCommandName(): void
    {
        $this->persistLog('app:example', 0, 'token-a', 100);
        $this->persistLog('app:other', 0, 'token-b', 200);

        $response = $this->httpKernel->handle(Request::create('/command-logs/stats', 'GET', ['name' => 'example']));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(1, $payload['summary']['total']);
        $this->assertCount(1, $payload['byCommand']);
        $this->assertSame('app:example', $payload['byCommand'][0]['commandName']);
    }

    public function testStatsFiltersByStatus(): void
    {
        $this->persistLog('app:example', 0, 'token-a', 100);
        $this->persistLog('app:example', 1, 'token-b', 300);

        $response = $this->httpKernel->handle(Request::create('/command-logs/stats', 'GET', ['status' => 'error']));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(1, $payload['summary']['total']);
        $this->assertSame(1, $payload['summary']['failureCount']);
        $this->assertSame(0, $payload['summary']['successCount']);
    }

    public function testStatsReturns422ForInvalidFilter(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/stats', 'GET', ['status' => 'nimportequoi']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertNotEmpty($payload['violations']);
        $this->assertSame('status', $payload['violations'][0]['propertyPath']);
    }

    public function testStatsReturns405ForUnsupportedMethod(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/stats', 'POST'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function testStatsOnEmptyTableReturnsZeroesWithoutDivisionByZero(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/stats'));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        $summary = $payload['summary'];
        $this->assertSame(0, $summary['total']);
        $this->assertSame(0, $summary['successCount']);
        $this->assertSame(0, $summary['failureCount']);
        $this->assertSame(0, $summary['unfinishedCount']);
        // Same whole-number float caveat as durationMs.avg above: 0.0 round-trips through
        // JSON as an int 0.
        $this->assertEqualsWithDelta(0.0, $summary['failureRate'], 0.001);
        $this->assertNull($summary['durationMs']['avg']);
        $this->assertNull($summary['durationMs']['min']);
        $this->assertNull($summary['durationMs']['max']);
        $this->assertSame(0, $summary['durationMs']['count']);
        $this->assertSame([], $payload['byExitCode']);
        $this->assertSame([], $payload['byCommand']);
    }

    /**
     * $durationMs mirrors CommandTerminateListener: endTime, exitCode and durationMs are only
     * ever set together, once execution has finished. Leaving it null keeps the log
     * "unfinished" (endTime still null), exactly like a real in-flight command.
     */
    private function persistLog(string $commandName, int $exitCode, string $token, ?int $durationMs = null): CommandLog
    {
        $log = new CommandLog();
        $log->setCommandName($commandName)
            ->setStartTime(new \DateTimeImmutable('-1 day'))
            ->setExitCode($exitCode)
            ->setExecutionToken($token);

        if (null !== $durationMs) {
            $log->setEndTime(new \DateTimeImmutable())
                ->setDurationMs($durationMs);
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
