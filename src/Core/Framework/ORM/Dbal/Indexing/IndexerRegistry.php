<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Indexing;

use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexerRegistry implements IndexerInterface, EventSubscriberInterface
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(iterable $indexer)
    {
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            EntityWrittenContainerEvent::NAME => 'refresh',
        ];
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->index($timestamp, $tenantId);
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($event);
        }
    }
}
