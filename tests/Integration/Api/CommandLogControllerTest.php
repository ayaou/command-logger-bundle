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

    public function testItemReturns404ForUnknownId(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs/999999'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testListReturns422ForInvalidStatusFilter(): void
    {
        $response = $this->httpKernel->handle(Request::create('/command-logs', 'GET', ['status' => 'nimportequoi']));

        $this->assertSame(422, $response->getStatusCode());
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

    private function persistLog(string $commandName, int $exitCode, string $token): CommandLog
    {
        $log = new CommandLog();
        $log->setCommandName($commandName)
            ->setStartTime(new \DateTimeImmutable('-1 day'))
            ->setExitCode($exitCode)
            ->setExecutionToken($token);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
