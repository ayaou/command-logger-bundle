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

namespace Ayaou\CommandLoggerBundle\Controller\Api;

use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Service\JsonLdFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

// Route names are prefixed with "command_logger_", the bundle alias, as required by the
// Symfony best practices for bundle-provided routes. The path itself carries no prefix: the
// consuming application chooses one when it imports config/routes.yaml (see README.md).
#[Route(self::PATH, name: 'command_logger_api_')]
class CommandLogController extends AbstractController
{
    /**
     * The path this controller mounts itself on, below whatever prefix the application
     * chose when importing config/routes.yaml. JsonLdFactory strips it back off to recover
     * that prefix, so the two must never drift apart - hence a shared constant.
     */
    public const PATH = '/command-logs';

    public function __construct(
        private readonly CommandLogRepository $repository,
        private readonly JsonLdFactory $jsonLdFactory,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CommandLogFilter $filter = new CommandLogFilter(),
    ): JsonResponse {
        $paginator = $this->repository->getPaginatedResults($filter);

        return $this->jsonLdFactory->createCollectionResponse(
            $paginator,
            $request,
            $filter,
            ['command_log:list'],
        );
    }

    // Declared before item() on purpose. Attribute routes are registered in method
    // declaration order, and item()'s "/{id}" carries no format constraint on {id} - if this
    // method were declared after item(), "/command-logs/stats" would be captured by "/{id}"
    // first (findOneByIdOrToken('stats') matches nothing, so it would 404 instead of
    // returning statistics). See testStatsPathIsNotSwallowedByItemRoute().
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CommandLogFilter $filter = new CommandLogFilter(),
    ): JsonResponse {
        $statistics = $this->repository->getStatistics($filter);
        $byCommand = $this->repository->getStatisticsByCommand($filter, $filter->limit);

        $summary = $statistics;
        unset($summary['byExitCode']);

        return $this->jsonLdFactory->createArrayResponse(
            [
                'summary' => $summary,
                'byExitCode' => $statistics['byExitCode'],
                'byCommand' => $byCommand,
            ],
            'CommandLogStatistics',
            'command_logger_api_stats',
        );
    }

    #[Route('/{id}', name: 'item', methods: ['GET'])]
    public function item(string $id): JsonResponse
    {
        // #[MapEntity(expr: ...)] was dropped: it requires symfony/expression-language, which
        // this bundle does not depend on. Looking the log up directly keeps one dependency less.
        $commandLog = $this->repository->findOneByIdOrToken($id);

        if (null === $commandLog) {
            throw $this->createNotFoundException();
        }

        return $this->jsonLdFactory->createItemResponse(
            $commandLog,
            'command_logger_api_item',
            ['id' => $commandLog->getId()],
            ['command_log:item'],
        );
    }
}
