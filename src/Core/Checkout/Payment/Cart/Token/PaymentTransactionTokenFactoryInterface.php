<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStruct;
use Shopware\Core\Framework\Context;

interface PaymentTransactionTokenFactoryInterface
{
    public function generateToken(OrderTransactionStruct $transaction, Context $context): string;

    public function validateToken(string $token, Context $context): TokenStruct;

    public function invalidateToken(string $tokenId, Context $context): bool;
}
