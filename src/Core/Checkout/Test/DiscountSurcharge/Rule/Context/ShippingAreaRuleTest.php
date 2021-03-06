<?php declare(strict_types=1);
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

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Rule\ShippingAreaRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaStruct;
use Shopware\Core\System\Country\CountryStruct;

class ShippingAreaRuleTest extends TestCase
{
    /**
     * @dataProvider matchingEqualsData
     *
     * @param array  $ruleData
     * @param string $currentArea
     *
     * @throws \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     */
    public function testEquals(array $ruleData, string $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_EQ);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function matchingEqualsData(): array
    {
        return [
            [['SWAG-AREA-ID-1'], 'SWAG-AREA-ID-1'],
            [['SWAG-AREA-ID-1', 'SWAG-AREA-ID-2', 'SWAG-AREA-ID-3'], 'SWAG-AREA-ID-2'],
        ];
    }

    /**
     * @dataProvider matchingNotEqualsData
     *
     * @param array  $ruleData
     * @param string $currentArea
     *
     * @throws \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     */
    public function testNotEquals(array $ruleData, string $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_NEQ);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function matchingNotEqualsData(): array
    {
        return [
            [['SWAG-AREA-ID-1'], 'SWAG-AREA-ID-2'],
            [['SWAG-AREA-ID-1', 'SWAG-AREA-ID-2', 'SWAG-AREA-ID-3'], 'SWAG-AREA-ID-4'],
        ];
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new ShippingAreaRule(['SWAG-AREA-ID-1'], $operator);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $rule->match(new CartRuleScope($cart, $context))->matches();
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [Rule::OPERATOR_GTE],
            [Rule::OPERATOR_LTE],
        ];
    }

    private function createCountryWithArea(string $areaId): CountryStruct
    {
        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $area = new CountryAreaStruct();
        $area->setId($areaId);
        $country->setAreaId($areaId);

        return $country;
    }
}
