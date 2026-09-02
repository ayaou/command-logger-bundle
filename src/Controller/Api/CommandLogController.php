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
#[Route('/command-logs', name: 'command_logger_api_')]
class CommandLogController extends AbstractController
{
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
            ['command_log:list']
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
            ['command_log:item']
        );
    }
}
