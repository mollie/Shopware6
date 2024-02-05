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
use Ramsey\Uuid\Uuid;
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

            return (! empty($methodID));
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
     * @return Session
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startSession(Cart $cart, SalesChannelContext $context): Session
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
            'redirectUrl' => $this->urlBuilder->buildPaypalExpressRedirectUrl(),
            'cancelUrl' => $this->urlBuilder->buildPaypalExpressCancelUrl(),
        ];

        return $mollie->sessions->create($params);
    }

    public function loadSession(string $sessionId, SalesChannelContext $context)
    {
        $mollie = $this->mollieApiFactory->getClient($context->getSalesChannelId());
        return $mollie->sessions->get($sessionId);

    }



    /**
     * @param AddressStruct $shippingAddress
     * @param SalesChannelContext $context
     * @param AddressStruct|null $billingAddress
     * @return SalesChannelContext
     * @throws \Exception
     */
    public function prepareCustomer(AddressStruct $shippingAddress, SalesChannelContext $context, ?AddressStruct $billingAddress = null): SalesChannelContext
    {


        $paypalExpressId = $this->getActivePaypalExpressID($context);

        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        if (! $this->customerService->isCustomerLoggedIn($context)) {

            # find existing customer by email
            $customer = $this->customerService->findCustomerByEmail($shippingAddress->getEmail(), $context->getContext());

            $updateShippingAddress = true;
            if ($customer === null) {

                $updateShippingAddress = false;
                $customer = $this->customerService->createGuestAccount(
                    $shippingAddress,
                    $paypalExpressId,
                    $context,
                    $billingAddress
                );
            }


            if (! $customer instanceof CustomerEntity) {
                throw new \Exception('Error when creating customer!');
            }

            # if we have an existing customer, we want reuse his shipping adress instead of creating new one
            if ($updateShippingAddress) {
                $this->customerService->upsertAddresses($customer, $shippingAddress, $context->getContext(),$billingAddress);
            }

            # now start the login of our customer.
            # Our SalesChannelContext will be correctly updated after our
            # forward to the finish-payment page.
            $this->customerService->loginCustomer($customer, $context);
        }

        # also (always) update our payment method to use Apple Pay for our cart
        return $this->cartService->updatePaymentMethod($context, $paypalExpressId);
    }

    public function createMollieAddressId(string $firstName, string $lastName, string $email, string $street, string $addressAdditional, string $zipCode, string $city, string $country): string
    {
        return md5(implode('-', [$firstName, $lastName, $email, $street, $addressAdditional, $zipCode, $city, $country]));
    }

    public function setBillingAddress(CustomerEntity $customer, string $firstname, string $lastname, string $email, string $street, string $zipcode, string $city, string $countryCode, SalesChannelContext $context)
    {

    }


}
