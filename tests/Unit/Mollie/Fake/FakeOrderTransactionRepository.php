<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;

final class FakeOrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    private ?OrderTransactionEntity $orderTransaction = null;
    private bool $withOrder = false;
    private bool $withPayment = false;
    private array $orderCustomFields = [];

    public function findOpenTransactions(?Context $context = null): IdSearchResult
    {
        // TODO: Implement findOpenTransactions() method.
    }

    public function savePaymentExtension(OrderTransactionEntity $orderTransactionEntity, Payment $payment, Context $context): EntityWrittenContainerEvent
    {
        $context = new Context(new SystemSource());
        $nestedEventCollection = new NestedEventCollection();

        return new EntityWrittenContainerEvent($context,$nestedEventCollection,[]);
    }

    public function findById(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function createValidTransaction(): void
    {
        $this->withOrder = true;
        $this->withPayment = true;
        $this->createTransaction();
    }

    public function withOrderCustomFields(array $customFields): void
    {
        $this->withOrder = true;
        $this->orderCustomFields = $customFields;
        $this->createTransaction();
    }

    public function createLegacyTransaction(): void
    {
        $this->withOrderCustomFields([
            'order_id' => 'mollieTestId',
            'transactionReturnUrl' => 'payment/finalize',
        ]);
    }

    public function createTransactionWithoutOrder(): void
    {
        $this->withOrder = false;
        $this->createTransaction();
    }

    public function createValidTransactionWithoutPaymentData(): void
    {
        $this->withOrder = true;
        $this->withPayment = false;
        $this->createTransaction();
    }

    private function createTransaction(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::fromStringToHex('fake-order-transaction-id'));
        if ($this->withOrder) {
            $order = new OrderEntity();
            $order->setSalesChannelId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
            if (count($this->orderCustomFields) > 0) {
                $order->setCustomFields([
                    Mollie::EXTENSION => $this->orderCustomFields
                ]);
            }

            $transaction->setOrder($order);
        }
        if ($this->withPayment) {
            $payment = new Payment('testMollieId',PaymentMethod::CREDIT_CARD);
            $payment->setFinalizeUrl('payment/finalize');
            $transaction->addExtension(Mollie::EXTENSION,$payment);
        }

        $this->orderTransaction = $transaction;
    }
}
