<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $status = $exception->getStatusCode();
        $message = match ($status) {
            404 => 'The requested resource was not found.',
            405 => 'This method is not allowed for the requested resource.',
            default => '' !== $exception->getMessage() ? $exception->getMessage() : 'The request could not be processed.',
        };

        $response = new JsonResponse(['error' => $message], $status);
        $response->headers->add($exception->getHeaders());

        $event->setResponse($response);
    }
}
