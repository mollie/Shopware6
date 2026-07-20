<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\LineItemFilterInterface;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixerInterface;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\RecurringAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Entity\Customer\Customer as CustomerExtension;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PayloadBuilder implements PayloadBuilderInterface
{
    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     */
    public function __construct(
        #[Autowire(service: RouteBuilder::class)]
        private readonly RouteBuilderInterface $routeBuilder,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
        #[Autowire(service: LineItemFilter::class)]
        private readonly LineItemFilterInterface $lineItemFilter,
        #[Autowire(service: RoundingDifferenceFixer::class)]
        private readonly RoundingDifferenceFixerInterface $roundingDifferenceFixer,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function buildPayment(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreatePayment
    {
        $transactionId = $transactionData->getTransaction()->getId();
        $order = $transactionData->getOrder();
        $salesChannelId = $order->getSalesChannelId();
        $customer = $transactionData->getCustomer();
        $salesChannelName = (string) $transactionData->getSalesChannel()->getName();
        $orderNumber = (string) $order->getOrderNumber();
        $taxStatus = (string) $order->getTaxStatus();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        $logData = [
            'salesChannel' => $salesChannelName,
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'taxStatus' => $taxStatus,
        ];

        // buildBaseCreatePayment already sets the existing Mollie customer id (if any).
        $createPaymentStruct = $this->buildBaseCreatePayment($transactionData);

        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

        if ($paymentHandler instanceof ManualCaptureModeAwareInterface) {
            $createPaymentStruct->setCaptureMode(CaptureMode::MANUAL);
        }

        if ($paymentHandler instanceof BankTransferAwareInterface && $paymentSettings->getDueDateDays() > 0) {
            $dueDate = new \DateTime('now', new \DateTimeZone('UTC'));
            $dueDate->modify('+' . $paymentSettings->getDueDateDays() . ' days');
            $createPaymentStruct->setDueDate($dueDate);
        }

        if (! $customer->getGuest()) {
            $createPaymentStruct = $this->modifySequenceType($createPaymentStruct, $paymentHandler, $dataBag, $salesChannelId);
        }

        $createPaymentStruct = $this->ensureMollieCustomerId($createPaymentStruct, $customer, $salesChannelId, $context, $logData);

        /** @var CreatePayment $createPaymentStruct */
        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $dataBag, $customer);

        $logData['payload'] = $createPaymentStruct->toArray();
        $this->logger->info('Payment payload created for mollie API', $logData);

        return $createPaymentStruct;
    }

    public function buildOrder(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreateOrder
    {
        $createPayment = $this->buildPayment($transactionData, $paymentHandler, $dataBag, $context);

        $createOrder = new CreateOrder(
            $createPayment->getShopwareOrderNumber(),
            $createPayment->getRedirectUrl(),
            $createPayment->getAmount(),
            $createPayment->getLines(),
            $createPayment->getBillingAddress(),
            $createPayment->getLocale(),
        );

        $createOrder->setShippingAddress($createPayment->getShippingAddress());
        $createOrder->setWebhookUrl($createPayment->getWebhookUrl());
        $createOrder->setMethod($createPayment->getMethod());
        $createOrder->setMetadata(['shopwareOrderNumber' => $createPayment->getShopwareOrderNumber()]);

        if ($createPayment->getCustomerId() !== null) {
            $createOrder->setCustomerId($createPayment->getCustomerId());
        }

        /** @var CreateOrder $createOrder */
        $createOrder = $paymentHandler->applyPaymentSpecificParameters($createOrder, $dataBag, $transactionData->getCustomer());

        $this->logger->info('Order payload created for mollie API', [
            'orderNumber' => $createPayment->getShopwareOrderNumber(),
            'payload' => $createOrder->toArray(),
        ]);

        return $createOrder;
    }

    public function buildPaymentLink(TransactionDataStruct $transactionData, array $allowedMethods, ?AbstractMolliePaymentHandler $paymentHandler, Context $context): CreatePaymentLink
    {
        $order = $transactionData->getOrder();
        $salesChannelId = $order->getSalesChannelId();
        $customer = $transactionData->getCustomer();

        $logData = [
            'transactionId' => $transactionData->getTransaction()->getId(),
            'orderNumber' => (string) $order->getOrderNumber(),
            'allowedMethods' => $allowedMethods,
        ];

        // A payment link reuses the regular payment payload; the sequence type (first for
        // subscription orders) is already set by buildBaseCreatePayment().
        $createPayment = $this->buildBaseCreatePayment($transactionData);
        $createPayment = $this->ensureMollieCustomerId($createPayment, $customer, $salesChannelId, $context, $logData);

        // With exactly one allowed method the link behaves like that method's checkout, so its
        // payment-specific parameters are applied (there is no request data in this flow).
        if ($paymentHandler !== null) {
            $emptyDataBag = new RequestDataBag();
            /** @var CreatePayment $createPayment */
            $createPayment = $paymentHandler->applyPaymentSpecificParameters($createPayment, $emptyDataBag, $customer);
        }

        $createPaymentLink = new CreatePaymentLink(
            $createPayment->getDescription(),
            $createPayment->getRedirectUrl(),
            $createPayment->getAmount(),
            $createPayment->getLines(),
            $createPayment->getBillingAddress(),
            $createPayment->getShippingAddress(),
            $createPayment->getSequenceType(),
        );
        $createPaymentLink->setWebhookUrl($createPayment->getWebhookUrl());
        $createPaymentLink->setAllowedMethods($allowedMethods);

        $customerId = $createPayment->getCustomerId();
        if ($customerId !== null) {
            $createPaymentLink->setCustomerId($customerId);
        }

        $logData['payload'] = $createPaymentLink->toArray();
        $this->logger->info('Payment link payload created for mollie API', $logData);

        return $createPaymentLink;
    }

    /**
     * Assembles the payload parts that are identical for the regular checkout and for pay-by-link:
     * description, return/webhook url, line items (incl. rounding fix), billing/shipping address,
     * amount and locale. Whether the order contains a subscription product is flagged on the
     * struct itself. Everything method-, capture-, sequence- and customer-specific is applied by
     * the caller.
     */
    private function buildBaseCreatePayment(TransactionDataStruct $transactionData): CreatePayment
    {
        $transactionId = $transactionData->getTransaction()->getId();
        $order = $transactionData->getOrder();
        $salesChannelId = $order->getSalesChannelId();
        $customer = $transactionData->getCustomer();
        $currency = $transactionData->getCurrency();
        $language = $transactionData->getLanguage();
        $shippingOrderAddress = $transactionData->getShippingOrderAddress();
        $billingOrderAddress = $transactionData->getBillingOrderAddress();
        $deliveries = $transactionData->getDeliveries();

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        $orderNumberFormat = $paymentSettings->getOrderNumberFormat();
        $orderNumber = (string) $order->getOrderNumber();
        $taxStatus = (string) $order->getTaxStatus();

        $description = $orderNumber;
        if (mb_strlen($orderNumberFormat) > 0) {
            $description = str_replace([
                '{ordernumber}',
                '{customernumber}'
            ], [
                $orderNumber,
                $customer->getCustomerNumber()
            ], $orderNumberFormat);
        }

        $returnUrl = $this->routeBuilder->getReturnUrl($transactionId);
        $webhookUrl = $this->routeBuilder->getWebhookUrl($transactionId);

        $lineItemCollection = new LineItemCollection();
        $orderLineItems = $order->getLineItems();
        $shippingDiscountLabel = $orderLineItems !== null ? LineItem::resolveDeliveryDiscountLabel($orderLineItems) : null;
        $hasSubscriptionLineItem = false;
        if ($orderLineItems !== null) {
            $filteredLineItems = $orderLineItems->filter($this->lineItemFilter->isItemAllowed(...));
            foreach ($filteredLineItems as $lineItem) {
                $lineItemCollection->add(LineItem::fromOrderLine($lineItem, $currency, $taxStatus));
            }
            $subscriptionsEnabled = $this->settingsService->getSubscriptionSettings($salesChannelId)->isEnabled();
            $hasSubscriptionLineItem = $subscriptionsEnabled && $this->lineItemAnalyzer->hasSubscriptionProduct($orderLineItems);
        }

        $shippingAddress = Address::fromAddress($customer, $shippingOrderAddress);

        foreach ($deliveries as $delivery) {
            $deliveryOrderShippingAddress = $delivery->getShippingOrderAddress();
            if (method_exists($order, 'getPrimaryOrderDeliveryId')
                && $deliveryOrderShippingAddress instanceof OrderAddressEntity
                && $order->getPrimaryOrderDeliveryId() !== null
                && $delivery->getId() === $order->getPrimaryOrderDeliveryId()
            ) {
                $shippingAddress = Address::fromAddress($customer, $deliveryOrderShippingAddress);
            }

            $shippingCosts = $delivery->getShippingCosts()->getTotalPrice();
            if (round($shippingCosts, 2) === 0.0) {
                continue;
            }

            $descriptionOverride = $shippingCosts < 0 ? $shippingDiscountLabel : null;
            $lineItemCollection->add(LineItem::fromDelivery($delivery, $currency, $taxStatus, $descriptionOverride));
        }

        $billingAddress = Address::fromAddress($customer, $billingOrderAddress);

        $orderAmount = Money::fromOrder($order, $currency);

        if ($paymentSettings->isFixRoundingDiffEnabled()) {
            $lineItemCollection = $this->roundingDifferenceFixer->fixAmountDiff(
                $orderAmount,
                $lineItemCollection,
                $paymentSettings->getFixRoundingDiffName(),
                $paymentSettings->getFixRoundingDiffSku()
            );
        }

        $createPaymentStruct = new CreatePayment($description, $returnUrl, $orderAmount);
        $createPaymentStruct->setBillingAddress($billingAddress);
        $createPaymentStruct->setShippingAddress($shippingAddress);
        $createPaymentStruct->setLines($lineItemCollection);
        $createPaymentStruct->setLocale(Locale::fromLanguage($language));
        $createPaymentStruct->setWebhookUrl($webhookUrl);
        $createPaymentStruct->setShopwareOrderNumber($orderNumber);

        // A subscription product always needs a first payment (to create a mandate). For the
        // checkout, modifySequenceType() reverts this to one-off when the chosen payment method
        // cannot handle subscriptions.
        if ($hasSubscriptionLineItem) {
            $createPaymentStruct->setSequenceType(SequenceType::FIRST);
        }

        // An existing Mollie customer id is always sent, regardless of subscription/sequence type.
        // Creating a customer (forced by settings or required by the sequence type) is left to the
        // caller via ensureMollieCustomerId(), once the sequence type is known.
        $existingCustomerId = $this->resolveExistingMollieCustomerId($customer, $salesChannelId);
        if ($existingCustomerId !== null) {
            $createPaymentStruct->setCustomerId($existingCustomerId);
        }

        return $createPaymentStruct;
    }

    /**
     * The already assigned Mollie customer id for this sales channel's profile/mode, or null when
     * the customer has none yet (or is a guest). Always sent with the payment when present.
     */
    private function resolveExistingMollieCustomerId(CustomerEntity $customer, string $salesChannelId): ?string
    {
        $mollieCustomerExtension = $customer->getExtension(Mollie::EXTENSION);
        if (! $mollieCustomerExtension instanceof CustomerExtension) {
            return null;
        }

        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);

        return $mollieCustomerExtension->getForProfileId($this->resolveProfileId($salesChannelId), $apiSettings->getMode());
    }

    /**
     * Ensures the payment has a Mollie customer id and returns the struct:
     * - keeps an already assigned customer id
     * - returns unchanged when neither the plugin setting forces a customer nor the sequence type
     *   requires one
     * - otherwise creates a Mollie customer and persists it on the Shopware customer - for guests
     *   too. A non-oneoff sequence (subscription/recurring) always needs a customer for the mandate,
     *   regardless of the force setting.
     *
     * @param array<string, mixed> $logData
     */
    private function ensureMollieCustomerId(CreatePayment $createPaymentStruct, CustomerEntity $customer, string $salesChannelId, Context $context, array $logData): CreatePayment
    {
        if ($createPaymentStruct->getCustomerId() !== null) {
            return $createPaymentStruct;
        }

        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        if (! $paymentSettings->forceCustomerCreation() && $createPaymentStruct->getSequenceType() === SequenceType::ONEOFF) {
            return $createPaymentStruct;
        }

        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $mollieCustomer = $this->mollieGateway->createCustomer($customer, $salesChannelId);
        $this->saveCustomerId($customer, $mollieCustomer, $this->resolveProfileId($salesChannelId), $apiSettings->getMode(), $context);
        $createPaymentStruct->setCustomerId($mollieCustomer->getId());

        $this->logger->info('Mollie customer created and assigned to shopware customer', $logData);

        return $createPaymentStruct;
    }

    private function resolveProfileId(string $salesChannelId): string
    {
        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $profileId = $apiSettings->getProfileId();
        if (mb_strlen($profileId) === 0) {
            $profileId = $this->mollieGateway->getCurrentProfile($salesChannelId)->getId();
        }

        return $profileId;
    }

    private function modifySequenceType(CreatePayment $createPaymentStruct, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, string $salesChannelId): CreatePayment
    {
        // buildBaseCreatePayment() already sets "first" for subscription orders. Payment methods
        // that cannot handle subscriptions fall back to a one-off payment.
        if ($createPaymentStruct->getSequenceType() === SequenceType::FIRST && ! $paymentHandler instanceof SubscriptionAwareInterface) {
            $createPaymentStruct->setSequenceType(SequenceType::ONEOFF);
        }

        // Storing payment details for later (customer-initiated) use also needs a first payment.
        $savePaymentDetails = $dataBag->get('savePaymentDetails', false);
        if ($savePaymentDetails) {
            $createPaymentStruct->setSequenceType(SequenceType::FIRST);
        }

        $mandateId = $dataBag->get('mandateId');
        $mollieCustomerId = $createPaymentStruct->getCustomerId();

        if (
            $mollieCustomerId
            && $mandateId
            && $paymentHandler instanceof RecurringAwareInterface
        ) {
            $mandates = $this->mollieGateway->listMandates($mollieCustomerId, $salesChannelId);
            $paymentMethodMandates = $mandates->filterByPaymentMethod($paymentHandler->getPaymentMethod());
            $mandate = $paymentMethodMandates->get($mandateId);
            if ($mandate instanceof Mandate) {
                $createPaymentStruct->setMandateId($mandateId);
                $createPaymentStruct->setSequenceType(SequenceType::RECURRING);
            }
        }

        return $createPaymentStruct;
    }

    private function saveCustomerId(CustomerEntity $customerEntity, Customer $mollieCustomer, string $profileId, Mode $mode, Context $context): CustomerEntity
    {
        $customerExtension = new CustomerExtension();
        $customerExtension->setCustomerId($profileId, $mode, $mollieCustomer->getId());
        $customerEntity->addExtension(Mollie::EXTENSION, $customerExtension);

        $customerCustomFields = $customerEntity->getCustomFields() ?? [];
        $customerCustomFields[Mollie::EXTENSION] = $customerExtension->toArray();
        $customerEntity->setCustomFields($customerCustomFields);

        $this->customerRepository->upsert([
            [
                'id' => $customerEntity->getId(),
                'customFields' => $customerCustomFields
            ]
        ], $context);

        return $customerEntity;
    }
}
