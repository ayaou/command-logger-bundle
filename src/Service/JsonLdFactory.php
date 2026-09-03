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

namespace Ayaou\CommandLoggerBundle\Service;

use Ayaou\CommandLoggerBundle\Controller\Api\CommandLogController;
use Ayaou\CommandLoggerBundle\Dto\CommandLogFilter;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class JsonLdFactory
{
    public function __construct(
        private readonly NormalizerInterface $serializer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Builds a JSON-LD Item response.
     *
     * @param array<string, mixed> $routeParams
     * @param array<string>        $groups
     *
     * @throws ExceptionInterface
     */
    public function createItemResponse(
        mixed $data,
        string $route,
        array $routeParams = [],
        array $groups = [],
    ): JsonResponse {
        $context = $this->getContextName($data);

        // We temporarily wrap the data to add JSON-LD fields without dirtying the Entity
        // Note: Real API Platform uses Normalizers, but this is faster for simple bundles
        $payload = [
            '@context' => $this->getApiBasePath()."/contexts/$context",
            '@id' => $this->urlGenerator->generate($route, $routeParams),
            '@type' => $context,
            // Merge the actual entity data after serialization to avoid recursion issues
            ...$this->normalize($data, $groups),
        ];

        return new JsonResponse($payload);
    }

    /**
     * Builds a JSON-LD Collection response with Pagination (Hydra).
     *
     * @param Paginator<mixed> $paginator
     * @param array<string>    $groups
     *
     * @throws ExceptionInterface
     */
    public function createCollectionResponse(
        Paginator $paginator,
        Request $request,
        CommandLogFilter $filter,
        array $groups = [],
    ): JsonResponse {
        $totalItems = count($paginator);
        // $filter is the very same CommandLogFilter already validated by #[MapQueryString]
        // (see CommandLogController::index()): page/limit must be read from it, never
        // re-read from the raw query string, so there is a single, validated source of truth.
        $limit = $filter->limit;
        $page = $filter->page;
        // ceil(0 / $limit) is 0 for an empty collection: floor it to 1 so "hydra:last" never
        // points to a nonexistent page 0.
        $lastPage = max(1, (int) ceil($totalItems / $limit));

        $route = $request->attributes->getString('_route');
        $queryParams = $request->query->all();

        $view = [
            '@id' => $request->getRequestUri(),
            '@type' => 'hydra:PartialCollectionView',
            'hydra:first' => $this->generateUrl($route, $queryParams, 1),
            'hydra:last' => $this->generateUrl($route, $queryParams, $lastPage),
        ];

        // An empty string is not a valid IRI: omit the key entirely rather than emit one when
        // there is no previous/next page.
        if ($page > 1) {
            $view['hydra:previous'] = $this->generateUrl($route, $queryParams, $page - 1);
        }

        if ($page < $lastPage) {
            $view['hydra:next'] = $this->generateUrl($route, $queryParams, $page + 1);
        }

        $payload = [
            '@context' => $this->getApiBasePath().'/contexts/Collection',
            '@id' => $request->getRequestUri(),
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => $totalItems,
            'hydra:itemsPerPage' => $limit,
            'hydra:view' => $view,
            'hydra:member' => $this->normalize(iterator_to_array($paginator), $groups),
        ];

        return new JsonResponse($payload);
    }

    /**
     * Builds a JSON-LD response wrapping a plain array the caller already built - a computed
     * or aggregated payload with no entity behind it (statistics, for instance), as opposed to
     * createItemResponse() which normalizes one. $data is merged in as-is, no serializer
     * groups involved.
     *
     * @param array<string, mixed> $data
     */
    public function createArrayResponse(array $data, string $type, string $route): JsonResponse
    {
        $payload = [
            '@context' => $this->getApiBasePath()."/contexts/$type",
            '@id' => $this->urlGenerator->generate($route),
            '@type' => $type,
            ...$data,
        ];

        return new JsonResponse($payload);
    }

    /**
     * @param array<string> $groups
     *
     * @throws ExceptionInterface
     */
    private function normalize(mixed $data, array $groups): array
    {
        return $this->serializer->normalize($data, 'json', ['groups' => $groups]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generateUrl(string $route, array $params, int $page): string
    {
        $params['page'] = $page;

        return $this->urlGenerator->generate($route, $params);
    }

    /**
     * Recovers whatever prefix (if any) the consuming application chose when it imported
     * config/routes.yaml.
     *
     * The prefix is only known once routes are imported, so it cannot be hardcoded: it is
     * merged into the compiled route path at import time. Generating the "list" route's URL
     * and stripping this bundle's own "/command-logs" suffix recovers exactly that prefix,
     * which keeps "@context" correct under any mount point instead of assuming "/api".
     */
    private function getApiBasePath(): string
    {
        $listPath = $this->urlGenerator->generate('command_logger_api_list');

        return substr($listPath, 0, -\strlen(CommandLogController::PATH));
    }

    private function getContextName(mixed $data): string
    {
        if (is_object($data)) {
            return (new \ReflectionClass($data))->getShortName();
        }

        return 'Item';
    }
}
