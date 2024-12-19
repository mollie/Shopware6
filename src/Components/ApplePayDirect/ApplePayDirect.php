<?php

namespace Kiener\MolliePayments\Components\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\Exceptions\ApplePayDirectDomainAllowListCanNotBeEmptyException;
use Kiener\MolliePayments\Components\ApplePayDirect\Exceptions\ApplePayDirectDomainNotInAllowListException;
use Kiener\MolliePayments\Components\ApplePayDirect\Gateways\ApplePayDirectDomainAllowListGateway;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDirectDomainSanitizer;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDomainVerificationService;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayFormatter;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayShippingBuilder;
use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\DomainExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\ShopService;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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
     * @var EntityRepository
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
     * @var EntityRepository
     */
    private $repoOrderAdresses;

    /**
     * @var ApplePayDirectDomainAllowListGateway
     */
    private $applePayDirectDomainAllowListGateway;

    /**
     * @var ApplePayDirectDomainSanitizer
     */
    private $domainSanitizer;


    /**
     * @param ApplePayDomainVerificationService $domainFileDownloader
     * @param ApplePayPayment $paymentHandler
     * @param MolliePaymentDoPay $molliePayments
     * @param CartServiceInterface $cartService
     * @param ApplePayFormatter $formatter
     * @param ApplePayShippingBuilder $shippingBuilder
     * @param SettingsService $pluginSettings
     * @param CustomerService $customerService
     * @param EntityRepository $repoPaymentMethods
     * @param CartBackupService $cartBackupService
     * @param MollieApiFactory $mollieApiFactory
     * @param ShopService $shopService
     * @param OrderService $orderService
     * @param EntityRepository $repoOrderAdresses
     * @param ApplePayDirectDomainAllowListGateway $domainAllowListGateway
     * @param ApplePayDirectDomainSanitizer $domainSanitizer
     */
    public function __construct(ApplePayDomainVerificationService $domainFileDownloader, ApplePayPayment $paymentHandler, MolliePaymentDoPay $molliePayments, CartServiceInterface $cartService, ApplePayFormatter $formatter, ApplePayShippingBuilder $shippingBuilder, SettingsService $pluginSettings, CustomerService $customerService, EntityRepository $repoPaymentMethods, CartBackupService $cartBackupService, MollieApiFactory $mollieApiFactory, ShopService $shopService, OrderService $orderService, EntityRepository $repoOrderAdresses, ApplePayDirectDomainAllowListGateway $domainAllowListGateway, ApplePayDirectDomainSanitizer $domainSanitizer)
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
        $this->applePayDirectDomainAllowListGateway = $domainAllowListGateway;
        $this->domainSanitizer = $domainSanitizer;
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
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', ApplePayPayment::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $paymentMethods */
        $paymentMethods = $this->repoPaymentMethods->searchIds($criteria, $context->getContext())->getIds();

        if (count($paymentMethods) <= 0) {
            throw new \Exception('Payment Method Apple Pay Direct not found in system');
        }

        return (string)$paymentMethods[0];
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
                $applePayMethodID = $this->getActiveApplePayID($context);

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
        if (! $this->cartBackupService->isBackupExisting($context)) {
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
     * @param string $domain
     * @param SalesChannelContext $context
     * @throws ApiException
     * @throws ApplePayDirectDomainAllowListCanNotBeEmptyException
     * @throws ApplePayDirectDomainNotInAllowListException
     * @return string
     */
    public function createPaymentSession(string $validationURL, string $domain, SalesChannelContext $context): string
    {
        $domain = $this->getValidDomain($domain, $context);
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
    public function prepareCustomer(string $firstname, string $lastname, string $email, string $street, string $zipcode, string $city, string $countryCode, string $phone, string $paymentToken, ?int $acceptedDataProtection, SalesChannelContext $context): SalesChannelContext
    {
        if (empty($paymentToken)) {
            throw new \Exception('PaymentToken not found!');
        }

        $updateShippingAddress = true;
        $applePayID = $this->getActiveApplePayID($context);
        $customer = $context->getCustomer();
        $shippingAddress = new AddressStruct($firstname, $lastname, $email, $street, '', $zipcode, $city, $countryCode, $phone);
        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        if ($customer === null) {


            # find existing customer by email
            $customer = $this->customerService->findCustomerByEmail($shippingAddress->getEmail(), $context);

            if ($customer === null) {
                $updateShippingAddress = false;


                $customer = $this->customerService->createGuestAccount(
                    $shippingAddress,
                    $applePayID,
                    $context,
                    $acceptedDataProtection
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

        if ($updateShippingAddress) {
            $this->customerService->reuseOrCreateAddresses($customer, $shippingAddress, $context->getContext());
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
                $this->repoOrderAdresses->update([
                    [
                        'id' =>   $address->getId(),
                        'firstName' => $firstname,
                        'lastName' => $lastname,
                        'company' => '',
                        'department' => '',
                        'vatId' => '',
                        'street' => $street,
                        'zipcode' => $zipcode,
                        'city' => $city,
                        'countryId' => $countryID,
                    ]
                ], $context->getContext());
            }
        }


        # get the latest new transaction.
        # we need this for our payment handler
        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction = $transactions->last();

        if (! $transaction instanceof OrderTransactionEntity) {
            throw new \Exception('Created Apple Pay Direct order has not OrderTransaction!');
        }

        # generate the finish URL for our shopware page.
        # This is required, because we will immediately bring the user to this page.
        $asyncPaymentTransition = new AsyncPaymentTransactionStruct($transaction, $order, $shopwareReturnUrl);

        # now set the Apple Pay payment token for our payment handler.
        # This is required for a smooth checkout with our already validated Apple Pay transaction.
        $this->paymentHandler->setToken($paymentToken);

        $paymentData = $this->molliePayments->startMolliePayment(ApplePayPayment::PAYMENT_METHOD_NAME, $asyncPaymentTransition, $context, $this->paymentHandler, new RequestDataBag());

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

    /**
     * This method will return a valid domain if not provided by the user it will use the shop domain
     *
     * @param SalesChannelContext $context
     * @param string $domain
     * @throws ApplePayDirectDomainAllowListCanNotBeEmptyException
     * @throws ApplePayDirectDomainNotInAllowListException
     * @return string
     */
    private function getValidDomain(string $domain, SalesChannelContext $context): string
    {
        #   if we have no domain, then we need to use the shop domain
        if (empty($domain)) {
            # make sure to get rid of any http prefixes or
            # also any sub shop slugs like /de or anything else
            # that would NOT work with Mollie and Apple Pay!
            $domainExtractor = new DomainExtractor();
            return $domainExtractor->getCleanDomain($this->shopService->getShopUrl(true));
        }

        $allowList = $this->applePayDirectDomainAllowListGateway->getAllowList($context);

        if ($allowList->isEmpty()) {
            throw new ApplePayDirectDomainAllowListCanNotBeEmptyException();
        }

        $sanitizedDomain = $this->domainSanitizer->sanitizeDomain($domain);

        if ($allowList->contains($sanitizedDomain) === false) {
            throw new ApplePayDirectDomainNotInAllowListException($sanitizedDomain);
        }

        return $sanitizedDomain;
    }
}
