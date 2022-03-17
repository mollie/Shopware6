<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Event\MollieOrderBuildEvent;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\VerticalTaxLineItemFixer;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
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
     * @var RouterInterface
     */
    private $router;

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
     * @var WebhookBuilder
     */
    private $webhookBuilder;

    /**
     * @var VerticalTaxLineItemFixer
     */
    private $verticalTaxLineItemFixer;

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
     * @param RouterInterface $router
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param MollieLineItemBuilder $lineItemBuilder
     * @param MollieOrderAddressBuilder $addressBuilder
     * @param MollieOrderCustomerEnricher $customerEnricher
     * @param LoggerInterface $loggerService
     * @param MollieShippingLineItemBuilder $shippingLineItemBuilder
     * @param VerticalTaxLineItemFixer $verticalTaxLineItemFixer
     * @param MollieLineItemHydrator $mollieLineItemHydrator
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(SettingsService $settingsService, OrderDataExtractor $extractor, RouterInterface $router, MollieOrderPriceBuilder $priceBuilder, MollieLineItemBuilder $lineItemBuilder, MollieOrderAddressBuilder $addressBuilder, MollieOrderCustomerEnricher $customerEnricher, LoggerInterface $loggerService, MollieShippingLineItemBuilder $shippingLineItemBuilder, VerticalTaxLineItemFixer $verticalTaxLineItemFixer, MollieLineItemHydrator $mollieLineItemHydrator, EventDispatcherInterface $eventDispatcher)
    {
        $this->settingsService = $settingsService;
        $this->logger = $loggerService;
        $this->extractor = $extractor;
        $this->router = $router;
        $this->priceBuilder = $priceBuilder;
        $this->lineItemBuilder = $lineItemBuilder;
        $this->addressBuilder = $addressBuilder;
        $this->customerEnricher = $customerEnricher;
        $this->shippingLineItemBuilder = $shippingLineItemBuilder;
        $this->verticalTaxLineItemFixer = $verticalTaxLineItemFixer;
        $this->mollieLineItemHydrator = $mollieLineItemHydrator;
        $this->eventDispatcher = $eventDispatcher;

        $this->webhookBuilder = new WebhookBuilder($router);
    }


    /**
     * @param OrderEntity $order
     * @param string $transactionId
     * @param string $paymentMethod
     * @param string $returnUrl
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler|null $handler
     * @param array $paymentData
     * @return array
     * @throws \Exception
     */
    public function build(OrderEntity $order, string $transactionId, string $paymentMethod, string $returnUrl, SalesChannelContext $salesChannelContext, ?PaymentHandler $handler, array $paymentData = []): array
    {
        $customer = $this->extractor->extractCustomer($order, $salesChannelContext);
        $currency = $this->extractor->extractCurrency($order, $salesChannelContext);
        $locale = $this->extractor->extractLocale($order, $salesChannelContext);
        $localeCode = ($locale instanceof LocaleEntity) ? $locale->getCode() : self::MOLLIE_DEFAULT_LOCALE_CODE;

        $orderData = [];
        $orderData['amount'] = $this->priceBuilder->build($order->getAmountTotal(), $currency->getIsoCode());
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $orderData['amount'] = $this->priceBuilder->build($order->getAmountNet(), $currency->getIsoCode());
        }
        $orderData['locale'] = $localeCode;
        $orderData['method'] = $paymentMethod;
        $orderData['orderNumber'] = $order->getOrderNumber();
        $orderData['payment'] = $paymentData;

        // create urls
        $redirectUrl = $this->router->generate(
            'frontend.mollie.payment',
            [
                'transactionId' => $transactionId
            ],
            $this->router::ABSOLUTE_URL
        );


        $orderData['redirectUrl'] = $redirectUrl;


        $webhookUrl = $this->webhookBuilder->buildWebhook($transactionId);
        $orderData['webhookUrl'] = $webhookUrl;
        $orderData['payment']['webhookUrl'] = $webhookUrl;


        $isVerticalTaxCalculation = $this->isVerticalTaxCalculation($salesChannelContext);


        $lines = $this->lineItemBuilder->buildLineItems(
            $order->getTaxStatus(),
            $order->getNestedLineItems(),
            $isVerticalTaxCalculation
        );


        $deliveries = $order->getDeliveries();

        if ($deliveries instanceof OrderDeliveryCollection) {

            $shippingLineItems = $this->shippingLineItemBuilder->buildShippingLineItems(
                $order->getTaxStatus(),
                $deliveries,
                $isVerticalTaxCalculation
            );

            /** @var MollieLineItem $shipping */
            foreach ($shippingLineItems as $shipping) {
                $lines->add($shipping);
            }
        }

        if ($isVerticalTaxCalculation) {
            $this->verticalTaxLineItemFixer->fixLineItems($lines, $salesChannelContext);
        }

        $orderData['lines'] = $this->mollieLineItemHydrator->hydrate($lines, $currency->getIsoCode());

        $orderData['billingAddress'] = $this->addressBuilder->build($customer->getEmail(), $customer->getDefaultBillingAddress());
        $orderData['shippingAddress'] = $this->addressBuilder->build($customer->getEmail(), $customer->getActiveShippingAddress());


        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());

        // set order lifetime like configured
        $dueDate = $settings->getOrderLifetimeDate();

        if ($dueDate !== null) {
            $orderData['expiresAt'] = $dueDate;
        }


        // add payment specific data
        if ($handler instanceof PaymentHandler) {

            $orderData = $handler->processPaymentMethodSpecificParameters(
                $orderData,
                $order,
                $salesChannelContext,
                $customer
            );
        }

        // enrich data with create customer at mollie
        $orderData = $this->customerEnricher->enrich($orderData, $customer, $settings, $salesChannelContext);

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
            $orderData['metadata'] = json_encode($event->getMetadata());
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

}
