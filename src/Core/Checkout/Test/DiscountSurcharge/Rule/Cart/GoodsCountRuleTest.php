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

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class GoodsCountRuleTest extends TestCase
{
    public function testRuleWithExactCountMatch(): void
    {
        $rule = new GoodsCountRule(0, Rule::OPERATOR_EQ);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithExactCountNotMatch(): void
    {
        $rule = new GoodsCountRule(0, Rule::OPERATOR_EQ);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactCountMatch(): void
    {
        $rule = new GoodsCountRule(1, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualCountMatch(): void
    {
        $rule = new GoodsCountRule(2, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualCountNotMatch(): void
    {
        $rule = new GoodsCountRule(0, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');

        $cart->add((new LineItem('A', 'test'))->setGood(true));

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactCountMatch(): void
    {
        $rule = new GoodsCountRule(1, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualCountMatch(): void
    {
        $rule = new GoodsCountRule(0, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualCountNotMatch(): void
    {
        $rule = new GoodsCountRule(2, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualCountMatch(): void
    {
        $rule = new GoodsCountRule(2, Rule::OPERATOR_NEQ);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualCountNotMatch(): void
    {
        $rule = new GoodsCountRule(1, Rule::OPERATOR_NEQ);

        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
