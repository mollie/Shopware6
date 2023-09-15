<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Event\MollieOrderBuildEvent;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MollieOrderBuilder
{
    public const MOLLIE_DEFAULT_LOCALE_CODE = 'en_GB';

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var OrderDataExtractor
     */
    private $extractor;

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceBuilder;

    /**
     * @var MollieLineItemBuilder
     */
    private $lineItemBuilder;

    /**
     * @var MollieOrderAddressBuilder
     */
    private $addressBuilder;

    /**
     * @var MollieOrderCustomerEnricher
     */
    private $customerEnricher;

    /**
     * @var RoutingBuilder
     */
    private $urlBuilder;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SettingsService $settingsService
     * @param OrderDataExtractor $extractor
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param MollieLineItemBuilder $lineItemBuilder
     * @param MollieOrderAddressBuilder $addressBuilder
     * @param MollieOrderCustomerEnricher $customerEnricher
     * @param RoutingBuilder $urlBuilder
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settingsService, OrderDataExtractor $extractor, MollieOrderPriceBuilder $priceBuilder, MollieLineItemBuilder $lineItemBuilder, MollieOrderAddressBuilder $addressBuilder, MollieOrderCustomerEnricher $customerEnricher, RoutingBuilder $urlBuilder, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->extractor = $extractor;
        $this->priceBuilder = $priceBuilder;
        $this->lineItemBuilder = $lineItemBuilder;
        $this->addressBuilder = $addressBuilder;
        $this->customerEnricher = $customerEnricher;
        $this->urlBuilder = $urlBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }


    /**
     * @param OrderEntity $order
     * @param string $transactionId
     * @param string $paymentMethod
     * @param SalesChannelContext $salesChannelContext
     * @param null|PaymentHandler $handler
     * @param array<mixed> $paymentData
     * @throws \Exception
     * @return array<mixed>
     */
    public function build(OrderEntity $order, string $transactionId, string $paymentMethod, SalesChannelContext $salesChannelContext, ?PaymentHandler $handler, array $paymentData = []): array
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings($order->getSalesChannelId());

        $customer = $this->extractor->extractCustomer($order, $salesChannelContext);
        $currency = $this->extractor->extractCurrency($order, $salesChannelContext);
        $locale = $this->extractor->extractLocale($order, $salesChannelContext);
        $localeCode = ($locale instanceof LocaleEntity) ? $locale->getCode() : self::MOLLIE_DEFAULT_LOCALE_CODE;
        $lineItems = $order->getLineItems();
        $isVerticalTaxCalculation = $this->isVerticalTaxCalculation($salesChannelContext);

        $orderData = [];


        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $orderData['amount'] = $this->priceBuilder->build($order->getAmountNet(), $currency->getIsoCode());
        } else {
            $orderData['amount'] = $this->priceBuilder->build($order->getAmountTotal(), $currency->getIsoCode());
        }

        # build custom format
        # TODO this is just inline code, but it's unit tested, but maybe we should move it to a separate class too, and switch to unit tests + integration tests
        if (!empty(trim($settings->getFormatOrderNumber()))) {
            $orderNumberFormatted = $settings->getFormatOrderNumber();
            $orderNumberFormatted = str_replace('{ordernumber}', (string)$order->getOrderNumber(), (string)$orderNumberFormatted);

            $orderCustomer = $order->getOrderCustomer();
            if ($orderCustomer instanceof OrderCustomerEntity) {
                $orderNumberFormatted = str_replace('{customernumber}', (string)$orderCustomer->getCustomerNumber(), (string)$orderNumberFormatted);
            }
        } else {
            $orderNumberFormatted = $order->getOrderNumber();
        }

        $orderData['locale'] = $localeCode;
        $orderData['method'] = $paymentMethod;
        $orderData['orderNumber'] = $orderNumberFormatted;
        $orderData['payment'] = $paymentData;


        $redirectUrl = $this->urlBuilder->buildReturnUrl($transactionId);
        $webhookUrl = $this->urlBuilder->buildWebhookURL($transactionId);

        $orderData['redirectUrl'] = $redirectUrl;
        $orderData['webhookUrl'] = $webhookUrl;
        $orderData['payment']['webhookUrl'] = $webhookUrl;


        if ($settings->isSubscriptionsEnabled() && $lineItems instanceof OrderLineItemCollection && $this->isSubscriptions($lineItems->getElements())) {
            $orderData['payment']['sequenceType'] = 'first';
        }

        # ----------------------------------------------------------------------------------------------------------------------------

        $orderData['lines'] = $this->lineItemBuilder->buildLineItemPayload(
            $order,
            $currency->getIsoCode(),
            $settings,
            $isVerticalTaxCalculation
        );

        # ----------------------------------------------------------------------------------------------------------------------------

        $orderData['billingAddress'] = $this->addressBuilder->build($customer->getEmail(), $customer->getDefaultBillingAddress());
        $orderData['shippingAddress'] = $this->addressBuilder->build($customer->getEmail(), $customer->getActiveShippingAddress());

        // set order lifetime like configured
        $dueDate = $settings->getOrderLifetimeDate();

        if ($dueDate !== null) {
            $orderData['expiresAt'] = $dueDate;
        }

        # add payment specific data
        if ($handler instanceof PaymentHandler) {
            # set CreditCardPayment singleClickPayment true if Single click payment feature is enabled
            if ($handler instanceof CreditCardPayment && $settings->isOneClickPaymentsEnabled()) {
                $handler->setEnableSingleClickPayment(true);
            }

            $orderData = $handler->processPaymentMethodSpecificParameters(
                $orderData,
                $order,
                $salesChannelContext,
                $customer
            );
        }

        # ----------------------------------------------------------------------------------------------------------------------------

        // enrich data with create customer at mollie
        $orderAttributes = new OrderAttributes($order);

        if ($orderAttributes->isTypeSubscription() || $settings->createCustomersAtMollie() || $settings->isOneClickPaymentsEnabled()) {
            $orderData = $this->customerEnricher->enrich($orderData, $customer, $settings, $salesChannelContext);
        }

        # ----------------------------------------------------------------------------------------------------------------------------

        $this->logger->debug(
            sprintf('Preparing Shopware Order %s to be sent to Mollie', $order->getOrderNumber()),
            [
                'amount' => $orderData['amount'],
                'locale' => $orderData['locale'],
                'method' => $orderData['method'],
                'lines' => $orderData['lines'],
            ]
        );


        # we want to give people the chance to adjust the
        # amounts that will be used when building the order.
        # we do not guarantee anything if this event is consumed by another plugin!
        # but valid use cases might be the injection of custom metadata for example.
        # please do not use this unless you know what you are doing!
        $event = new MollieOrderBuildEvent($orderData, $order, $transactionId, $salesChannelContext);
        $event = $this->eventDispatcher->dispatch($event);

        if (!$event instanceof MollieOrderBuildEvent) {
            throw new \Exception('Event Dispatcher did not return a MollieOrderBuilder event. No mollie order data is available');
        }

        # now check if we have metadata
        # and add it to our order if existing
        if (!empty($event->getMetadata())) {
            $orderData['metadata'] = (string)json_encode($event->getMetadata());
        }

        return $orderData;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return bool
     */
    private function isVerticalTaxCalculation(SalesChannelContext $salesChannelContext): bool
    {
        $salesChannel = $salesChannelContext->getSalesChannel();

        if (!method_exists($salesChannel, 'getTaxCalculationType')) {
            return false;
        }

        return $salesChannel->getTaxCalculationType() === SalesChannelDefinition::CALCULATION_TYPE_VERTICAL;
    }

    /**
     * @param array<mixed> $lines
     * @return bool
     */
    private function isSubscriptions($lines): bool
    {
        foreach ($lines as $line) {
            $attributes = new OrderLineItemEntityAttributes($line);

            if ($attributes->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }
}
