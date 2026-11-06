<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionLocaleSubscriber implements EventSubscriberInterface
{
    public const string SESSION_KEY = '_locale';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['applySessionLocale', 20]];
    }

    public function applySessionLocale(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get(self::SESSION_KEY);

        if (!\is_string($locale)) {
            return;
        }

        $request->setLocale($locale);
    }
}
