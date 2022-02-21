<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\PaymentMethodService;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var EntityRepositoryInterface */
    private $orderTransactionRepository;

    public function __construct(
        OrderStatusConverter $orderStatusConverter,
        OrderStatusUpdater $orderStatusUpdater,
        SettingsService $settingsService,
        PaymentMethodService $paymentMethodService,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $orderTransactionRepository
    )
    {
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
        $this->paymentMethodService = $paymentMethodService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    public function process(OrderTransactionEntity $transaction, OrderEntity $order, Order $mollieOrder, SalesChannelContext $salesChannelContext): bool
    {
        $paymentStatus = $this->orderStatusConverter->getMollieOrderStatus($mollieOrder);
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
        // this is only mollie payment flow here we are doing failed management here
        $this->orderStatusUpdater->updatePaymentStatus($transaction, $paymentStatus, $salesChannelContext->getContext());
        $this->orderStatusUpdater->updateOrderStatus($order, $paymentStatus, $settings, $salesChannelContext->getContext());

        //now check if payment method has changed, but only in case that it is no paid apple pay (apple pay returns credit card as method)
        if (!$this->paymentMethodService->isPaidApplePayTransaction($transaction, $mollieOrder)) {
            $currentCustomerSelectedPaymentMethod = $mollieOrder->method;

            // check if it is mollie payment method
            // ensure that we may only fetch mollie payment methods
            $molliePaymentMethodId = $this->paymentMethodRepository->searchIds(
                (new Criteria())
                    ->addFilter(
                        new MultiFilter('AND', [
                                new ContainsFilter('handlerIdentifier', 'Kiener\MolliePayments\Handler\Method'),
                                new EqualsFilter('customFields.mollie_payment_method_name', $currentCustomerSelectedPaymentMethod)
                            ]
                        )
                    ),
                $salesChannelContext->getContext()
            )->firstId();

            // if payment method has changed, update it
            if (!is_null($molliePaymentMethodId) && $molliePaymentMethodId !== $transaction->getPaymentMethodId()) {
                $transaction->setPaymentMethodId($molliePaymentMethodId);

                $this->orderTransactionRepository->update(
                    [
                        [
                            'id' => $transaction->getUniqueIdentifier(),
                            'paymentMethodId' => $molliePaymentMethodId
                        ]
                    ],

                    $salesChannelContext->getContext()
                );
            }
        }

        # our transaction has no payment method here?
        # but it's also done in the finalize...this should be refactored
        if (MolliePaymentStatus::isFailedStatus('', $paymentStatus)) {
            $mollieOrder->createPayment([]);

            return false;
        }

        return true;
    }
}
