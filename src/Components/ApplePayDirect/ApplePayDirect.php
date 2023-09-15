<?php

namespace Kiener\MolliePayments\Components\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDomainVerificationService;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayFormatter;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayShippingBuilder;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Repository\Order\OrderAddressRepository;
use Kiener\MolliePayments\Repository\Order\OrderAddressRepositoryInterface;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepository;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\DomainExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShopService;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayDirect
{
    /**
     * @var ApplePayDomainVerificationService
     */
    private $domainFileDownloader;

    /**
     * @var ApplePayPayment
     */
    private $paymentHandler;

    /**
     * @var MolliePaymentDoPay
     */
    private $molliePayments;


    /**
     * @var CartServiceInterface
     */
    private $cartService;

    /**
     * @var ApplePayFormatter
     */
    private $formatter;

    /**
     * @var ApplePayShippingBuilder
     */
    private $shippingBuilder;

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var PaymentMethodRepository
     */
    private $repoPaymentMethods;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;

    /**
     * @var ShopService
     */
    private $shopService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderAddressRepositoryInterface
     */
    private $repoOrderAdresses;


    /**
     * @param ApplePayDomainVerificationService $domainFileDownloader
     * @param ApplePayPayment $paymentHandler
     * @param MolliePaymentDoPay $molliePayments
     * @param CartServiceInterface $cartService
     * @param ApplePayFormatter $formatter
     * @param ApplePayShippingBuilder $shippingBuilder
     * @param SettingsService $pluginSettings
     * @param CustomerService $customerService
     * @param PaymentMethodRepository $repoPaymentMethods
     * @param CartBackupService $cartBackupService
     * @param MollieApiFactory $mollieApiFactory
     * @param ShopService $shopService
     * @param OrderService $orderService
     * @param OrderAddressRepositoryInterface $repoOrderAdresses
     */
    public function __construct(ApplePayDomainVerificationService $domainFileDownloader, ApplePayPayment $paymentHandler, MolliePaymentDoPay $molliePayments, CartServiceInterface $cartService, ApplePayFormatter $formatter, ApplePayShippingBuilder $shippingBuilder, SettingsService $pluginSettings, CustomerService $customerService, PaymentMethodRepository $repoPaymentMethods, CartBackupService $cartBackupService, MollieApiFactory $mollieApiFactory, ShopService $shopService, OrderService $orderService, OrderAddressRepositoryInterface $repoOrderAdresses)
    {
        $this->domainFileDownloader = $domainFileDownloader;
        $this->paymentHandler = $paymentHandler;
        $this->molliePayments = $molliePayments;
        $this->cartService = $cartService;
        $this->formatter = $formatter;
        $this->shippingBuilder = $shippingBuilder;
        $this->pluginSettings = $pluginSettings;
        $this->customerService = $customerService;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->cartBackupService = $cartBackupService;
        $this->mollieApiFactory = $mollieApiFactory;
        $this->shopService = $shopService;
        $this->orderService = $orderService;
        $this->repoOrderAdresses = $repoOrderAdresses;
    }


    /**
     *
     */
    public function downloadDomainAssociationFile(): void
    {
        $this->domainFileDownloader->downloadDomainAssociationFile();
    }

    /**
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return string
     */
    public function getActiveApplePayID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActiveApplePayID($context->getContext());
    }

    /**
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return bool
     */
    public function isApplePayDirectEnabled(SalesChannelContext $context): bool
    {
        $settings = $this->pluginSettings->getSettings($context->getSalesChannel()->getId());

        /** @var null|array<mixed> $salesChannelPaymentIDs */
        $salesChannelPaymentIDs = $context->getSalesChannel()->getPaymentMethodIds();

        $enabled = false;

        if (is_array($salesChannelPaymentIDs) && $settings->isEnableApplePayDirect()) {
            try {
                $applePayMethodID = $this->repoPaymentMethods->getActiveApplePayID($context->getContext());

                foreach ($salesChannelPaymentIDs as $tempID) {
                    # verify if our Apple Pay payment method is indeed in use
                    # for the current sales channel
                    if ($tempID === $applePayMethodID) {
                        $enabled = true;
                        break;
                    }
                }
            } catch (\Exception $ex) {
                # it can happen that apple pay is just not active in the system
            }
        }

        return $enabled;
    }

    /**
     * @param SalesChannelContext $context
     * @return ApplePayCart
     */
    public function getCart(SalesChannelContext $context): ApplePayCart
    {
        $currentMethodID = $context->getShippingMethod()->getId();
        $context = $this->cartService->updateShippingMethod($context, $currentMethodID);

        $swCart = $this->cartService->getCalculatedMainCart($context);

        return $this->buildApplePayCart($swCart);
    }

    /**
     * @param SalesChannelContext $context
     * @return array<mixed>
     */
    public function getCartFormatted(SalesChannelContext $context): array
    {
        $cart = $this->getCart($context);

        $settings = $this->pluginSettings->getSettings($context->getSalesChannel()->getId());

        $isTestMode = $settings->isTestMode();

        return $this->formatter->formatCart($cart, $context->getSalesChannel(), $isTestMode);
    }

    /**
     * @param string $productId
     * @param int $quantity
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return Cart
     */
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        # if we already have a backup cart, then do NOT backup again.
        # because this could backup our temp. apple pay cart
        if (!$this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->backupCart($context);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        # clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());
        $this->cartService->updateCart($cart);

        # add new product to cart
        $this->cartService->addProduct($productId, $quantity, $context);

        return $this->cartService->getCalculatedMainCart($context);
    }

    /**
     * @param string $shippingMethodID
     * @param SalesChannelContext $context
     * @return SalesChannelContext
     */
    public function setShippingMethod(string $shippingMethodID, SalesChannelContext $context): SalesChannelContext
    {
        return $this->cartService->updateShippingMethod($context, $shippingMethodID);
    }

    /**
     * @param string $validationURL
     * @param SalesChannelContext $context
     * @throws ApiException
     * @return string
     */
    public function createPaymentSession(string $validationURL, SalesChannelContext $context): string
    {
        # make sure to get rid of any http prefixes or
        # also any sub shop slugs like /de or anything else
        # that would NOT work with Mollie and Apple Pay!
        $domainExtractor = new DomainExtractor();
        $domain = $domainExtractor->getCleanDomain($this->shopService->getShopUrl(true));

        # we always have to use the LIVE api key for
        # our first domain validation for Apple Pay!
        # the rest will be done with our test API key (if test mode active), or also Live API key (no test mode)
        $liveClient = $this->mollieApiFactory->getLiveClient($context->getSalesChannel()->getId());

        /** @var null|string $session */
        $session = $liveClient->wallets->requestApplePayPaymentSession($domain, $validationURL);

        return (string)$session;
    }

    /**
     * @param string $countryCode
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return array<mixed>
     */
    public function getShippingMethods(string $countryCode, SalesChannelContext $context): array
    {
        $currentMethodID = $context->getShippingMethod()->getId();

        $countryID = (string)$this->customerService->getCountryId($countryCode, $context->getContext());

        # get all available shipping methods of
        # our current country for Apple Pay
        $shippingMethods = $this->shippingBuilder->getShippingMethods($countryID, $context);

        # restore our previously used shipping method
        # this is very important to avoid accidental changes in the context
        $this->cartService->updateShippingMethod($context, $currentMethodID);

        return $shippingMethods;
    }

    /**
     * @param SalesChannelContext $context
     */
    public function restoreCart(SalesChannelContext $context): void
    {
        if ($this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->restoreCart($context);
        }

        $this->cartBackupService->clearBackup($context);
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
     * @throws \Exception
     * @return SalesChannelContext
     */
    public function prepareCustomer(string $firstname, string $lastname, string $email, string $street, string $zipcode, string $city, string $countryCode, string $paymentToken, SalesChannelContext $context): SalesChannelContext
    {
        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }


        # we clear our cart backup now
        # we are in the user redirection process where a restoring wouldn't make sense
        # because from now on we would end on the cart page where we could even switch payment method.
        $this->cartBackupService->clearBackup($context);


        $applePayID = $this->getActiveApplePayID($context);

        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        if (!$this->customerService->isCustomerLoggedIn($context)) {
            $customer = $this->customerService->createApplePayDirectCustomer(
                $firstname,
                $lastname,
                $email,
                '',
                $street,
                $zipcode,
                $city,
                $countryCode,
                $applePayID,
                $context
            );

            if (!$customer instanceof CustomerEntity) {
                throw new \Exception('Error when creating customer!');
            }

            # now start the login of our customer.
            # Our SalesChannelContext will be correctly updated after our
            # forward to the finish-payment page.
            $this->customerService->customerLogin($customer, $context);
        }

        # also (always) update our payment method to use Apple Pay for our cart
        return $this->cartService->updatePaymentMethod($context, $applePayID);
    }

    /**
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(SalesChannelContext $context): OrderEntity
    {
        $data = new DataBag();

        # we have to agree to the terms of services
        # to avoid constraint violation checks
        $data->add(['tos' => true]);

        # create our new Order using the
        # Shopware function for it.
        return $this->orderService->createOrder($data, $context);
    }

    /**
     * @param OrderEntity $order
     * @param string $shopwareReturnUrl
     * @param string $firstname
     * @param string $lastname
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryCode
     * @param string $paymentToken
     * @param SalesChannelContext $context
     * @throws ApiException
     * @return string
     */
    public function createPayment(OrderEntity $order, string $shopwareReturnUrl, string $firstname, string $lastname, string $street, string $zipcode, string $city, string $countryCode, string $paymentToken, SalesChannelContext $context): string
    {
        # immediately try to get the country of the buyer.
        # maybe this could lead to an exception if that country is not possible.
        # that's why we do it within these first steps.
        $countryID = (string)$this->customerService->getCountryId($countryCode, $context->getContext());


        # always make sure to use the correct address from Apple Pay
        # and never the one from the customer (if already existing)
        if ($order->getAddresses() instanceof OrderAddressCollection) {
            foreach ($order->getAddresses() as $address) {
                # attention, Apple Pay does not have a company name
                # therefore we always need to make sure to remove the company field in our order
                $this->repoOrderAdresses->updateAddress(
                    $address->getId(),
                    $firstname,
                    $lastname,
                    '',
                    '',
                    '',
                    $street,
                    $zipcode,
                    $city,
                    $countryID,
                    $context->getContext()
                );
            }
        }


        # get the latest new transaction.
        # we need this for our payment handler
        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction = $transactions->last();

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new \Exception('Created Apple Pay Direct order has not OrderTransaction!');
        }

        # generate the finish URL for our shopware page.
        # This is required, because we will immediately bring the user to this page.
        $asyncPaymentTransition = new AsyncPaymentTransactionStruct($transaction, $order, $shopwareReturnUrl);

        # now set the Apple Pay payment token for our payment handler.
        # This is required for a smooth checkout with our already validated Apple Pay transaction.
        $this->paymentHandler->setToken($paymentToken);

        $paymentData = $this->molliePayments->startMolliePayment(ApplePayPayment::PAYMENT_METHOD_NAME, $asyncPaymentTransition, $context, $this->paymentHandler);

        if (empty($paymentData->getCheckoutURL())) {
            throw new \Exception('Error when creating Apple Pay Direct order in Mollie');
        }


        # now also update the custom fields of our order
        # we want to have the mollie metadata in the
        # custom fields in Shopware too
        $this->orderService->updateMollieDataCustomFields(
            $order,
            $paymentData->getMollieID(),
            '',
            $transaction->getId(),
            $context->getContext()
        );


        return $paymentData->getMollieID();
    }

    /**
     * @param Cart $cart
     * @return ApplePayCart
     */
    private function buildApplePayCart(Cart $cart): ApplePayCart
    {
        $appleCart = new ApplePayCart();

        foreach ($cart->getLineItems() as $item) {
            if ($item->getPrice() instanceof CalculatedPrice) {
                $appleCart->addItem(
                    (string)$item->getReferencedId(),
                    (string)$item->getLabel(),
                    $item->getQuantity(),
                    $item->getPrice()->getUnitPrice()
                );
            }
        }

        foreach ($cart->getDeliveries() as $delivery) {
            $appleCart->addShipping(
                (string)$delivery->getShippingMethod()->getName(),
                $delivery->getShippingCosts()->getUnitPrice()
            );
        }

        $taxes = $cart->getPrice()->getCalculatedTaxes()->getAmount();

        if ($taxes > 0) {
            $appleCart->setTaxes($taxes);
        }

        return $appleCart;
    }
}
