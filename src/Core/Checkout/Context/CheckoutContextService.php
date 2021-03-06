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

namespace Shopware\Core\Checkout\Context;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CheckoutContextService implements CheckoutContextServiceInterface
{
    public const CURRENCY_ID = 'currencyId';
    public const LANGUAGE_ID = 'languageId';
    public const CUSTOMER_ID = 'customerId';
    public const CUSTOMER_GROUP_ID = 'customerGroupId';
    public const BILLING_ADDRESS_ID = 'billingAddressId';
    public const SHIPPING_ADDRESS_ID = 'shippingAddressId';
    public const PAYMENT_METHOD_ID = 'paymentMethodId';
    public const SHIPPING_METHOD_ID = 'shippingMethodId';
    public const COUNTRY_ID = 'countryId';
    public const STATE_ID = 'stateId';

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var CheckoutContextFactoryInterface
     */
    private $factory;

    /**
     * @var CheckoutContext[]
     */
    private $context = [];

    /**
     * @var CheckoutRuleLoader
     */
    private $ruleLoader;

    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    public function __construct(
        CacheItemPoolInterface $cache,
        CheckoutContextFactoryInterface $factory,
        CheckoutRuleLoader $ruleLoader,
        CheckoutContextPersister $contextPersister
    ) {
        $this->cache = $cache;
        $this->factory = $factory;
        $this->ruleLoader = $ruleLoader;
        $this->contextPersister = $contextPersister;
    }

    public function get(string $tenantId, string $touchpointId, string $token): CheckoutContext
    {
        return $this->load($tenantId, $touchpointId, $token, true);
    }

    public function refresh(string $tenantId, string $touchpointId, string $token): void
    {
        $key = $touchpointId . '-' . $token . '-' . $tenantId;
        $this->context[$key] = null;
        $this->load($tenantId, $touchpointId, $token, false);
    }

    private function load(string $tenantId, string $touchpointId, string $token, bool $useCache): CheckoutContext
    {
        $key = $touchpointId . '-' . $token . '-' . $tenantId;

        if (isset($this->context[$key])) {
            return $this->context[$key];
        }

        $parameters = $this->contextPersister->load($token, $tenantId);

        $cacheKey = $key . '-' . implode($parameters);

        $item = $this->cache->getItem($cacheKey);

        $context = null;
        if ($useCache && $item->isHit()) {
            try {
                $context = $this->loadFromCache($item, $token);
            } catch (\Exception $e) {
            }
        }

        if (!$context) {
            $context = $this->factory->create($tenantId, $token, $touchpointId, $parameters);

            $item->set(serialize($context));

            $item->expiresAfter(120);

            $this->cache->save($item);
        }

        $rules = $this->ruleLoader->loadMatchingRules($context, $token);
        $context->setRuleIds($rules->getIds());
        $context->lockRules();

        $this->context[$key] = $context;

        return $context;
    }

    private function loadFromCache(CacheItemInterface $item, string $token): CheckoutContext
    {
        $cacheContext = unserialize($item->get(), [Struct::class]);

        /* @var CheckoutContext $cacheContext */
        return new CheckoutContext(
            $cacheContext->getTenantId(),
            $token,
            $cacheContext->getTouchpoint(),
            $cacheContext->getLanguage(),
            $cacheContext->getFallbackLanguage(),
            $cacheContext->getCurrency(),
            $cacheContext->getCurrentCustomerGroup(),
            $cacheContext->getFallbackCustomerGroup(),
            $cacheContext->getTaxRules(),
            $cacheContext->getPaymentMethod(),
            $cacheContext->getShippingMethod(),
            $cacheContext->getShippingLocation(),
            $cacheContext->getCustomer(),
            []
        );
    }
}
