<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Exception;
use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Validator\DoesOpenPaymentExist;
use Mollie\Api\Resources\Order;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MollieOrderPaymentFlow
{
    /**
     * @var PaymentStatusHelper
     */
    private PaymentStatusHelper $paymentStatusHelper;
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(PaymentStatusHelper $paymentStatusHelper, EventDispatcherInterface $eventDispatcher)
    {

        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process(OrderTransactionEntity $transaction, OrderEntity $order, Order $mollieOrder, SalesChannelContext $salesChannelContext): bool
    {

        // this is only mollie payment flow here we are doing failed management here
        try {
            $paymentStatus = $this->paymentStatusHelper->processPaymentStatus(
                $transaction,
                $order,
                $mollieOrder,
                $salesChannelContext->getContext()
            );
        } catch (Exception $e) {
            //@todo do something here, log and throw an error
        }

        $failedStates = [PaymentStatus::STATUS_CANCELED, PaymentStatus::STATUS_FAILED];

        // if order hasn't failed just lead user to normal checkout url
        if (!in_array($paymentStatus, $failedStates)) {

            //return $this->returnRedirect($salesChannelContext, $returnUrl, $order, $mollieOrder);
            return false;
        }

        if (!DoesOpenPaymentExist::validate($mollieOrder->payments()->getArrayCopy())) {
            $mollieOrder->createPayment([]);
        }

        $redirectUrl = '';

        if (!empty($mollieOrder->getCheckoutUrl())) {
            $redirectUrl = $mollieOrder->getCheckoutUrl();
        }

        if (empty($redirectUrl)) {
            //@todo do something here, log and throw an error
        }

        $paymentPageFailEvent = new PaymentPageFailEvent(
            $salesChannelContext->getContext(),
            $order,
            $mollieOrder,
            $salesChannelContext->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageFailEvent, $paymentPageFailEvent::EVENT_NAME);

        return true;
    }
}
