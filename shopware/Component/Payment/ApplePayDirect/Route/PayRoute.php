<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Account\AbstractAccountService;
use Mollie\Shopware\Component\Account\AccountService;
use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class PayRoute extends AbstractPayRoute
{
    public function __construct(
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        #[Autowire(service: CartOrderRoute::class)]
        private AbstractCartOrderRoute $cartOrderRoute,
        #[Autowire(service: HandlePaymentMethodRoute::class)]
        private AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        #[Autowire(service: GetCartRoute::class)]
        private AbstractGetCartRoute $getCartRoute,
        #[Autowire(service: ApplePayDirectEnabledRoute::class)]
        private AbstractApplePayDirectEnabledRoute $applePayDirectEnabledRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractPayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/applepay/pay', name: 'store-api.apple-pay.pay', methods: ['POST'])]
    public function pay(Request $request, SalesChannelContext $salesChannelContext): PayResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - applepay direct payment', $logData);
        $response = $this->applePayDirectEnabledRoute->getEnabled($salesChannelContext);

        $applePayPaymentMethodId = $response->getPaymentMethodId();
        $applePayDirectEnabled = $response->isEnabled();
        if ($applePayDirectEnabled === false && $applePayPaymentMethodId === null) {
            $this->logger->error('Apple pay direct ist disabled', $logData);
            throw ApplePayDirectException::paymentDisabled();
        }

        $paymentToken = $request->get('paymentToken');

        if ($paymentToken === null) {
            $this->logger->error('"paymentToken" not set in request body', $logData);
            throw ApplePayDirectException::paymentTokenNotFound();
        }

        $email = (string) $request->get('email', '');
        $firstname = (string) $request->get('firstname', '');
        $lastname = (string) $request->get('lastname', '');
        $street = (string) $request->get('street', '');
        $zipcode = (string) $request->get('postalCode', '');
        $city = (string) $request->get('city', '');
        $countryCode = (string) $request->get('countryCode', '');
        $phone = (string) $request->get('phone', '');
        $acceptedDataProtection = (int) $request->get('acceptedDataProtection', '0');

        $billingAddress = new Address($email, '', $firstname, $lastname, $street, $zipcode, $city, $countryCode);
        if (mb_strlen($phone) > 0) {
            $billingAddress->setPhone($phone);
        }
        $shippingAddress = $billingAddress;

        try {
            $salesChannelContext = $this->accountService->loginOrCreateAccount((string) $applePayPaymentMethodId, $billingAddress, $shippingAddress, $salesChannelContext);
            $customer = $salesChannelContext->getCustomer();
            if ($customer instanceof CustomerEntity) {
                $logData['customerNumber'] = $customer->getCustomerNumber();
                $logData['customerId'] = $customer->getId();
            }

            $this->logger->debug('Login or create account successfull', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Failed to create a guest account or login with existing customer', $logData);
            throw ApplePayDirectException::customerActionFailed($exception);
        }

        try {
            $cartResponse = $this->getCartRoute->cart($request, $salesChannelContext);
            $this->logger->debug('Apple pay direct cart loaded', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Failed to load apple pay direct cart', $logData);
            throw ApplePayDirectException::loadCartFailed($exception);
        }

        try {
            $orderResponse = $this->cartOrderRoute->order($cartResponse->getShopwareCart(), $salesChannelContext, new RequestDataBag());
            $orderEntity = $orderResponse->getOrder();
            $orderId = $orderEntity->getId();
            $logData['orderId'] = $orderId;
            $logData['orderNumber'] = $orderEntity->getOrderNumber();
            $this->logger->debug('Apple pay direct order created', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Failed to create an apple pay direct order', $logData);
            throw ApplePayDirectException::createOrderFailed($exception);
        }

        try {
            $request = new Request();
            $request->request->set('orderId', $orderId);
            $request->request->set('paymentToken', $paymentToken);
            $handlePaymentResponse = $this->handlePaymentMethodRoute->load($request, $salesChannelContext);
            $this->logger->debug('Apple pay direct payment handled', $logData);
        } catch (\Throwable $exception) {
            $logData['message'] = $exception->getMessage();
            $this->logger->error('Failed to handle apple pay direct payment', $logData);
            throw ApplePayDirectException::paymentFailed($exception, $orderId);
        }
        $redirectUrl = '';
        $redirectResponse = $handlePaymentResponse->getRedirectResponse();
        if ($redirectResponse instanceof RedirectResponse) {
            $redirectUrl = $redirectResponse->getTargetUrl();
        }
        $logData['redirectUrl'] = $redirectUrl;
        $this->logger->info('Finished - applepay direct payment', $logData);

        return new PayResponse(true, $redirectUrl, '', $orderId,$salesChannelContext);
    }
}
