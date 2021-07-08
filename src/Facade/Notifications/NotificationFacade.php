<?php

namespace Kiener\MolliePayments\Facade\Notifications;

use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class NotificationFacade
{

    /**
     * @var MollieGatewayInterface
     */
    private $gatewayMollie;

    /**
     * @var OrderStatusConverter
     */
    private $statusConverter;

    /**
     * @var OrderStatusUpdater
     */
    private $statusUpdater;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrderTransactions;


    /**
     * @param MollieGatewayInterface $gatewayMollie
     * @param OrderStatusConverter $statusConverter
     * @param OrderStatusUpdater $statusUpdater
     * @param EntityRepositoryInterface $repoPaymentMethods
     * @param EntityRepositoryInterface $repoOrderTransactions
     */
    public function __construct(MollieGatewayInterface $gatewayMollie, OrderStatusConverter $statusConverter, OrderStatusUpdater $statusUpdater, EntityRepositoryInterface $repoPaymentMethods, EntityRepositoryInterface $repoOrderTransactions)
    {
        $this->gatewayMollie = $gatewayMollie;
        $this->statusConverter = $statusConverter;
        $this->statusUpdater = $statusUpdater;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->repoOrderTransactions = $repoOrderTransactions;
    }


    /**
     * @param string $transactionId
     * @param MollieSettingStruct $settings
     * @param SalesChannelContext $contextSC
     * @throws \Exception
     */
    public function onNotify(string $transactionId, MollieSettingStruct $settings, SalesChannelContext $contextSC): void
    {
        # -----------------------------------------------------------------------------------------------------
        # LOAD TRANSACTION
        $transaction = $this->getTransaction($transactionId, $contextSC->getContext());

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new \Exception('Transaction ' . $transactionId . ' not found in Shopware');
        }

        # -----------------------------------------------------------------------------------------------------

        $swOrder = $transaction->getOrder();

        if (!$swOrder instanceof OrderEntity) {
            throw new OrderNotFoundException('Shopware Order not found for transaction: ' . $transactionId);
        }

        $mollieOrderId = $this->getMollieId($swOrder);

        # --------------------------------------------------------------------------------------------

        $this->gatewayMollie->switchClient($contextSC->getSalesChannel()->getId());

        # fetch the order of our mollie ID
        # from our sales channel mollie profile
        $mollieOrder = $this->gatewayMollie->getOrder($mollieOrderId);

        # --------------------------------------------------------------------------------------------

        $status = $this->statusConverter->getMollieStatus($mollieOrder);

        $this->statusUpdater->updatePaymentStatus($transaction, $status, $contextSC->getContext());

        $this->statusUpdater->updateOrderStatus($swOrder, $status, $settings, $contextSC->getContext());

        # --------------------------------------------------------------------------------------------

        # lets check what payment method has been used
        # if somehow the user switched to a different one
        # then we want to update the one inside Shopware
        # If our order is Apple Pay, then DO NOT CONVERT THIS TO CREDIT CARD!
        # we want to keep apple pay as payment method
        if ($transaction->getPaymentMethod() instanceof PaymentMethodEntity && $transaction->getPaymentMethod()->getHandlerIdentifier() !== ApplePayPayment::class) {
            $this->updatePaymentMethod($transaction, $mollieOrder, $contextSC->getContext());
        }

    }


    /**
     * @param string $transactionId
     * @param Context $context
     * @return OrderTransactionEntity|null
     */
    private function getTransaction(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $transactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod');

        return $this->repoOrderTransactions->search($criteria, $context)->first();
    }

    /**
     * @param OrderEntity $order
     * @return string
     */
    private function getMollieId(OrderEntity $order): string
    {
        $customFields = $order->getCustomFields();

        if (!isset($customFields['mollie_payments'])) {
            return "";
        }

        if (!isset($customFields['mollie_payments']['order_id'])) {
            return "";
        }

        return (string)$customFields['mollie_payments']['order_id'];
    }

    /**
     * @param OrderTransactionEntity $transaction
     * @param Order $mollieOrder
     * @param Context $context
     */
    private function updatePaymentMethod(OrderTransactionEntity $transaction, Order $mollieOrder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter('AND', [
                    new ContainsFilter('handlerIdentifier', 'Kiener\MolliePayments\Handler\Method'),
                    new EqualsFilter('customFields.mollie_payment_method_name', $mollieOrder->method)
                ]
            )
        );

        $shopwarePaymentId = $this->repoPaymentMethods->searchIds($criteria, $context)->firstId();

        if (is_null($shopwarePaymentId)) {
            # if the payment method is not available locally in shopware
            # then we just skip the update process
            # we do not want to fail our notification because of this
            return;
        }

        $transaction->setPaymentMethodId($shopwarePaymentId);

        $this->repoOrderTransactions->update([
            [
                'id' => $transaction->getUniqueIdentifier(),
                'paymentMethodId' => $shopwarePaymentId
            ]
        ],
            $context
        );
    }


}
