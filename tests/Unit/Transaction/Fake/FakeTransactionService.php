<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Mollie\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Mollie\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Mollie\Fake\FakeOrderTransactionRepository;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class FakeTransactionService implements TransactionServiceInterface
{
    private FakeOrderTransactionRepository $orderTransactionRepository;

    private bool $withPayment = false;
    private ?TransactionDataStruct $transaction = null;

    private FakeCustomerRepository $customerRepository;
    private FakeOrderRepository $orderRepository;
    private array $orderCustomFields = [];

    public function __construct()
    {
        $this->customerRepository = new FakeCustomerRepository();
        $this->orderRepository = new FakeOrderRepository();
    }

    public function findById(string $transactionId, Context $context): TransactionDataStruct
    {
        if ($this->transaction === null) {
            $this->createTransaction();
        }
        $this->transaction->getTransaction()->setId($transactionId);

        return $this->transaction;
    }

    public function savePaymentExtension(string $transactionId, OrderEntity $order, Payment $payment, Context $context): EntityWrittenContainerEvent
    {
        $context = new Context(new SystemSource());
        $nestedEventCollection = new NestedEventCollection();

        return new EntityWrittenContainerEvent($context, $nestedEventCollection, []);
    }

    public function createValidStruct(): void
    {
        $this->withPayment = true;
        $this->createTransaction();
    }

    public function withOrderCustomFields(array $customFields): void
    {
        $this->orderCustomFields = $customFields;
        $this->createTransaction();
    }

    public function getDefaultSalesChannelEntity(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        return $salesChannel;
    }

    public function createTransaction(): void
    {
        $transaction = new OrderTransactionEntity();
        $currency = $this->getDefaultCurrency();
        $language = $this->getDefaultLanguage();
        $customer = $this->customerRepository->getDefaultCustomer();
        if ($this->withPayment) {
            $payment = new Payment('testMollieId', PaymentMethod::CREDIT_CARD);
            $payment->setFinalizeUrl('payment/finalize');
            $transaction->addExtension(Mollie::EXTENSION, $payment);
        }

        $order = $this->orderRepository->getDefaultOrder($customer);
        $order->setCurrency($currency);
        $order->setLanguage($language);
        if (count($this->orderCustomFields) > 0) {
            $order->setCustomFields([
                Mollie::EXTENSION => $this->orderCustomFields
            ]);
        }
        $shippingAddress = $this->orderRepository->getOrderAddress($customer);
        $billingAddress = $this->orderRepository->getOrderAddress($customer);

        $this->transaction = new TransactionDataStruct(
            $transaction,
            $order,
            $this->getDefaultSalesChannelEntity(),
            $customer,
            $shippingAddress,
            $billingAddress,
            $currency,
            $language,
            $this->orderRepository->getOrderDeliveries($customer)
        );
    }

    private function getDefaultCurrency(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        return $currency;
    }

    private function getDefaultLocale(): LocaleEntity
    {
        $locale = new LocaleEntity();
        $locale->setCode('en-GB');

        return $locale;
    }

    private function getDefaultLanguage(): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setLocale($this->getDefaultLocale());

        return $language;
    }

    private function getDefaultCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setIso('DE');

        return $country;
    }
}
