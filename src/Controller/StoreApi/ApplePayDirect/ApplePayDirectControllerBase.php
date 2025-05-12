<?php
declare(strict_types=1);

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
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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

    public function __construct(ApplePayDirect $applePay, LoggerInterface $logger)
    {
        $this->applePay = $applePay;
        $this->logger = $logger;
    }

    /**
     * @throws \Exception
     */
    public function isEnabled(SalesChannelContext $context): StoreApiResponse
    {
        $isEnabled = $this->applePay->isApplePayDirectEnabled($context);

        return new IsApplePayEnabledResponse($isEnabled);
    }

    /**
     * @throws \Exception
     */
    public function getId(SalesChannelContext $context): StoreApiResponse
    {
        $success = true;
        $id = '';

        try {
            $id = $this->applePay->getActiveApplePayID($context);
        } catch (\Throwable $ex) {
            $success = false;

            $this->logger->warning('Error when fetching Apple Pay ID in Store API. ' . $ex->getMessage());
        }

        return new GetIDResponse($success, $id);
    }

    /**
     * @throws \Throwable
     */
    public function addProduct(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $productId = $data->getAlnum('productId');
        $quantity = (int) $data->getAlnum('quantity', '0');

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
     * @throws \Throwable
     */
    public function createPaymentSession(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $validationURL = $data->get('validationUrl');
        $domain = (string) $data->get('domain');

        if (empty($validationURL)) {
            throw new \Exception('Please provide a validation url!');
        }

        $session = $this->applePay->createPaymentSession($validationURL, $domain, $context);

        return new CreateSessionResponse($session);
    }

    /**
     * @throws \Throwable
     */
    public function getCart(SalesChannelContext $context): StoreApiResponse
    {
        $formattedCart = $this->applePay->getCartFormatted($context);

        return new GetCartResponse($formattedCart);
    }

    /**
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
     * @throws \Exception
     */
    public function pay(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        $email = (string) $data->get('email', '');
        $firstname = (string) $data->get('firstname', '');
        $lastname = (string) $data->get('lastname', '');
        $street = (string) $data->get('street', '');
        $city = (string) $data->get('city', '');
        $zipcode = (string) $data->get('postalCode', '');
        $countryCode = (string) $data->get('countryCode', '');
        $phone = (string) $data->get('phone', '');

        $acceptedDataProtection = (int) $data->get('acceptedDataProtection', '0');

        $paymentToken = (string) $data->get('paymentToken', '');
        $finishUrl = (string) $data->get('finishUrl', '');
        $errorUrl = (string) $data->get('errorUrl', '');

        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }
        try {
            // make sure to create a customer if necessary
            // then update to our apple pay payment method
            // and return the new context
            $newContext = $this->applePay->prepareCustomer(
                $firstname,
                $lastname,
                $email,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $phone,
                $paymentToken,
                $acceptedDataProtection,
                $context
            );

            // we only start our TRY/CATCH here!
            // we always need to throw exceptions on an API level
            // but if something BELOW breaks, we want to navigate to the error page.
            // customers are ready, data is ready, but the handling has a problem.

            // create our new Shopware Order
            $order = $this->applePay->createOrder($newContext);

            // now create the Mollie payment for it
            // there should not be a checkout URL required for apple pay,
            // so we just create the payment and redirect.
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

            return new PaymentResponse(true, $finishUrl, '', $order->getId());
        } catch (\Throwable $ex) {
            return new PaymentResponse(false, $errorUrl, $ex->getMessage(), '');
        }
    }

    public function restoreCart(SalesChannelContext $context): StoreApiResponse
    {
        $this->applePay->restoreCart($context);

        return new RestoreCartResponse(true);
    }
}
