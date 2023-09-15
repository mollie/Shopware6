<?php

namespace Kiener\MolliePayments\Facade\Controller;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Event\PaymentPageRedirectEvent;
use Kiener\MolliePayments\Exception\CouldNotFetchTransactionException;
use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Exception\MissingOrderInTransactionException;
use Kiener\MolliePayments\Facade\MollieOrderPaymentFlow;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieServiceOrder;
use Kiener\MolliePayments\Service\Router\RoutingDetector;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Resources\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class PaymentReturnFacade
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $eventDispatcher;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var MollieServiceOrder
     */
    private $orders;

    /**
     * @var MollieOrderPaymentFlow
     */
    private $molliePaymentFlow;

    /**
     * @var RoutingDetector
     */
    private $routingDetector;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param RouterInterface $router
     * @param FlowBuilderFactoryInterface $flowFactory
     * @param SettingsService $settingsService
     * @param TransactionService $transactionService
     * @param MollieServiceOrder $orders
     * @param MollieOrderPaymentFlow $molliePaymentFlow
     * @param RoutingDetector $routingDetector
     * @param LoggerInterface $logger
     */
    public function __construct(RouterInterface $router, FlowBuilderFactoryInterface $flowFactory, SettingsService $settingsService, TransactionService $transactionService, MollieServiceOrder $orders, MollieOrderPaymentFlow $molliePaymentFlow, RoutingDetector $routingDetector, LoggerInterface $logger)
    {
        $this->router = $router;
        $this->eventDispatcher = $flowFactory->createDispatcher();
        $this->settingsService = $settingsService;
        $this->transactionService = $transactionService;
        $this->orders = $orders;
        $this->molliePaymentFlow = $molliePaymentFlow;
        $this->routingDetector = $routingDetector;
        $this->logger = $logger;
    }


    /**
     * @param string $transactionId
     * @param Context $context
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return null|Response
     */
    public function returnAction(string $transactionId, Context $context): ?Response
    {
        # Get the transaction from the order transaction repository. With the
        # transaction we can fetch the order from the database.
        $transaction = $this->transactionService->getTransactionById($transactionId, null, $context);

        if (!$transaction instanceof OrderTransactionEntity) {
            $this->logger->critical('Transaction with id ' . $transactionId . ' could not be read from database');
            throw new CouldNotFetchTransactionException($transactionId);
        }

        # --------------------------------------------------------------------------------------------------------------------

        $swOrder = $transaction->getOrder();

        // TODO: Refactor to use Service/OrderService::getOrder if $order does not exist.
        if (!$swOrder instanceof OrderEntity) {
            $this->logger->critical(sprintf('Could not fetch order from transaction with id %s', $transactionId));
            throw new MissingOrderInTransactionException($transactionId);
        }


        $orderAttributes = new OrderAttributes($swOrder);

        $this->logger->debug('Customer is returning to Shopware for order: ' . $swOrder->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId());


        $mollieOrderId = $orderAttributes->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $this->logger->critical(sprintf('Could not fetch mollie order id from order with number %s', $swOrder->getOrderNumber()));
            throw new MissingMollieOrderIdException((string)$swOrder->getOrderNumber());
        }

        # --------------------------------------------------------------------------------------------------------------------

        # now grab our sales channel of the order
        # and also the correct plugin configuration for this sales channel
        $salesChannelId = $swOrder->getSalesChannelId();
        $settings = $this->settingsService->getSettings($salesChannelId);


        try {
            $mollieOrder = $this->orders->getMollieOrder(
                $mollieOrderId,
                $salesChannelId,
                [
                    'embed' => 'payments'
                ]
            );
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Could not fetch order at mollie with id %s', $mollieOrderId));
            throw $e;
        }


        # --------------------------------------------------------------------------------------------------------------------
        # SHOPWARE DEFAULT WAY
        # if we have enabled the Shopware default way to handle payments,
        # then just redirect to the transaction return URL that shopware did create for us.
        # this is usually the finalizeURL.
        # also, if we have a Admin-API route (from our headless approach), then we also go with the default way
        $useShopwareDefault = ($this->routingDetector->isAdminApiRoute() || $settings->isShopwareStandardFailureMode());

        if ($useShopwareDefault) {
            return $this->navigateShopwareStandardRoute(
                (string)$orderAttributes->getTransactionReturnUrl(),
                $swOrder,
                $mollieOrder,
                $salesChannelId,
                $context
            );
        }

        # --------------------------------------------------------------------------------------------------------------------
        # MOLLIE CUSTOM MODE
        # this is only done in the Storefront, and only if we have activated this feature.
        # depending on the success of the payment, we either redirect
        # to the standard Shopware success route, or to our custom failure route and page in the Storefront.
        $success = $this->molliePaymentFlow->process($transaction, $swOrder, $mollieOrder, $salesChannelId, $context);

        if ($success) {
            return $this->navigateShopwareStandardRoute(
                (string)$orderAttributes->getTransactionReturnUrl(),
                $swOrder,
                $mollieOrder,
                $salesChannelId,
                $context
            );
        }

        return $this->navigateMollieFailurePage($transactionId);
    }

    /**
     * @param string $redirectUrl
     * @param OrderEntity $order
     * @param Order $mollieOrder
     * @param string $salesChannelId
     * @param Context $context
     * @return Response
     */
    private function navigateShopwareStandardRoute(string $redirectUrl, OrderEntity $order, Order $mollieOrder, string $salesChannelId, Context $context): Response
    {
        $paymentPageRedirectEvent = new PaymentPageRedirectEvent(
            $context,
            $order,
            $mollieOrder,
            $salesChannelId,
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageRedirectEvent);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param string $transactionId
     * @return Response
     */
    private function navigateMollieFailurePage(string $transactionId): Response
    {
        $params = [
            'transactionId' => $transactionId
        ];

        $redirectUrl = $this->router->generate(
            'frontend.mollie.payment-failed',
            $params,
            $this->router::ABSOLUTE_URL
        );

        return new RedirectResponse($redirectUrl);
    }
}
