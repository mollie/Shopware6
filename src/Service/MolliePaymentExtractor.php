<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

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

        if ($this->isMolliePayment($lastTransaction)) {
            return $lastTransaction;
        }

        return null;
    }

    private function isMolliePayment(?OrderTransactionEntity $transaction): bool
    {
        if (!$transaction instanceof OrderTransactionEntity) {
            return false;
        }

        $pattern = sprintf(
            '/^%s/',
            preg_quote('Kiener\MolliePayments\Handler\Method')
        );

        return preg_match($pattern, $transaction->getPaymentMethod()->getHandlerIdentifier()) === 1;
    }
}
