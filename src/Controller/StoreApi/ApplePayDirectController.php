<?php

namespace Kiener\MolliePayments\Controller\StoreApi;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\AddProductRoute;
use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\CreateSessionRoute;
use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\IsEnabledRoute;
use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\PaymentIdRoute;
use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\ShippingMethodRoute;
use Kiener\MolliePayments\Controller\StoreApi\Response\ApplePayDirectAddProductResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\ApplePayDirectEnabledResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\ApplePayDirectIdResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\ApplePaySessionResponse;
use Kiener\MolliePayments\Controller\StoreApi\Response\ShippingResponse;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @RouteScope(scopes={"store-api"})
 */
class ApplePayDirectController extends StorefrontController
{


    /**
     * @var IsEnabledRoute
     */
    private $routeApplePayDirectEnabled;

    /**
     * @var PaymentIdRoute
     */
    private $routeApplePayDirectId;

    /**
     * @var AddProductRoute
     */
    private $routeApplePayAddProduct;

    /**
     * @var CreateSessionRoute
     */
    private $routeApplePayCreateSession;

    /**
     * @var ShippingMethodRoute
     */
    private $routeShippingMethod;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param IsEnabledRoute $routeApplePayDirectEnabled
     * @param PaymentIdRoute $routeApplePayDirectId
     * @param AddProductRoute $routeApplePayAddProduct
     * @param CreateSessionRoute $routeApplePayCreateSession
     * @param ShippingMethodRoute $routeShippingMethod
     * @param LoggerInterface $logger
     */
    public function __construct(IsEnabledRoute $routeApplePayDirectEnabled, PaymentIdRoute $routeApplePayDirectId, AddProductRoute $routeApplePayAddProduct, CreateSessionRoute $routeApplePayCreateSession, ShippingMethodRoute $routeShippingMethod, LoggerInterface $logger)
    {
        $this->routeApplePayDirectEnabled = $routeApplePayDirectEnabled;
        $this->routeApplePayDirectId = $routeApplePayDirectId;
        $this->routeApplePayAddProduct = $routeApplePayAddProduct;
        $this->routeApplePayCreateSession = $routeApplePayCreateSession;
        $this->routeShippingMethod = $routeShippingMethod;

        $this->logger = $logger;
    }


    /**
     * @Route("/store-api/mollie/apple-pay/enabled", name="store-api.mollie.apple-pay.enabled", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function isEnabled(SalesChannelContext $context): StoreApiResponse
    {
        try {

            $isEnabled = $this->routeApplePayDirectEnabled->isApplePayDirectEnabled($context);

            return new ApplePayDirectEnabledResponse($isEnabled);

        } catch (\Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @Route("/store-api/mollie/apple-pay/id", name="store-api.mollie.apple-pay.id", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     */
    public function getPaymentID(SalesChannelContext $context): StoreApiResponse
    {
        try {

            $id = $this->routeApplePayDirectId->getApplePayID($context);

            return new ApplePayDirectIdResponse($id);

        } catch (\Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @Route("/store-api/mollie/apple-pay/add-product", name="store-api.mollie.apple-pay.add-product", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function addProduct(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {

            $productId = $data->getAlnum('productId', '');
            $quantity = (int)$data->getAlnum('quantity', 0);

            if (empty($productId)) {
                throw new \Exception('Please provide a product ID!');
            }

            if ($quantity <= 0) {
                throw new \Exception('Please provide a valid quantity > 0!');
            }

            $cart = $this->routeApplePayAddProduct->addProduct($productId, $quantity, $context);

            return new ApplePayDirectAddProductResponse($cart);

        } catch (\Throwable $ex) {
            throw $ex;
        }
    }

    /**
     * @Route("/store-api/mollie/apple-pay/validate", name="store-api.mollie.apple-pay.validate", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function createPaymentSession(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {

            $validationURL = $data->getAlnum('validationUrl', '');

            if (empty($validationURL)) {
                throw new \Exception('Please provide a validation url!');
            }

            $data = $this->routeApplePayCreateSession->createPaymentSession($validationURL, $context);

            return new ApplePaySessionResponse($data);

        } catch (\Throwable $ex) {
            # $this->logger->error('Apple Pay Direct error when creating payment session: ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @Route("/store-api/mollie/apple-pay/shipping-methods", name="store-api.mollie.apple-pay.shipping-methods", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param SalesChannelContext $context
     * @return StoreApiResponse
     * @throws \Throwable
     */
    public function getShippingMethods(RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {

            $countryCode = $data->getAlnum('countryCode', '');

            if (empty($countryCode)) {
                throw new \Exception('No Country Code provided!');
            }

            $data = $this->routeShippingMethod->getShippingMethods($countryCode, $context);

            return new ShippingResponse($data);

        } catch (\Throwable $ex) {
            # $this->logger->error('Apple Pay Direct error when creating payment session: ' . $ex->getMessage());
            throw $ex;
        }
    }


}
