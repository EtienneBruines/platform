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
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;

class BillingZipCodeRuleTest extends TestCase
{
    public function testEqualsWithSingleCode(): void
    {
        $rule = new BillingZipCodeRule(['ABC123']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressStruct();
        $billing->setZipcode('ABC123');

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = new BillingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressStruct();
        $billing->setZipcode('ABC2');

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = new BillingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $billing = new CustomerAddressStruct();
        $billing->setZipcode('ABC4');

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = new BillingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
