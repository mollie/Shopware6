<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Event\MollieOrderBuildEvent;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Fixer\OrderAmountDiffFixer;
use Kiener\MolliePayments\Service\MollieApi\Fixer\VerticalTaxLineItemFixer;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class MollieOrderBuilder
{
    public const MOLLIE_DEFAULT_LOCALE_CODE = 'en_GB';

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;

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
     * @var MollieShippingLineItemBuilder
     */
    private $shippingLineItemBuilder;

    /**
     * @var RoutingBuilder
     */
    private $urlBuilder;

    /**
     * @var VerticalTaxLineItemFixer
     */
    private $verticalTaxLineItemFixer;

    /**
     * @var OrderAmountDiffFixer
     */
    private $orderAmountFixer;

    /**
     * @var MollieLineItemHydrator
     */
    private $mollieLineItemHydrator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;


    /**
     * @param SettingsService $settingsService
     * @param OrderDataExtractor $extractor
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param MollieLineItemBuilder $lineItemBuilder
     * @param MollieOrderAddressBuilder $addressBuilder
     * @param MollieOrderCustomerEnricher $customerEnricher
     * @param LoggerInterface $loggerService
     * @param MollieShippingLineItemBuilder $shippingLineItemBuilder
     * @param VerticalTaxLineItemFixer $verticalTaxLineItemFixer
     * @param OrderAmountDiffFixer $orderAmountDiffFixer
     * @param MollieLineItemHydrator $mollieLineItemHydrator
     * @param EventDispatcherInterface $eventDispatcher
     * @param RoutingBuilder $routingBuilder
     */
    public function __construct(SettingsService $settingsService, OrderDataExtractor $extractor, MollieOrderPriceBuilder $priceBuilder, MollieLineItemBuilder $lineItemBuilder, MollieOrderAddressBuilder $addressBuilder, MollieOrderCustomerEnricher $customerEnricher, LoggerInterface $loggerService, MollieShippingLineItemBuilder $shippingLineItemBuilder, VerticalTaxLineItemFixer $verticalTaxLineItemFixer, OrderAmountDiffFixer $orderAmountDiffFixer, MollieLineItemHydrator $mollieLineItemHydrator, EventDispatcherInterface $eventDispatcher, RoutingBuilder $routingBuilder)
    {
        $this->settingsService = $settingsService;
        $this->logger = $loggerService;
        $this->extractor = $extractor;
        $this->priceBuilder = $priceBuilder;
        $this->lineItemBuilder = $lineItemBuilder;
        $this->addressBuilder = $addressBuilder;
        $this->customerEnricher = $customerEnricher;
        $this->shippingLineItemBuilder = $shippingLineItemBuilder;
        $this->verticalTaxLineItemFixer = $verticalTaxLineItemFixer;
        $this->mollieLineItemHydrator = $mollieLineItemHydrator;
        $this->eventDispatcher = $eventDispatcher;
        $this->urlBuilder = $routingBuilder;
        $this->orderAmountFixer = $orderAmountDiffFixer;
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
        $webhookUrl = $this->urlBuilder->buildWebhookURL($transactionId);
        $isVerticalTaxCalculation = $this->isVerticalTaxCalculation($salesChannelContext);

        $orderData = [];


        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $orderData['amount'] = $this->priceBuilder->build($order->getAmountNet(), $currency->getIsoCode());
        } else {
            $orderData['amount'] = $this->priceBuilder->build($order->getAmountTotal(), $currency->getIsoCode());
        }

        $orderData['locale'] = $localeCode;
        $orderData['method'] = $paymentMethod;
        $orderData['orderNumber'] = $order->getOrderNumber();
        $orderData['payment'] = $paymentData;

        $orderData['redirectUrl'] = $this->urlBuilder->buildReturnUrl($transactionId);
        $orderData['webhookUrl'] = $webhookUrl;
        $orderData['payment']['webhookUrl'] = $webhookUrl;


        if ($lineItems instanceof OrderLineItemCollection && $this->isSubscriptions($lineItems->getElements())) {
            $orderData['payment']['sequenceType'] = 'first';
        }

        # ----------------------------------------------------------------------------------------------------------------------------

        $mollieOrderLines = $this->lineItemBuilder->buildLineItems($order->getTaxStatus(), $order->getNestedLineItems(), $isVerticalTaxCalculation);

        $deliveries = $order->getDeliveries();

        if ($deliveries instanceof OrderDeliveryCollection) {
            $shippingLineItems = $this->shippingLineItemBuilder->buildShippingLineItems(
                $order->getTaxStatus(),
                $deliveries,
                $isVerticalTaxCalculation
            );

            foreach ($shippingLineItems as $shipping) {
                $mollieOrderLines->add($shipping);
            }
        }

        # with the vertical tax calculation
        # it can be that there are larger diffs due to rounding
        # this is a special treatment that is handled with our separate
        # line item fixer class
        if ($isVerticalTaxCalculation) {
            $this->verticalTaxLineItemFixer->fixLineItems($mollieOrderLines, $salesChannelContext);
        }

        # in cases with items that have multiple decimals
        # it can be that it's just not possible to calculate the correct amounts.
        # Shopware shows 5 decimals in the checkout, while the total sum might be rounded to 2 decimals.
        # If such a scenario happens, it can be that we have 1 cent off. In that case,
        # we have a separate fixer that adjusts the line items of Mollie for this diff.
        $orderTotalAmount = (float)$orderData['amount']['value'];
        $mollieOrderLines = $this->orderAmountFixer->fixSmallAmountDiff($orderTotalAmount, $mollieOrderLines);


        $orderData['lines'] = $this->mollieLineItemHydrator->hydrate($mollieOrderLines, $currency->getIsoCode());

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

        if ($orderAttributes->isTypeSubscription() || $settings->createCustomersAtMollie()) {
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
