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
use Ayaou\CommandLoggerBundle\Entity\CommandLog;
use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Ayaou\CommandLoggerBundle\Service\JsonLdFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/command-logs', name: 'api_command_logs_')]
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
    public function item(
        #[MapEntity(expr: 'repository.findOneByIdOrToken(id)')]
        CommandLog $commandLog,
    ): JsonResponse {
        return $this->jsonLdFactory->createItemResponse(
            $commandLog,
            'api_command_logs_item',
            ['id' => $commandLog->getId()],
            ['command_log:item']
        );
    }
}
