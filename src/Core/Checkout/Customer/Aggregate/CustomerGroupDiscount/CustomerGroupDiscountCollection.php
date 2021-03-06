<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount;

use Shopware\Core\Framework\ORM\EntityCollection;

class CustomerGroupDiscountCollection extends EntityCollection
{
    /**
     * @var CustomerGroupDiscountStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerGroupDiscountStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerGroupDiscountStruct
    {
        return parent::current();
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupDiscountStruct $customerGroupDiscount) {
            return $customerGroupDiscount->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupDiscountStruct $customerGroupDiscount) use ($id) {
            return $customerGroupDiscount->getCustomerGroupId() === $id;
        });
    }

    public function getDiscountForCartAmount(float $totalPrice, string $customerGroupId): ?float
    {
        $discount = null;
        foreach ($this->elements as $discountData) {
            if ($discountData->getMinimumCartAmount() > $totalPrice) {
                break;
            }
            if ($discountData->getCustomerGroupId() === $customerGroupId) {
                $discount = $discountData->getPercentageDiscount() * -1;
            }
        }

        return $discount;
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDiscountStruct::class;
    }
}
