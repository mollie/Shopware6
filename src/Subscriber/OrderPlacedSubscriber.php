<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    /** @var CustomerService */
    protected $customerService;

    /** @var Customer */
    protected $customerApiService;

    /** @var MolliePaymentExtractor */
    protected $extractor;

    /** @var SettingsService */
    protected $settingsService;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        CustomerService $customerService,
        Customer $customerApiService,
        MolliePaymentExtractor $extractor,
        SettingsService $settingsService,
        LoggerInterface $logger
    )
    {
        $this->customerService = $customerService;
        $this->customerApiService = $customerApiService;
        $this->extractor = $extractor;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'createCustomerAtMollie'
        ];
    }

    public function createCustomerAtMollie(CheckoutOrderPlacedEvent $event)
    {
        $customer = $event->getOrder()->getOrderCustomer()->getCustomer();

        // Do not create a Mollie customer for guest orders.
        if ($customer->getGuest()) {
            return;
        }

        // Do not create a customer if this order isn't being paid through Mollie.
        if (!($this->extractor->extractLast($event->getOrder()->getTransactions()) instanceof OrderTransactionEntity)) {
            return;
        }

        $settings = $this->settingsService->getSettings($event->getSalesChannelId(), $event->getContext());

        if (!$settings->createCustomersAtMollie()) {
            return;
        }

        try {
            $this->customerService->createMollieCustomer($customer->getId(), $event->getSalesChannelId(), $event->getContext());
        } catch (CouldNotCreateMollieCustomerException | CustomerCouldNotBeFoundException $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }
    }
}
