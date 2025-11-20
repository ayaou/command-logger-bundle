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
            '@context' => "/api/contexts/$context",
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
    public function createCollectionResponse(Paginator $paginator, Request $request, array $groups = []): JsonResponse
    {
        $totalItems = count($paginator);
        $limit = $request->query->getInt('limit', 10);
        $page = $request->query->getInt('page', 1);
        $lastPage = (int) ceil($totalItems / $limit);

        $route = $request->attributes->getString('_route');
        $queryParams = $request->query->all();

        $view = [
            '@id' => $request->getRequestUri(),
            '@type' => 'hydra:PartialCollectionView',
        ];

        $view['hydra:first'] = $this->generateUrl($route, $queryParams, 1);
        $view['hydra:last'] = $this->generateUrl($route, $queryParams, $lastPage);
        $view['hydra:previous'] = $page > 1 ? $this->generateUrl($route, $queryParams, $page - 1) : '';
        $view['hydra:next'] = $page < $lastPage ? $this->generateUrl($route, $queryParams, $page + 1) : '';

        $payload = [
            '@context' => '/api/contexts/Collection',
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

    private function getContextName(mixed $data): string
    {
        if (is_object($data)) {
            return (new \ReflectionClass($data))->getShortName();
        }

        return 'Item';
    }
}
