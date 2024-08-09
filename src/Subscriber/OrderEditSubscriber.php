<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\Order\OrderTimeService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEditSubscriber implements EventSubscriberInterface
{
    /**
     * @var OrderStatusUpdater
     */
    private $orderStatusUpdater;

    /**
     * @var OrderTimeService
     */
    private $orderTimeService;

    /**
     * @var SettingsService
     */
    private $settingsService;

    public function __construct(
        OrderStatusUpdater $orderStatusUpdater,
        OrderTimeService $orderTimeService,
        SettingsService $settingsService
    ) {
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->orderTimeService = $orderTimeService;
        $this->settingsService = $settingsService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountOrderPageLoadedEvent::class => 'accountOrderDetailPageLoaded'
        ];
    }

    public function accountOrderDetailPageLoaded(AccountOrderPageLoadedEvent $event): void
    {
        $orders = $event->getPage()->getOrders();

        foreach ($orders as $order) {
            if (!$order instanceof OrderEntity || $this->isMolliePayment($order) === false) {
                continue;
            }

            $transactions = $order->getTransactions();

            if ($transactions === null || $transactions->count() === 0) {
                continue;
            }

            $lastTransaction = $transactions->last();

            $lastStatus = $lastTransaction->getStateMachineState()->getTechnicalName();

            // disregard any orders that are not in progress
            if ($lastStatus !== 'in_progress') {
                continue;
            }

            $settings = $this->settingsService->getSettings();
            $finalizeTransactionTimeInMinutes = $settings->getPaymentFinalizeTransactionTime();
            $finalizeTransactionTimeInHours = (int) ceil($finalizeTransactionTimeInMinutes / 60);

            if ($this->orderUsesSepaPayment($order)) {
                $finalizeTransactionTimeInHours = (int) ceil($settings->getPaymentMethodBankTransferDueDateDays() / 24);
            }

            if ($this->orderTimeService->isOrderAgeGreaterThan($order, $finalizeTransactionTimeInHours) === false) {
                continue;
            }

            // orderStatusUpdater needs the order to be set on the transaction
            $lastTransaction->setOrder($order);
            $context = $event->getContext();
            // this forces the order to be open again
            $context->addState(OrderStatusUpdater::ORDER_STATE_FORCE_OPEN);
            try {
                $this->orderStatusUpdater->updatePaymentStatus($lastTransaction, MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED, $context);
            } catch (\Exception $exception) {
            }
        }
    }

    /**
     * @param OrderEntity $order
     * @return bool
     * @todo refactor once php8.0 is minimum version. Use Null-safe operator
     */
    private function orderUsesSepaPayment(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();

        if ($transactions === null || count($transactions) === 0) {
            return false;
        }

        $lastTransaction = $transactions->last();

        if ($lastTransaction instanceof OrderTransactionEntity === false) {
            return false;
        }

        $paymentMethod = $lastTransaction->getPaymentMethod();

        if ($paymentMethod === null) {
            return false;
        }

        return $paymentMethod->getHandlerIdentifier() === BankTransferPayment::class;
    }

    private function isMolliePayment(OrderEntity $order): bool
    {
        $customFields = $order->getCustomFields();

        return is_array($customFields) && !empty($customFields) && isset($customFields['mollie_payments']);
    }
}
