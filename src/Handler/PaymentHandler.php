<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Handler;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Exceptions\ApiException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{
    public const PAYMENT_SEQUENCE_TYPE_FIRST = 'first';
    public const PAYMENT_SEQUENCE_TYPE_RECURRING = 'recurring';

    protected const FIELD_ORDER_NUMBER = 'orderNumber';
    protected const FIELD_BILLING_ADDRESS = 'billingAddress';
    protected const FIELD_BILLING_EMAIL = 'billingEmail';

    /** @var string */
    protected $paymentMethod;

    /** @var array<mixed> */
    protected $paymentMethodData = [];

    /** @var LoggerInterface */
    protected $logger;

    /** @var MolliePaymentDoPay */
    private $payFacade;

    /** @var TransactionTransitionServiceInterface */
    private $transactionTransitionService;

    /** @var MolliePaymentFinalize */
    private $finalizeFacade;

    /**
     * @var ContainerInterface
     */
    private $container;


    /**
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     */
    public function __construct(LoggerInterface $logger, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->container = $container;
    }


    /**
     * @param array<mixed> $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        return $orderData;
    }

    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * A redirect to the url will be performed
     *
     * Throw a
     *
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     *
     * @throws ApiException
     * @return RedirectResponse @see AsyncPaymentProcessException exception if an error ocurres while processing the
     *                          payment
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $this->loadServices();

        $this->logger->info(
            'Starting Checkout for order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $this->paymentMethod,
            [
                'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                'cart' => [
                    'amount' => $transaction->getOrder()->getAmountTotal(),
                ],
            ]
        );


        try {
            $paymentData = $this->payFacade->startMolliePayment(
                $this->paymentMethod,
                $transaction,
                $salesChannelContext,
                $this
            );

            $paymentUrl = $paymentData->getCheckoutURL();
        } catch (Throwable $exception) {
            $this->logger->error(
                'Error when starting Mollie payment: ' . $exception->getMessage(),
                [
                    'function' => 'order-prepare',
                    'exception' => $exception
                ]
            );

            throw new PaymentUrlException($transaction->getOrderTransaction()->getId(), $exception->getMessage());
        }

        try {
            # before we send the customer to the Mollie payment page
            # we will process the order transaction, which means we set it to be IN PROGRESS.
            # this is just how it works at the moment, I did only add the comment for it here :)
            $this->transactionTransitionService->processTransaction($transaction->getOrderTransaction(), $salesChannelContext->getContext());
        } catch (\Exception $exception) {
            $this->logger->warning(
                sprintf('Could not set payment to in progress. Got error %s', $exception->getMessage())
            );
        }

        /**
         * Redirect the customer to the payment URL. Afterwards the
         * customer is redirected back to Shopware's finish page, which
         * leads to the @finalize function.
         */
        return new RedirectResponse($paymentUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $this->loadServices();

        $orderAttributes = new OrderAttributes($transaction->getOrder());
        $molliedID = $orderAttributes->getMollieOrderId();

        $this->logger->info(
            'Finalizing Mollie payment for order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $this->paymentMethod . ' and Mollie ID' . $molliedID,
            [
                'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
            ]
        );

        try {
            $this->finalizeFacade->finalize($transaction, $salesChannelContext);
        } catch (AsyncPaymentFinalizeException|CustomerCanceledAsyncPaymentException $ex) {
            $this->logger->error(
                'Error when finalizing order ' . $transaction->getOrder()->getOrderNumber() . ', Mollie ID: ' . $molliedID . ', ' . $ex->getMessage()
            );

            # these are already correct exceptions
            # that cancel the Shopware order in a coordinated way by Shopware
            throw $ex;
        } catch (Throwable $ex) {
            # this processes all unhandled exceptions.
            # we need to log whatever happens in here, and then also
            # throw an exception that breaks the order in a coordinated way.
            # Only the 2 exceptions above, lead to a correct failure-behaviour in Shopware.
            # All other exceptions would lead to a 500 exception in the storefront.
            $this->logger->error(
                'Unknown Error when finalizing order ' . $transaction->getOrder()->getOrderNumber() . ', Mollie ID: ' . $molliedID . ', ' . $ex->getMessage()
            );

            throw new AsyncPaymentFinalizeException(
                $transaction->getOrderTransaction()->getId(),
                'An unknown error happened when finalizing the order. Please see the Shopware logs for more. It can be that the payment in Mollie was succesful and the Shopware order is now cancelled or failed!'
            );
        }
    }

    /**
     * Attention!!!! With Shopware 6.4.9.0 there was suddenly a circular reference with these services.
     * but ONLY if the tag "shopware.payment.method.async" was used in the service xml.
     * So it seems as if that tag leads to a certain time during DI where things might just be under
     * construction and not already existing, so that a circular reference is occurring?
     * I have no clue, but lazy loading will fix this.
     *
     * @throws \Exception
     * @return void
     */
    private function loadServices(): void
    {
        /** @var \Symfony\Component\DependencyInjection\Container $container */
        $container = $this->container;

        /** @var MolliePaymentDoPay $payFacade */
        $payFacade = $container->get('Kiener\MolliePayments\Facade\MolliePaymentDoPay');
        $this->payFacade = $payFacade;

        /** @var MolliePaymentFinalize $finalizeFacade */
        $finalizeFacade = $container->get('Kiener\MolliePayments\Facade\MolliePaymentFinalize');
        $this->finalizeFacade = $finalizeFacade;

        /** @var TransactionTransitionService $transactionTransitionService */
        $transactionTransitionService = $container->get('Kiener\MolliePayments\Service\Transition\TransactionTransitionService');
        $this->transactionTransitionService = $transactionTransitionService;
    }
}
