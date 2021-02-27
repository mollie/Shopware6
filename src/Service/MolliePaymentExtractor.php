<?php

namespace Kiener\MolliePayments\Service;


use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MolliePaymentExtractor
{
    /**
     * method extracts last created transaction if it is a mollie payment transaction.
     *
     * @param OrderTransactionCollection $collection
     * @return OrderTransactionEntity|null
     */
    public function extractLast(?OrderTransactionCollection $collection): ?OrderTransactionEntity
    {
        if (!$collection instanceof OrderTransactionCollection) {
            return null;
        }

        if ($collection->count() === 0) {
            return null;
        }

        // only transactions with a payment method
        $collection->filter(function (OrderTransactionEntity $transaction) {
            return ($transaction->getPaymentMethod() instanceof PaymentMethodEntity);
        });

        // sort all transactions chronological
        $collection->sort(function (OrderTransactionEntity $a, OrderTransactionEntity $b) {
            return $a->getCreatedAt() > $b->getCreatedAt();
        });

        $lastTransaction = $collection->last();

        $molliePaymentsNamespace = 'Kiener\MolliePayments\Handler\Method';

        $handlerName = substr($lastTransaction->getPaymentMethod()->getHandlerIdentifier(), 0, strlen($molliePaymentsNamespace));

        if ($handlerName !== $molliePaymentsNamespace) {
            return null;
        }

        return $lastTransaction;
    }
}
