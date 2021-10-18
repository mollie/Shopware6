<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentFinalize
{
    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;
    /**
     * @var TransactionTransitionServiceInterface
     */
    private $transactionTransitionService;
    /**
     * @var OrderStatusConverter
     */
    private $orderStatusConverter;
    /**
     * @var OrderStatusUpdater
     */
    private $orderStatusUpdater;
    /**
     * @var SettingsService
     */
    private $settingsService;

    public function __construct(
        MollieApiFactory $mollieApiFactory,
        TransactionTransitionServiceInterface $transactionTransitionService,
        OrderStatusConverter $orderStatusConverter,
        OrderStatusUpdater $orderStatusUpdater,
        SettingsService $settingsService
    )
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @throws MissingMollieOrderIdException
     * @throws ApiException|IncompatiblePlatform|MissingMollieOrderIdException|CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext): void
    {
        $order = $transactionStruct->getOrder();
        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $orderNumber = $order->getOrderNumber() ?? '-';

            throw new MissingMollieOrderIdException($orderNumber);
        }

        $apiClient = $this->mollieApiFactory->getClient($salesChannelContext->getSalesChannel()->getId());
        $mollieOrder = $apiClient->orders->get($mollieOrderId, ['embed' => 'payments']);

        $paymentStatus = $this->orderStatusConverter->getMollieStatus($mollieOrder);
        $this->orderStatusUpdater->updatePaymentStatus($transactionStruct->getOrderTransaction(), $paymentStatus, $salesChannelContext->getContext());
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
        $this->orderStatusUpdater->updateOrderStatus($order, $paymentStatus, $settings, $salesChannelContext->getContext());

        if (MolliePaymentStatus::isFailedStatus($paymentStatus)) {

            throw new CustomerCanceledAsyncPaymentException(
                $transactionStruct->getOrderTransaction()->getUniqueIdentifier(),
                sprintf(
                    'Payment for order %s (%s) was cancelled by the customer.',
                    $order->getOrderNumber(),
                    $mollieOrder->id
                )
            );
        }
    }
}
