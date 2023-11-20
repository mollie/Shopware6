<?php

namespace Kiener\MolliePayments\Components\PaypalExpress;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalExpress
{

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

            return (!empty($methodID));
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * @param SalesChannelContext $context
     * @return string
     * @throws \Exception
     */
    public function getActivePaypalExpressID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActivePaypalExpressID($context->getContext());
    }

    public function getActivePaypalID(SalesChannelContext $context)
    {
        return $this->repoPaymentMethods->getActivePaypalID($context->getContext());

    }


    /**
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startSession(Cart $cart, SalesChannelContext $context): string
    {
        $mollie = $this->mollieApiFactory->getClient($context->getSalesChannelId());

        $params = [
            'method' => 'paypal',
            'methodDetails' => [
                'checkoutFlow' => 'express',
            ],
            'amount' => $this->priceBuilder->build(
                $cart->getPrice()->getTotalPrice(),
                $context->getCurrency()->getIsoCode()
            ),
            'description' => 'test',
            #  'redirectUrl' => $this->urlBuilder->buildPaypalExpressRedirectUrl(),
            #   'cancelUrl' => $this->urlBuilder->buildPaypalExpressCancelUrl(),
        ];

        $session = $mollie->sessions->create($params);

        $redirectUrl = $session->getRedirectUrl();

        if (empty($redirectUrl)) {
            return '';
            #          throw new \Exception('Paypal Express RedirectURL is empty! Cannot proceed');
        }

        return $redirectUrl;
    }


    /**
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryCode
     * @param string $paymentToken
     * @param SalesChannelContext $context
     * @return SalesChannelContext
     * @throws \Exception
     */
    public function prepareCustomer(string $firstname, string $lastname, string $email, string $street, string $zipcode, string $city, string $countryCode, string $paymentToken, SalesChannelContext $context): SalesChannelContext
    {
        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }


        $paypalExpressId = $this->getActivePaypalExpressID($context);

        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        if (!$this->customerService->isCustomerLoggedIn($context)) {
            $customer = $this->customerService->createGuestAccount(
                $firstname,
                $lastname,
                $email,
                '',
                $street,
                $zipcode,
                $city,
                $countryCode,
                $paypalExpressId,
                $context
            );

            if (!$customer instanceof CustomerEntity) {
                throw new \Exception('Error when creating customer!');
            }

            # now start the login of our customer.
            # Our SalesChannelContext will be correctly updated after our
            # forward to the finish-payment page.
            $this->customerService->loginCustomer($customer, $context);
        }

        # also (always) update our payment method to use Apple Pay for our cart
        return $this->cartService->updatePaymentMethod($context, $paypalExpressId);
    }


}
