<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\ORM\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Core\Framework\ORM\Search\Query\NotQuery;
use Shopware\Core\Framework\ORM\Search\Query\Query;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Listing\AggregationView\ListAggregation;
use Shopware\Storefront\Page\Listing\AggregationView\ListItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ManufacturerAggregationSubscriber implements EventSubscriberInterface
{
    public const PRODUCT_MANUFACTURER_ID = 'product.manufacturer.id';

    public const MANUFACTURER_PARAMETER = self::AGGREGATION_NAME;

    public const AGGREGATION_NAME = 'manufacturer';

    /**
     * @var RepositoryInterface
     */
    private $manufacturerRepository;

    public function __construct(RepositoryInterface $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LOADED => 'buildPage',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::MANUFACTURER_PARAMETER)) {
            return;
        }

        $names = $request->query->get(self::MANUFACTURER_PARAMETER, '');
        $names = array_filter(explode('|', $names));

        if (empty($names)) {
            return;
        }

        $listingRequest = $event->getListingPageRequest();
        $listingRequest->setManufacturerNames($names);
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::PRODUCT_MANUFACTURER_ID,
                ProductManufacturerDefinition::class,
                self::MANUFACTURER_PARAMETER
            )
        );

        if (empty($request->getManufacturerNames())) {
            return;
        }
        $names = $request->getManufacturerNames();

        $search = new Criteria();
        $search->addFilter(new TermsQuery('product_manufacturer.name', $names));
        $ids = $this->manufacturerRepository->searchIds($search, $event->getContext());

        if (empty($ids->getIds())) {
            return;
        }

        $query = new TermsQuery(self::PRODUCT_MANUFACTURER_ID, $ids->getIds());

        $event->getCriteria()->addPostFilter($query);
    }

    public function buildPage(ListingPageLoadedEvent $event): void
    {
        $result = $event->getPage()->getProducts()->getAggregations();

        if ($result === null) {
            return;
        }

        if (!$result->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $result->get(self::AGGREGATION_NAME);

        $criteria = $event->getPage()->getCriteria();

        $filter = $this->getFilter($criteria->getPostFilters());

        $active = $filter !== null;

        $actives = $filter ? $filter->getValue() : [];

        /** @var ProductManufacturerCollection $values */
        $values = $aggregation->getResult();

        $items = [];
        foreach ($values as $manufacturer) {
            $item = new ListItem(
                $manufacturer->getName(),
                \in_array($manufacturer->getId(), $actives, true),
                $manufacturer->getName()
            );

            $item->addExtension(self::AGGREGATION_NAME, $manufacturer);
            $items[] = $item;
        }

        $event->getPage()->getAggregations()->add(
            new ListAggregation(self::AGGREGATION_NAME, $active, 'Manufacturer', self::AGGREGATION_NAME, $items)
        );
    }

    private function getFilter(NestedQuery $nested): ?TermsQuery
    {
        /** @var Query $query */
        foreach ($nested->getQueries() as $query) {
            if ($query instanceof TermsQuery && $query->getField() === self::PRODUCT_MANUFACTURER_ID) {
                return $query;
            }

            if (!$query instanceof NestedQuery || !$query instanceof NotQuery) {
                continue;
            }

            $found = $this->getFilter($query);

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
