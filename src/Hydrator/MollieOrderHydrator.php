<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;


use Kiener\MolliePayments\Exception\OrderCustomerNotFound;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderCustomerEnricher;
use Kiener\MolliePayments\Service\MollieApi\MollieOrderLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MollieOrderHydrator
{
    public const MOLLIE_PAYMENT_ROUTE = '';

    /**
     * @var SettingsService
     */
    private $settingsService;
    /**
     * @var CustomerService
     */
    private $customerService;
    /**
     * @var LoggerService
     */
    private $loggerService;
    /**
     * @var OrderDataExtractor
     */
    private $extractor;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var MolliePriceHydrator
     */
    private $priceHydrator;
    /**
     * @var MollieOrderLineItemBuilder
     */
    private $builder;
    /**
     * @var MollieAddressHydrator
     */
    private $addressHydrator;
    /**
     * @var MollieOrderCustomerEnricher
     */
    private MollieOrderCustomerEnricher $enricher;

    public function __construct(
        SettingsService $settingsService,
        CustomerService $customerService,
        OrderDataExtractor $extractor,
        RouterInterface $router,
        MolliePriceHydrator $priceHydrator,
        MollieOrderLineItemBuilder $builder,
        MollieAddressHydrator $addressHydrator,
        MollieOrderCustomerEnricher $enricher,
        LoggerService $loggerService
    )
    {
        $this->settingsService = $settingsService;
        $this->customerService = $customerService;
        $this->loggerService = $loggerService;
        $this->extractor = $extractor;
        $this->router = $router;
        $this->priceHydrator = $priceHydrator;
        $this->builder = $builder;
        $this->addressHydrator = $addressHydrator;
        $this->enricher = $enricher;
    }

    public function hydrate(
        OrderEntity $order,
        string $transactionId,
        string $paymentMethod,
        string $returnUrl,
        SalesChannelContext $salesChannelContext,
        PaymentHandler $handler,
        array $paymentData = []
    ): array
    {
        $customer = $this->getCustomer($order, $salesChannelContext);
        $currency = $this->extractor->extractCurrency($order, $salesChannelContext);
        $locale = $this->extractor->extractLocaleCode($order, $salesChannelContext);

        $orderData = [];
        $orderData['amount'] = $this->priceHydrator->hydrate($order->getAmountTotal(), $currency->getIsoCode());

        // create urls
        $orderData['redirectUrl'] = $this->router->generate(
            'frontend.mollie.payment',
            [
                'transactionId' => $transactionId,
                'returnUrl' => urlencode($returnUrl),
            ],
            $this->router::ABSOLUTE_URL
        );
        $orderData['webhookUrl'] = $this->router->generate(
            'frontend.mollie.webhook',
            ['transactionId' => $transactionId],
            $this->router::ABSOLUTE_URL
        );

        $orderData['locale'] = $locale;
        $orderData['method'] = $paymentMethod;
        $orderData['orderNumber'] = $order->getOrderNumber();
        $orderData['lines'] = $this->builder->buildLineItems($order);
        $orderData['billingAddress'] = $this->addressHydrator->hydrate($customer->getEmail(), $customer->getDefaultBillingAddress());
        $orderData['shippingAddress'] = $this->addressHydrator->hydrate($customer->getEmail(), $customer->getActiveShippingAddress());
        $orderData['payment'] = $paymentData;

        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $orderData['amount'] = $this->priceHydrator->hydrate($order->getAmountNet(), $currency->getIsoCode());
        }

        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext()
        );

        // set order lifetime like configred
        $dueDate = $settings->getOrderLifetimeDate();

        if ($dueDate !== null) {
            $orderData['expiresAt'] = $dueDate;
        }

        // add payment specific data
        $orderData = $handler->processPaymentMethodSpecificParameters($orderData, $salesChannelContext, $customer, $locale);

        // enrich data with create customer at mollie
        $orderData=$this->enricher->enrich($orderData, $customer, $settings);

        return $orderData;
    }

    private function getCustomer(OrderEntity $orderEntity, SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $orderCustomer = $this->extractor->extractCustomer($orderEntity, $salesChannelContext);

        $enrichedCustomer = $this->customerService->getCustomer(
            $orderCustomer->getCustomerId(),
            $salesChannelContext->getContext()
        );

        if (!$enrichedCustomer instanceof CustomerEntity) {
            $this->loggerService->addEntry(
                sprintf('Could not fetch customer form order with id %s', $orderEntity->getId()),
                $salesChannelContext->getContext(),
                null,
                [],
                Logger::CRITICAL
            );

            throw new OrderCustomerNotFound($orderEntity->getId());
        }

        return $enrichedCustomer;
    }
}
