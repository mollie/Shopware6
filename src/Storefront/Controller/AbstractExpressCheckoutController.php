<?php

namespace Kiener\MolliePayments\Storefront\Controller;


use Exception;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Helper\ProfileHelper;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\ProductService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Kiener\MolliePayments\Service\ShopService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Profile;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractExpressCheckoutController extends StorefrontController
{
    /** @var MollieApiClient */
    protected $apiClient;

    /** @var PaymentHandler */
    protected $paymentHandler;

    /** @var EntityRepositoryInterface */
    protected $paymentMethodRepository;

    /** @var RouterInterface */
    protected $router;

    /** @var SalesChannelContextFactory */
    protected $salesChannelContextFactory;

    /** @var SettingsService */
    protected $settingsService;

    /** @var TokenFactoryInterfaceV2 */
    protected $tokenFactory;

    public function __construct(
        MollieApiClient $apiClient,
        PaymentHandler $paymentHandler,
        EntityRepositoryInterface $paymentMethodRepository,
        RouterInterface $router,
        SalesChannelContextFactory $salesChannelContextFactory,
        SettingsService $settingsService,
        TokenFactoryInterfaceV2 $tokenFactory
    )
    {
        $this->apiClient = $apiClient;
        $this->paymentHandler = $paymentHandler;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router = $router;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->settingsService = $settingsService;
        $this->tokenFactory = $tokenFactory;
    }


    /**
     * Returns an order that is created through the Mollie API.
     *
     * @param string                 $applePaymentToken
     * @param OrderEntity            $order
     * @param string                 $returnUrl
     * @param OrderTransactionEntity $transaction
     *
     * @param SalesChannelContext    $salesChannelContext
     *
     * @return Order|null
     * @throws RuntimeException
     */
    protected function createOrderAtMollie(
        string $molliePaymentMethod,
        OrderEntity $order,
        string $returnUrl,
        OrderTransactionEntity $transaction,
        SalesChannelContext $salesChannelContext,
        array $paymentData = []
    ): ?Order
    {
        /** @var Order $mollieOrder */
        $mollieOrder = null;

        /** @var array $orderData */
        $orderData = $this->paymentHandler->prepareOrderForMollie(
            $molliePaymentMethod,
            $transaction->getId(),
            $order,
            (string) $returnUrl,
            $salesChannelContext,
            $paymentData
        );

        // Create the order at Mollie
        if (
            is_array($orderData)
            && !empty($orderData)
        ) {
            $mollieOrder = $this->paymentHandler->createOrderAtMollie(
                $orderData,
                (string)$returnUrl,
                $order,
                $salesChannelContext
            );
        }

        return $mollieOrder;
    }

    /**
     * Returns a payment method by it's handler.
     *
     * @param string       $handlerIdentifier
     * @param Context|null $context
     *
     * @return PaymentMethodEntity|null
     */
    protected function getPaymentMethod(string $handlerIdentifier, Context $context = null): ?PaymentMethodEntity
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

            // Get payment methods
            $paymentMethods = $this->paymentMethodRepository->search($criteria, $context ?? Context::createDefaultContext());

            if (
                $paymentMethods !== null
                && $paymentMethods->count()
                && $paymentMethods->first() !== null
            ) {
                return $paymentMethods->first();
            }
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a payment method id by it's handler.
     *
     * @param string       $handlerIdentifier
     * @param Context|null $context
     *
     * @return array|mixed|string|null
     */
    protected function getPaymentMethodId(string $handlerIdentifier, Context $context = null)
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

            // Get payment methods
            $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();
            return !empty($paymentMethods) ? $paymentMethods[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Since the guest customer was logged in, the context changed in the system,
     * but this doesn't effect the context given as parameter.
     * Because of that, a new context for the following operations is created
     *
     * @param string              $newToken
     * @param SalesChannelContext $context
     *
     * @param string|null         $countryId
     * @param string              $customerId
     * @param string              $paymentMethodId
     * @param string|null         $shippingMethodId
     *
     * @return SalesChannelContext
     */
    protected function createSalesChannelContext(
        string $newToken,
        SalesChannelContext $context,
        ?string $countryId,
        ?string $customerId,
        ?string $paymentMethodId = null,
        ?string $shippingMethodId = null
    ): SalesChannelContext
    {
        /** @var array $options */
        $options = [];

        // Add country to options
        if ((string) $countryId !== '') {
            $options[SalesChannelContextService::COUNTRY_ID] = $countryId;
        }

        // Add customer to options
        if ((string) $customerId !== '') {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        // Add payment method to options
        if ((string) $paymentMethodId !== '') {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        // Add shipping method to options
        if ((string) $shippingMethodId !== '') {
            $options[SalesChannelContextService::SHIPPING_METHOD_ID] = $shippingMethodId;
        }

        $salesChannelContext = $this->salesChannelContextFactory->create(
            $newToken,
            $context->getSalesChannel()->getId(),
            $options
        );

        // todo: load matching rules
        $salesChannelContext->setRuleIds($context->getRuleIds());

        return $salesChannelContext;
    }

    /**
     * Sets the API keys for Mollie based on the current context.
     *
     * @param SalesChannelContext $context
     *
     * @param string|null         $mode
     *
     * @throws ApiException
     */
    protected function setApiKeysBySalesChannelContext(SalesChannelContext $context, ?string $mode = null): void
    {
        try {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            /** @var string $apiKey */
            $apiKey = $settings->isTestMode() === false ? $settings->getLiveApiKey() : $settings->getTestApiKey();

            if ($mode === 'live') {
                $apiKey = $settings->getLiveApiKey();
            }

            if ($mode === 'test') {
                $apiKey = $settings->getTestApiKey();
            }

            // Set the API key
            $this->apiClient->setApiKey($apiKey);
        } catch (InconsistentCriteriaIdsException $e) {
            throw new RuntimeException(sprintf('Could not set Mollie Api Key, error: %s', $e->getMessage()));
        }
    }

    protected function createReturnUrlForOrder(OrderEntity $order, ?string $errorUrl = null): string {
        $finishUrl = $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $order->getUniqueIdentifier()]);
        if(is_null($errorUrl)) {
            $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $order->getUniqueIdentifier()]);
        }

        $transactions = $order->getTransactions()->filterByState(OrderTransactionStates::STATE_OPEN);
        $transaction = $transactions->last();

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $finishUrl,
            null,
            $errorUrl
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);

        return $this->assembleReturnUrl($token);
    }

    protected function assembleReturnUrl(string $token): string
    {
        $parameter = [
            '_sw_payment_token' => $token,
            '_express_checkout' => true,
        ];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
