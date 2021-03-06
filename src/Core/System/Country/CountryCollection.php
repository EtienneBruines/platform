<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\ORM\EntityCollection;

class CountryCollection extends EntityCollection
{
    /**
     * @var CountryStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStruct
    {
        return parent::get($id);
    }

    public function current(): CountryStruct
    {
        return parent::current();
    }

    public function getAreaIds(): array
    {
        return $this->fmap(function (CountryStruct $country) {
            return $country->getAreaId();
        });
    }

    public function filterByAreaId(string $id): self
    {
        return $this->filter(function (CountryStruct $country) use ($id) {
            return $country->getAreaId() === $id;
        });
    }

    public function getTaxfreeForVatIds(): array
    {
        return $this->fmap(function (CountryStruct $country) {
            return $country->getTaxfreeForVatId();
        });
    }

    public function filterByTaxfreeForVatId(string $id): self
    {
        return $this->filter(function (CountryStruct $country) use ($id) {
            return $country->getTaxfreeForVatId() === $id;
        });
    }

    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();
        foreach ($this->elements as $country) {
            if ($country->getStates()) {
                $country->getStates()->getEntities()->sortByPositionAndName();
            }
        }
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryStruct $a, CountryStruct $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getName() !== $b->getName()) {
                return strnatcasecmp($a->getName(), $b->getName());
            }

            return 0;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStruct::class;
    }
}
