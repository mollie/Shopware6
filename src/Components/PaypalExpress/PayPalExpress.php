<?php

namespace Kiener\MolliePayments\Components\PaypalExpress;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Mollie\Api\Resources\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalExpress
{

    /**
     * define how often we ask the session until we get the shipping address
     */
    private const SESSION_MAX_RETRY = 5;

    /**
     * define how long we will wait for the session response
     */
    private const SESSION_BASE_TIMEOUT = 2000;
    /**
     * @var PaymentMethodRepository
     */
    private $repoPaymentMethods;

    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceBuilder;

    /**
     * @var RoutingBuilder
     */
    private $urlBuilder;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var CartServiceInterface
     */
    private $cartService;

    /**
     * @param PaymentMethodRepository $repoPaymentMethods
     * @param MollieApiFactory $mollieApiFactory
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param RoutingBuilder $urlBuilder
     * @param CustomerService $customerService
     * @param CartServiceInterface $cartService
     */
    public function __construct(PaymentMethodRepository $repoPaymentMethods, MollieApiFactory $mollieApiFactory, MollieOrderPriceBuilder $priceBuilder, RoutingBuilder $urlBuilder, CustomerService $customerService, CartServiceInterface $cartService)
    {
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->mollieApiFactory = $mollieApiFactory;
        $this->priceBuilder = $priceBuilder;
        $this->urlBuilder = $urlBuilder;
        $this->customerService = $customerService;
        $this->cartService = $cartService;
    }


    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function isPaypalExpressEnabled(SalesChannelContext $context): bool
    {
        try {
            $methodID = $this->getActivePaypalExpressID($context);

            return (! empty($methodID));
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return string
     */
    public function getActivePaypalExpressID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActivePaypalExpressID($context->getContext());
    }


    /**
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Session
     */
    public function startSession(Cart $cart, SalesChannelContext $context): Session
    {
        $mollie = $this->mollieApiFactory->getLiveClient($context->getSalesChannelId());

        $params = [
            'method' => 'paypal',
            'methodDetails' => [
                'checkoutFlow' => 'express',
            ],
            'amount' => $this->priceBuilder->build(
                $cart->getPrice()->getTotalPrice(),
                $context->getCurrency()->getIsoCode()
            ),
            'redirectUrl' => $this->urlBuilder->buildPaypalExpressRedirectUrl(),
            'cancelUrl' => $this->urlBuilder->buildPaypalExpressCancelUrl(),
        ];

        return $mollie->sessions->create($params);
    }

    public function loadSession(string $sessionId, SalesChannelContext $context): Session
    {
        $mollie = $this->mollieApiFactory->getLiveClient($context->getSalesChannelId());
        /**
         * if we load the session from mollie api, we dont get the shipping address at first time. usually it takes several seconds until the data from paypal is transfered to mollie
         * so we try to load the session at least 5 times with increased waiting time.
         */
        for ($i = 0; $i < self::SESSION_MAX_RETRY; $i++) {
            $sleepTimer = self::SESSION_BASE_TIMEOUT * ($i+1);
            usleep($sleepTimer);
            $session = $mollie->sessions->get($sessionId);
            if ($session->methodDetails !== null && $session->methodDetails->shippingAddress !== null) {
                break;
            }
        }

        return $session;
    }



    /**
     * @param AddressStruct $shippingAddress
     * @param SalesChannelContext $context
     * @param null|AddressStruct $billingAddress
     * @throws \Exception
     * @return SalesChannelContext
     */
    public function prepareCustomer(AddressStruct $shippingAddress, SalesChannelContext $context, ?int $acceptedDataProtection, ?AddressStruct $billingAddress = null): SalesChannelContext
    {
        $updateShippingAddress = true;
        $paypalExpressId = $this->getActivePaypalExpressID($context);

        $customer = $context->getCustomer();

        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        # check here for instance because of phpstan
        if ($customer === null) {

            # find existing customer by email
            $customer = $this->customerService->findCustomerByEmail($shippingAddress->getEmail(), $context);


            if ($customer === null) {
                $updateShippingAddress = false;
                $customer = $this->customerService->createGuestAccount(
                    $shippingAddress,
                    $paypalExpressId,
                    $context,
                    $acceptedDataProtection,
                    $billingAddress
                );
            }


            if (! $customer instanceof CustomerEntity) {
                throw new \Exception('Error when creating customer!');
            }

            # now start the login of our customer.
            # Our SalesChannelContext will be correctly updated after our
            # forward to the finish-payment page.
            $this->customerService->loginCustomer($customer, $context);
        }

        # if we have an existing customer, we want reuse his shipping address instead of creating new one
        if ($updateShippingAddress) {
            $this->customerService->reuseOrCreateAddresses($customer, $shippingAddress, $context->getContext(), $billingAddress);
        }


        # also (always) update our payment method to use Apple Pay for our cart
        return $this->cartService->updatePaymentMethod($context, $paypalExpressId);
    }
}
