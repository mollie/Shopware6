<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class MolliePaymentExtractor
{
    public const MOLLIE_PAYMENT_HANDLER_NAMESPACE = 'Kiener\MolliePayments\Handler\Method';

    /**
     * method extracts last created transaction if it is a mollie payment transaction.
     *
     * @param null|OrderTransactionCollection $collection
     * @return null|OrderTransactionEntity
     */
    public function extractLastMolliePayment(?OrderTransactionCollection $collection): ?OrderTransactionEntity
    {
        if (!$collection instanceof OrderTransactionCollection) {
            return null;
        }

        if ($collection->count() === 0) {
            return null;
        }

        // only transactions with a payment method
        $collection->filter(static function (OrderTransactionEntity $transaction) {
            return ($transaction->getPaymentMethod() instanceof PaymentMethodEntity);
        });

        // sort all transactions chronological
        $collection->sort(static function (OrderTransactionEntity $a, OrderTransactionEntity $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        $lastTransaction = $collection->last();

        if ($lastTransaction instanceof OrderTransactionEntity && $this->isMolliePayment($lastTransaction)) {
            return $lastTransaction;
        }

        return null;
    }

    private function isMolliePayment(OrderTransactionEntity $transaction): bool
    {
        $pattern = sprintf(
            '/^%s/',
            preg_quote(self::MOLLIE_PAYMENT_HANDLER_NAMESPACE)
        );

        $handlerID = ($transaction->getPaymentMethod() instanceof PaymentMethodEntity) ? $transaction->getPaymentMethod()->getHandlerIdentifier() : '';

        return preg_match($pattern, $handlerID) === 1;
    }
}
