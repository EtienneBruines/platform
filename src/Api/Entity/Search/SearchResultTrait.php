<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\TranslationContext;

trait SearchResultTrait
{
    /**
     * @var AggregationResult|null
     */
    protected $aggregations;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $searchData;

    public function getAggregations(): ?AggregationResult
    {
        return $this->aggregations;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function setAggregations($aggregations): void
    {
        $this->aggregations = $aggregations;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function setCriteria(Criteria $criteria): void
    {
        $this->criteria = $criteria;
    }

    public function setContext(TranslationContext $context): void
    {
        $this->context = $context;
    }

    public static function createFromResults(
        UuidSearchResult $uuids,
        EntityCollection $entities,
        ?AggregationResult $aggregations
    ) {
        $self = new static($entities->getElements());

        $scoring = $uuids->getData();

        /** @var Entity $element */
        foreach ($entities->getElements() as $element) {
            if (!array_key_exists($element->getUuid(), $scoring)) {
                continue;
            }
            $score = $scoring[$element->getUuid()];
            if (!array_key_exists('_score', $score)) {
                continue;
            }

            $element->setSearchScore((float) $score['_score']);
        }

        $self->setTotal($uuids->getTotal());
        $self->setCriteria($uuids->getCriteria());
        $self->setContext($uuids->getContext());
        $self->setSearchData($uuids->getData());

        if ($aggregations) {
            $self->setAggregations($aggregations->getAggregations());
        }

        return $self;
    }

    public function getSearchData(): array
    {
        return $this->searchData;
    }

    public function setSearchData(array $searchData): void
    {
        $this->searchData = $searchData;
    }
}