<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\RecurringAwareInterface;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
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

final readonly class CreatePaymentBuilder implements CreatePaymentBuilderInterface
{
    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     */
    public function __construct(
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'customer.repository')]
        private EntityRepository $customerRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function build(TransactionDataStruct $transactionData, AbstractMolliePaymentHandler $paymentHandler, RequestDataBag $dataBag, Context $context): CreatePayment
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

        $lineItemCollection = new LineItemCollection();
        $oderLineItems = $order->getLineItems();
        if ($oderLineItems !== null) {
            foreach ($oderLineItems as $lineItem) {
                $lineItem = LineItem::fromOrderLine($lineItem, $currency);
                $lineItemCollection->add($lineItem);
            }
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

            if ($delivery->getShippingCosts()->getTotalPrice() <= 0) {
                continue;
            }

            $lineItem = LineItem::fromDelivery($delivery, $currency);
            $lineItemCollection->add($lineItem);
        }

        $billingAddress = Address::fromAddress($customer, $billingOrderAddress);

        $createPaymentStruct = new CreatePayment($description, $returnUrl, Money::fromOrder($order, $currency));

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
        $mollieCustomerId = null;
        if ($mollieCustomerExtension instanceof CustomerExtension) {
            $mollieCustomerId = $mollieCustomerExtension->getForProfileId($profileId);
            if ($mollieCustomerId !== null) {
                $createPaymentStruct->setCustomerId($mollieCustomerId);
            }
        }

        $savePaymentDetails = $dataBag->get('savePaymentDetails', false);
        if (! $customer->getGuest() && $savePaymentDetails) {
            $createPaymentStruct->setSequenceType(SequenceType::FIRST);
        }

        $mandateId = $dataBag->get('mandateId');

        if (! $customer->getGuest() && $mollieCustomerId && $mandateId && $paymentHandler instanceof RecurringAwareInterface) {
            $mandates = $this->mollieGateway->listMandates($mollieCustomerId, $salesChannelId);
            $paymentMethodMandates = $mandates->filterByPaymentMethod($paymentHandler->getPaymentMethod());
            $mandate = $paymentMethodMandates->get($mandateId);
            if ($mandate instanceof Mandate) {
                $createPaymentStruct->setMandateId($mandateId);
                $createPaymentStruct->setSequenceType(SequenceType::RECURRING);
            }
        }

        if (! $customer->getGuest() && $createPaymentStruct->getSequenceType() !== SequenceType::ONEOFF && $createPaymentStruct->getCustomerId() === null) {
            $mollieCustomer = $this->mollieGateway->createCustomer($customer, $salesChannelId);
            $createPaymentStruct->setCustomerId($mollieCustomer->getId());

            $customer = $this->saveCustomerId($customer, $mollieCustomer, $profileId, $context);
            $this->logger->info('Mollie customer created and assigned to shopware customer', $logData);
        }
        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $dataBag, $customer);

        $logData['payload'] = $createPaymentStruct->toArray();
        $this->logger->info('Payment payload created for mollie API', $logData);

        return $createPaymentStruct;
    }

    private function saveCustomerId(CustomerEntity $customerEntity, Customer $mollieCustomer, string $profileId, Context $context): CustomerEntity
    {
        $customerExtension = new CustomerExtension();
        $customerExtension->setCustomerId($profileId, $mollieCustomer->getId());
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
