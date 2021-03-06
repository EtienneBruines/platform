<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Framework\Struct\Struct;

class Delivery extends Struct
{
    /**
     * @var DeliveryPositionCollection
     */
    protected $positions;

    /**
     * @var ShippingLocation
     */
    protected $location;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    /**
     * @var ShippingMethodStruct
     */
    protected $shippingMethod;

    /**
     * @var Price
     */
    protected $shippingCosts;

    /**
     * @var DeliveryDate
     */
    protected $endDeliveryDate;

    public function __construct(
        DeliveryPositionCollection $positions,
        DeliveryDate $deliveryDate,
        ShippingMethodStruct $shippingMethod,
        ShippingLocation $location,
        Price $shippingCosts
    ) {
        $this->location = $location;
        $this->positions = $positions;
        $this->deliveryDate = $deliveryDate;
        $this->shippingMethod = $shippingMethod;
        $this->shippingCosts = $shippingCosts;

        $end = clone $deliveryDate;

        $this->endDeliveryDate = new DeliveryDate(
            $end->getEarliest()->add(new \DateInterval('P' . $this->shippingMethod->getMinDeliveryTime() . 'D')),
            $end->getLatest()->add(new \DateInterval('P' . $this->shippingMethod->getMaxDeliveryTime() . 'D'))
        );
    }

    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }

    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getEndDeliveryDate()
    {
        return $this->endDeliveryDate;
    }

    public function getShippingMethod(): ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function getShippingCosts(): Price
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(Price $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }
}
