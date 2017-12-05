<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Order\Struct\OrderDeliveryDetailStruct;

class OrderDeliveryDetailCollection extends OrderDeliveryBasicCollection
{
    /**
     * @var OrderDeliveryDetailStruct[]
     */
    protected $elements = [];

    public function getOrders(): OrderBasicCollection
    {
        return new OrderBasicCollection(
            $this->fmap(function (OrderDeliveryDetailStruct $orderDelivery) {
                return $orderDelivery->getOrder();
            })
        );
    }

    public function getPositionUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPositions()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPositions(): OrderDeliveryPositionBasicCollection
    {
        $collection = new OrderDeliveryPositionBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPositions()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryDetailStruct::class;
    }
}