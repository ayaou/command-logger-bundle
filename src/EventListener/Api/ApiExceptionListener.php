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

namespace Ayaou\CommandLoggerBundle\EventListener\Api;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException; // <--- Import

#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ApiExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')] private readonly bool $isDebug,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isBundleController($event)) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // Base Problem Details
        $data = [
            'type' => 'about:blank',
            'title' => $this->getTitleForStatusCode($statusCode),
            'status' => $statusCode,
            'detail' => $exception->getMessage(),
        ];

        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            $data['title'] = 'Validation Failed';
            $data['detail'] = 'One or more parameters are invalid.';
            $data['violations'] = [];

            foreach ($previous->getViolations() as $violation) {
                $data['violations'][] = [
                    'propertyPath' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ];
            }
        }

        if (500 === $statusCode && !$this->isDebug) {
            $data['detail'] = 'Internal Server Error';
        }

        if ($this->isDebug) {
            $data['trace'] = $exception->getTrace();
            $data['class'] = get_class($exception);
        }

        $event->setResponse(new JsonResponse(
            $data,
            $statusCode,
            ['Content-Type' => 'application/problem+json']
        ));
    }

    private function isBundleController(ExceptionEvent $event): bool
    {
        $controller = $event->getRequest()->attributes->get('_controller');
        if (!$controller) {
            return false;
        }

        $class = is_array($controller) ? $controller[0] : $controller;
        if (is_object($class)) {
            $class = get_class($class);
        }
        if (is_string($class)) {
            $class = explode('::', $class)[0];
        }

        return str_starts_with($class, 'Ayaou\CommandLoggerBundle\Controller\Api');
    }

    private function getTitleForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'An error occurred',
        };
    }
}
