<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PhoneNumber;
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
        #[Autowire(service: LineCollectionBuilder::class)]
        private readonly LineCollectionBuilderInterface $lineCollectionBuilder,
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
        $currency = $transactionData->getCurrency();
        $language = $transactionData->getLanguage();
        $salesChannelName = (string) $transactionData->getSalesChannel()->getName();
        $shippingOrderAddress = $transactionData->getShippingOrderAddress();
        $billingOrderAddress = $transactionData->getBillingOrderAddress();
        $deliveries = $transactionData->getDeliveries();

        $paymentSettings = $this->settingsService->getPaymentSettings($order->getSalesChannelId());
        $orderNumberFormat = $paymentSettings->getOrderNumberFormat();

        $customerNumber = $customer->getCustomerNumber();
        $description = (string) $order->getOrderNumber();
        $orderNumber = (string) $order->getOrderNumber();
        $taxStatus = (string) $order->getTaxStatus();
        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $profileId = $apiSettings->getProfileId();

        if (mb_strlen($profileId) === 0) {
            $profile = $this->mollieGateway->getCurrentProfile($salesChannelId);
            $profileId = $profile->getId();
        }

        $logData = [
            'salesChannel' => $salesChannelName,
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'taxStatus' => $taxStatus,
        ];

        if (mb_strlen($orderNumberFormat) > 0) {
            $description = str_replace([
                '{ordernumber}',
                '{customernumber}'
            ], [
                $orderNumber,
                $customerNumber
            ], $orderNumberFormat);
        }

        $returnUrl = $this->routeBuilder->getReturnUrl($transactionId);
        $webhookUrl = $this->routeBuilder->getWebhookUrl($transactionId);

        $lineItemCollection = $this->lineCollectionBuilder->build($order, $deliveries, $currency, $taxStatus);

        $oderLineItems = $order->getLineItems();
        $hasSubscriptionLineItem = false;
        if ($oderLineItems !== null) {
            $subscriptionsEnabled = $this->settingsService->getSubscriptionSettings($salesChannelId)->isEnabled();
            $hasSubscriptionLineItem = $subscriptionsEnabled && $this->lineItemAnalyzer->hasSubscriptionProduct($oderLineItems);
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
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

        if ($paymentHandler instanceof ManualCaptureModeAwareInterface) {
            $createPaymentStruct->setCaptureMode(CaptureMode::MANUAL);
        }

        if ($paymentHandler instanceof BankTransferAwareInterface && $paymentSettings->getDueDateDays() > 0) {
            $dueDate = new \DateTime('now', new \DateTimeZone('UTC'));
            $dueDate->modify('+' . $paymentSettings->getDueDateDays() . ' days');
            $createPaymentStruct->setDueDate($dueDate);
        }

        $mollieCustomerExtension = $customer->getExtension(Mollie::EXTENSION);

        if ($mollieCustomerExtension instanceof CustomerExtension) {
            $mollieCustomerId = $mollieCustomerExtension->getForProfileId($profileId, $apiSettings->getMode());
            if ($mollieCustomerId !== null) {
                $createPaymentStruct->setCustomerId($mollieCustomerId);
            }
        }

        if (! $customer->getGuest()) {
            $createPaymentStruct = $this->modifySequenceType($createPaymentStruct, $paymentHandler, $dataBag, $salesChannelId, $hasSubscriptionLineItem);

            if (
                (
                    $createPaymentStruct->getSequenceType() !== SequenceType::ONEOFF
                    || $paymentSettings->forceCustomerCreation()
                )
                && $createPaymentStruct->getCustomerId() === null
            ) {
                $mollieCustomer = $this->mollieGateway->createCustomer($customer, $salesChannelId);
                $createPaymentStruct->setCustomerId($mollieCustomer->getId());

                $customer = $this->saveCustomerId($customer, $mollieCustomer, $profileId, $apiSettings->getMode(), $context);

                $this->logger->info('Mollie customer created and assigned to shopware customer', $logData);
            }
        }

        /** @var CreatePayment $createPaymentStruct */
        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $dataBag, $customer);

        // after the handler parameters, so numbers set there (e.g. Bancomat Pay/Bizum) are covered too
        $this->normalizePhoneNumber($createPaymentStruct->getShippingAddress(), $logData);
        $this->normalizePhoneNumber($createPaymentStruct->getBillingAddress(), $logData);

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

        $orderLogData = ['orderNumber' => $createPayment->getShopwareOrderNumber()];
        $orderShippingAddress = $createOrder->getShippingAddress();
        if ($orderShippingAddress !== null) {
            $this->normalizePhoneNumber($orderShippingAddress, $orderLogData);
        }
        $this->normalizePhoneNumber($createOrder->getBillingAddress(), $orderLogData);

        $this->logger->info('Order payload created for mollie API', [
            'orderNumber' => $createPayment->getShopwareOrderNumber(),
            'payload' => $createOrder->toArray(),
        ]);

        return $createOrder;
    }

    private function modifySequenceType(CreatePayment $createPaymentStruct, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, string $salesChannelId, bool $hasSubscriptionLineItem): CreatePayment
    {
        $savePaymentDetails = $dataBag->get('savePaymentDetails', false);

        if ($savePaymentDetails || ($hasSubscriptionLineItem && $paymentHandler instanceof SubscriptionAwareInterface)) {
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

    /**
     * Mollie rejects phone numbers that are not in E.164 format and fails the whole payment.
     * To keep the checkout working we first try to normalize the number to E.164 (most
     * customers enter their number in national format) and drop it from the payload if it
     * cannot be normalized. Only a masked hint (never the full number) is logged.
     *
     * @param array<string, mixed> $logData
     */
    private function normalizePhoneNumber(Address $address, array $logData): void
    {
        $phone = $address->getPhone();
        if ($phone === '' || PhoneNumber::isValidE164($phone)) {
            return;
        }

        $logData['phoneHint'] = mb_substr($phone, 0, 2) . '***';

        $normalized = PhoneNumber::toE164($phone, $address->getCountry());
        if ($normalized !== '') {
            $address->setPhone($normalized);

            $this->logger->info('Phone number was normalized to E.164 format for the mollie payload', $logData);

            return;
        }

        $address->setPhone('');

        $this->logger->warning('Phone number is not in E.164 format and was removed from the mollie payload to prevent a payment failure', $logData);
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
