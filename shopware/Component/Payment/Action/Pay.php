<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Action;

use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilder;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilderInterface;
use Mollie\Shopware\Component\Mollie\Customer;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\BankTransferAwareInterface;
use Mollie\Shopware\Component\Payment\Handler\ManualCaptureModeAwareInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Entity\Customer\Customer as CustomerExtension;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class Pay
{
    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     */
    public function __construct(
        #[Autowire(service: TransactionService::class)]
        private TransactionServiceInterface $transactionService,
        #[Autowire(service: CreatePaymentBuilder::class)]
        private CreatePaymentBuilderInterface $createPaymentBuilder,
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: OrderTransactionStateHandler::class)]
        private OrderTransactionStateHandler $stateMachineHandler,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'customer.repository')]
        private EntityRepository $customerRepository,
        #[Autowire(service: 'event_dispatcher')]
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function execute(AbstractMolliePaymentHandler $paymentHandler, PaymentTransactionStruct $transaction, RequestDataBag $dataBag, Context $context): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransactionId();
        $shopwareFinalizeUrl = (string) $transaction->getReturnUrl();

        $transactionDataStruct = $this->transactionService->findById($transactionId, $context);

        $order = $transactionDataStruct->getOrder();
        $transaction = $transactionDataStruct->getTransaction();
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannel = $transactionDataStruct->getSalesChannel();
        $salesChannelName = (string) $salesChannel->getName();

        $logData = [
            'salesChannel' => $salesChannelName,
            'paymentMethod' => $paymentHandler->getPaymentMethod()->value,
            'orderNumber' => $orderNumber,
            'transactionId' => $transactionId,
        ];

        $this->logger->info('Start - Mollie payment', $logData);

        $createPaymentStruct = $this->createPaymentStruct($transactionDataStruct, $paymentHandler, $dataBag, $salesChannelName, $context);

        $countPayments = $this->updatePaymentCounter($transaction, $createPaymentStruct);

        $payment = $this->mollieGateway->createPayment($createPaymentStruct, $salesChannel->getId());

        $payment->setFinalizeUrl($shopwareFinalizeUrl);
        $payment->setCountPayments($countPayments);

        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

        $this->processPaymentStatus($paymentHandler, $transactionId, $orderNumber, $context);

        $redirectUrl = $payment->getCheckoutUrl();
        if (mb_strlen($redirectUrl) === 0) {
            $redirectUrl = $shopwareFinalizeUrl;
        }
        $logData['redirectUrl'] = $redirectUrl;
        $this->logger->info('Finished - Mollie payment, redirecting', $logData);

        return new RedirectResponse($redirectUrl);
    }

    private function processPaymentStatus(AbstractMolliePaymentHandler $paymentHandler, string $transactionId, string $orderNumber, Context $context): void
    {
        try {
            $method = 'processUnconfirmed';
            if ($paymentHandler instanceof BankTransferAwareInterface) {
                $method = 'process';
            }

            $this->stateMachineHandler->{$method}($transactionId, $context);
            $this->logger->info('Changed payment status', [
                'transactionId' => $transactionId,
                'orderNumber' => $orderNumber,
                'method' => $method,
            ]);
        } catch (IllegalTransitionException $exception) {
            $this->logger->error('Failed to change payment status', [
                'transactionId' => $transactionId,
                'reason' => $exception->getMessage(),
                'orderNumber' => $orderNumber,
            ]);
        }
    }

    private function createPaymentStruct(TransactionDataStruct $transaction,
        AbstractMolliePaymentHandler $paymentHandler,
        RequestDataBag $dataBag,
        string $salesChannelName,
        Context $context): CreatePayment
    {
        $order = $transaction->getOrder();
        $transactionId = $transaction->getTransaction()->getId();
        $orderNumber = (string) $order->getOrderNumber();
        $customer = $transaction->getCustomer();
        $salesChannelId = $order->getSalesChannelId();
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);
        $apiSettings = $this->settingsService->getApiSettings($salesChannelId);
        $profileId = $apiSettings->getProfileId();
        if (mb_strlen($profileId) === 0) {
            $profile = $this->mollieGateway->getCurrentProfile($salesChannelId);
            $profileId = $profile->getId();
        }

        $createPaymentStruct = $this->createPaymentBuilder->build($transaction);
        $createPaymentStruct->setMethod($paymentHandler->getPaymentMethod());

        $logData = [
            'salesChannel' => $salesChannelName,
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
        ];
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
            $mollieCustomerId = $mollieCustomerExtension->getForProfileId($profileId);
            if ($mollieCustomerId !== null) {
                $createPaymentStruct->setCustomerId($mollieCustomerId);
            }
        }

        $savePaymentDetails = $dataBag->get('savePaymentDetails', false);
        if (! $customer->getGuest() && $savePaymentDetails) {
            $createPaymentStruct->setSequenceType(SequenceType::FIRST);
        }

        $createPaymentStruct = $paymentHandler->applyPaymentSpecificParameters($createPaymentStruct, $dataBag, $order, $customer);

        if (! $customer->getGuest() && $createPaymentStruct->getSequenceType() !== SequenceType::ONEOFF && $createPaymentStruct->getCustomerId() === null) {
            $mollieCustomer = $this->mollieGateway->createCustomer($customer, $salesChannelId);
            $createPaymentStruct->setCustomerId($mollieCustomer->getId());

            $customer = $this->saveCustomerId($customer, $mollieCustomer, $profileId, $context);
            $this->logger->info('Mollie customer created and assigned to shopware customer', $logData);
        }

        $logData['payload'] = $createPaymentStruct->toArray();
        $this->logger->info('Payment payload created for mollie API', $logData);

        $paymentEvent = new ModifyCreatePaymentPayloadEvent($createPaymentStruct, $context);
        $this->eventDispatcher->dispatch($paymentEvent);

        return $paymentEvent->getPayment();
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

    private function updatePaymentCounter(OrderTransactionEntity $transaction, CreatePayment $createPaymentStruct): int
    {
        $countPayments = 1;
        $oldMollieTransaction = $transaction->getExtension(Mollie::EXTENSION);
        if ($oldMollieTransaction instanceof Payment) {
            $countPayments = $oldMollieTransaction->getCountPayments() + 1;
            $createPaymentStruct->setDescription($createPaymentStruct->getDescription() . '-' . $countPayments);
        }

        return $countPayments;
    }
}
