<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Repository\PaymentMethodRepository;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class MollieOrderPaymentFlow
{
    /** @var OrderStatusConverter */
    private $orderStatusConverter;

    /** @var OrderStatusUpdater */
    private $orderStatusUpdater;

    /** @var SettingsService */
    private $settingsService;

    /** @var PaymentMethodService */
    private $paymentMethodService;

    /** @var PaymentMethodRepository */
    private $paymentMethodRepository;

    /** @var EntityRepository */
    private $orderTransactionRepository;

    public function __construct(OrderStatusConverter $orderStatusConverter, OrderStatusUpdater $orderStatusUpdater, SettingsService $settingsService, PaymentMethodService $paymentMethodService, PaymentMethodRepository $paymentMethodRepository, EntityRepository $orderTransactionRepository)
    {
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
        $this->paymentMethodService = $paymentMethodService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function process(OrderTransactionEntity $transaction, OrderEntity $order, Order $mollieOrder, string $salesChannelId, Context $context): bool
    {
        $paymentStatus = $this->orderStatusConverter->getMollieOrderStatus($mollieOrder);
        $settings = $this->settingsService->getSettings($salesChannelId);
        // this is only mollie payment flow here we are doing failed management here
        $this->orderStatusUpdater->updatePaymentStatus($transaction, $paymentStatus, $context);
        $this->orderStatusUpdater->updateOrderStatus($order, $paymentStatus, $settings, $context);

        //now check if payment method has changed, but only in case that it is no paid apple pay (apple pay returns credit card as method)
        if (! $this->paymentMethodService->isPaidApplePayTransaction($transaction, $mollieOrder)) {
            $currentCustomerSelectedPaymentMethod = $mollieOrder->method;

            // check if it is mollie payment method
            // ensure that we may only fetch mollie payment methods
            $molliePaymentMethodId = $this->paymentMethodRepository->getRepository()->searchIds(
                (new Criteria())
                    ->addFilter(
                        new MultiFilter(
                            'AND',
                            [
                                new ContainsFilter('handlerIdentifier', 'Kiener\MolliePayments\Handler\Method'),
                                new EqualsFilter('customFields.mollie_payment_method_name', $currentCustomerSelectedPaymentMethod),
                            ]
                        )
                    ),
                $context
            )->firstId();

            // if payment method has changed, update it
            if (! is_null($molliePaymentMethodId) && $molliePaymentMethodId !== $transaction->getPaymentMethodId()) {
                $transaction->setPaymentMethodId($molliePaymentMethodId);

                $this->orderTransactionRepository->update(
                    [
                        [
                            'id' => $transaction->getUniqueIdentifier(),
                            'paymentMethodId' => $molliePaymentMethodId,
                        ],
                    ],
                    $context
                );
            }
        }

        // our transaction has no payment method here?
        // but it's also done in the finalize...this should be refactored
        if (MolliePaymentStatus::isFailedStatus('', $paymentStatus)) {
            return false;
        }

        return true;
    }
}
