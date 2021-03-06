<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Storefront\Event\PasswordSaveRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordSaveSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'password';

    public static function getSubscribedEvents()
    {
        return [
            PasswordSaveRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(PasswordSaveRequestEvent $event)
    {
        $request = $event->getRequest();
        $transformed = $event->getPasswordSaveRequest();

        $transformed->assign($request->request->get(self::PREFIX) ?? $request->request->all());
    }
}
