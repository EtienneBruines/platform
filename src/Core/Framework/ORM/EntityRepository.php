<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\AggregatorResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EntityRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    protected $reader;

    /**
     * @var EntitySearcherInterface
     */
    protected $searcher;

    /**
     * @var EntityAggregatorInterface
     */
    protected $aggregator;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var VersionManager
     */
    protected $versionManager;

    /**
     * @var string|EntityDefinition
     */
    protected $definition;

    public function __construct(
        string $definition,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
        $this->versionManager = $versionManager;
        $this->definition = $definition;
    }

    public function search(Criteria $criteria, Context $context)
    {
        $ids = $this->searchIds($criteria, $context);

        $read = new ReadCriteria($ids->getIds());
        $read->setAssociations($criteria->getAssociations());

        $entities = $this->read($read, $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context)->getAggregations();
        }

        $search = $ids->getData();

        /** @var Entity $element */
        foreach ($entities as $element) {
            if (!array_key_exists($element->getId(), $search)) {
                continue;
            }
            $data = $search[$element->getId()];

            $element->addExtension('search', new ArrayStruct($data));
        }

        $result = new EntitySearchResult($ids->getTotal(), $entities, $aggregations, $criteria, $context);

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, Context $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate($this->definition, $criteria, $context);

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, Context $context)
    {
        $result = $this->searcher->search($this->definition, $criteria, $context);

        $event = new EntityIdSearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->update($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->upsert($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->insert($this->definition, $data, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $affected = $this->versionManager->delete($this->definition, $ids, WriteContext::createFromContext($context));
        $event = EntityWrittenContainerEvent::createWithDeletedEvents($affected->getDeleted(), $context, $affected->getNotFound());
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        if (!$this->definition::isVersionAware()) {
            throw new \RuntimeException(sprintf('Entity %s is not version aware', $this->definition::getEntityName()));
        }

        return $this->versionManager->createVersion($this->definition, $id, WriteContext::createFromContext($context), $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        if (!$this->definition::isVersionAware()) {
            throw new \RuntimeException(sprintf('Entity %s is not version aware', $this->definition::getEntityName()));
        }
        $this->versionManager->merge($versionId, WriteContext::createFromContext($context));
    }

    public function read(ReadCriteria $criteria, Context $context)
    {
        /** @var EntityCollection $entities */
        $entities = $this->reader->read($this->definition, $criteria, $context);

        $event = new EntityLoadedEvent($this->definition, $entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }
}
