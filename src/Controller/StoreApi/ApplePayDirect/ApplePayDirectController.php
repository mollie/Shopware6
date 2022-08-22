<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\AddProductResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\FinishPaymentResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetIDResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetShippingMethodsResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\IsApplePayEnabledResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\RestoreCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\SetShippingMethodResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\StartPaymentResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\CreateSessionResponse;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;


/**
 * @RouteScope(scopes={"store-api"})
 */
class ApplePayDirectController
{

    /**
     * @var ApplePayDirect
     */
    private $applePay;


    /**
     * @param ApplePayDirect $applePay
     */
    public function __construct(ApplePayDirect $applePay)
    {
        $this->applePay = $applePay;
    }


    /**
     * @Route("/store-api/mollie/applepay/enabled", name="store-api.mollie.apple-pay.enabled", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Exception
     */
    public function isEnabled(SalesChannelContext $context): StoreApiResponse
    {
        $isEnabled = $this->applePay->isApplePayDirectEnabled($context);

        return new IsApplePayEnabledResponse($isEnabled);
    }

    /**
     * @Route("/store-api/mollie/applepay/id", name="store-api.mollie.apple-pay.id", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Exception
     */
    public function getId(SalesChannelContext $context): StoreApiResponse
    {
        $id = $this->applePay->getActiveApplePayID($context);

        return new GetIDResponse($id);
    }

    /**
     * @Route("/store-api/mollie/applepay/add-product", name="store-api.mollie.apple-pay.add-product", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function addProduct(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $productId = $data->getAlnum('productId');
        $quantity = (int)$data->getAlnum('quantity', 0);

        if (empty($productId)) {
            throw new \Exception('Please provide a product ID!');
        }

        if ($quantity <= 0) {
            throw new \Exception('Please provide a valid quantity > 0!');
        }

        $swCart = $this->applePay->addProduct($productId, $quantity, $context);

        return new AddProductResponse($swCart);
    }

    /**
     * @Route("/store-api/mollie/applepay/validate", name="store-api.mollie.apple-pay.validate", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function createPaymentSession(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $validationURL = $data->getAlnum('validationUrl');

        if (empty($validationURL)) {
            throw new \Exception('Please provide a validation url!');
        }

        $session = $this->applePay->createPaymentSession($validationURL, $context);

        return new CreateSessionResponse($session);
    }

    /**
     * @Route("/store-api/mollie/applepay/cart", name="store-api.mollie.apple-pay.cart", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function getCart(SalesChannelContext $context): StoreApiResponse
    {
        $formattedCart = $this->applePay->getCartFormatted($context);

        return new GetCartResponse($formattedCart);
    }


    /**
     * @Route("/store-api/mollie/applepay/shipping-methods", name="store-api.mollie.apple-pay.shipping-methods", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function getShippingMethods(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $countryCode = $data->getAlnum('countryCode');

        if (empty($countryCode)) {
            throw new \Exception('No Country Code provided!');
        }

        $methods = $this->applePay->getShippingMethods($countryCode, $context);

        return new GetShippingMethodsResponse($methods);
    }

    /**
     * @Route("/store-api/mollie/applepay/shipping-method", name="store-api.mollie.apple-pay.set-shipping-methods", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function setShippingMethod(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $shippingMethodID = $data->getAlnum('identifier');

        if (empty($shippingMethodID)) {
            throw new \Exception('Please provide a Shipping Method identifier!');
        }

        $this->applePay->setShippingMethod($shippingMethodID, $context);

        return new SetShippingMethodResponse(true);
    }

    /**
     * @Route("/store-api/mollie/applepay/start-payment",  name="store-api.mollie.apple-pay.start-payment", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Exception
     */
    public function startPayment(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $email = $data->getAlnum('email');
        $firstname = $data->getAlnum('firstname');
        $lastname = $data->getAlnum('lastname');
        $street = $data->getAlnum('street');
        $city = $data->getAlnum('city');
        $zipcode = $data->getAlnum('postalCode');
        $countryCode = $data->getAlnum('countryCode');

        $paymentToken = $data->getAlnum('paymentToken');

        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }

        $this->applePay->prepareCustomer(
            $firstname,
            $lastname,
            $email,
            $street,
            $zipcode,
            $city,
            $countryCode,
            $paymentToken,
            $context
        );

        return new StartPaymentResponse(true);
    }

    /**
     * @Route("/store-api/mollie/applepay/finish-payment",  name="store-api.mollie.apple-pay.finish-payment", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     */
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $firstname = $data->getAlnum('firstname');
        $lastname = $data->getAlnum('lastname');
        $street = $data->getAlnum('street');
        $city = $data->getAlnum('city');
        $zipcode = $data->getAlnum('postalCode');
        $countryCode = $data->getAlnum('countryCode');

        $paymentToken = $data->getAlnum('paymentToken');


        # ----------------------------------------------------------------------------
        # STEP 1: Create Order
        try {

            if (empty($paymentToken)) {
                throw new \Exception('PaymentToken not found!');
            }

            $order = $this->applePay->createOrder($context);

        } catch (Throwable $ex) {

            return new FinishPaymentResponse(false, '', $ex->getMessage());
        }


        # ----------------------------------------------------------------------------
        # STEP 2: Start Payment (CHECKPOINT: we have a valid shopware order now)
        try {

            $returnUrl = $this->applePay->createPayment(
                $order,
                '',
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $paymentToken,
                $context
            );

            return new FinishPaymentResponse(true, '', '');

        } catch (Throwable $ex) {

            return new FinishPaymentResponse(false, '', $ex->getMessage());
        }
    }

    /**
     * @Route("/store-api/mollie/applepay/restore-cart", name="store-api.mollie.apple-pay.restore-cart", methods={"POST"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     */
    public function restoreCart(SalesChannelContext $context): StoreApiResponse
    {
        $this->applePay->restoreCart($context);

        return new RestoreCartResponse(true);
    }

}
