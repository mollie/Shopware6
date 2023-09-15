<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\AddProductResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\CreateSessionResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetIDResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\GetShippingMethodsResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\IsApplePayEnabledResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\PaymentResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\RestoreCartResponse;
use Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Response\SetShippingMethodResponse;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class ApplePayDirectControllerBase
{
    /**
     * @var ApplePayDirect
     */
    private $applePay;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ApplePayDirect $applePay
     * @param LoggerInterface $logger
     */
    public function __construct(ApplePayDirect $applePay, LoggerInterface $logger)
    {
        $this->applePay = $applePay;
        $this->logger = $logger;
    }


    /**
     * @Route("/store-api/mollie/applepay/enabled", name="store-api.mollie.apple-pay.enabled", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
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
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function getId(SalesChannelContext $context): StoreApiResponse
    {
        $success = true;
        $id = '';

        try {
            $id = $this->applePay->getActiveApplePayID($context);
        } catch (Throwable $ex) {
            $success = false;

            $this->logger->warning('Error when fetching Apple Pay ID in Store API. ' . $ex->getMessage());
        }

        return new GetIDResponse($success, $id);
    }

    /**
     * @Route("/store-api/mollie/applepay/add-product", name="store-api.mollie.apple-pay.add-product", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @throws \Throwable
     * @return StoreApiResponse
     */
    public function addProduct(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $productId = $data->getAlnum('productId');
        $quantity = (int)$data->getAlnum('quantity', '0');

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
     * @throws \Throwable
     * @return StoreApiResponse
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
     * @throws \Throwable
     * @return StoreApiResponse
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
     * @throws \Throwable
     * @return StoreApiResponse
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
     * @throws \Throwable
     * @return StoreApiResponse
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
     * @Route("/store-api/mollie/applepay/pay",  name="store-api.mollie.apple-pay.pay", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function pay(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $email = (string)$data->get('email', '');
        $firstname = (string)$data->get('firstname', '');
        $lastname = (string)$data->get('lastname', '');
        $street = (string)$data->get('street', '');
        $city = (string)$data->get('city', '');
        $zipcode = (string)$data->get('postalCode', '');
        $countryCode = (string)$data->get('countryCode', '');

        $paymentToken = (string)$data->get('paymentToken', '');
        $finishUrl = (string)$data->get('finishUrl', '');
        $errorUrl = (string)$data->get('errorUrl', '');


        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }

        # make sure to create a customer if necessary
        # then update to our apple pay payment method
        # and return the new context
        $newContext = $this->applePay->prepareCustomer(
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

        # we only start our TRY/CATCH here!
        # we always need to throw exceptions on an API level
        # but if something BELOW breaks, we want to navigate to the error page.
        # customers are ready, data is ready, but the handling has a problem.

        try {
            # create our new Shopware Order
            $order = $this->applePay->createOrder($newContext);

            # now create the Mollie payment for it
            # there should not be a checkout URL required for apple pay,
            # so we just create the payment and redirect.
            $this->applePay->createPayment(
                $order,
                $finishUrl,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $paymentToken,
                $newContext
            );

            return new PaymentResponse(true, $finishUrl, '');
        } catch (Throwable $ex) {
            return new PaymentResponse(false, $errorUrl, $ex->getMessage());
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
