<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class FakeSalesChannelContext extends SalesChannelContext
{
    private string $fakeSalesChannelId;
    private string $fakeToken;
    private Context $fakeContext;
    private ?CustomerEntity $fakeCustomer = null;
    private ?PaymentMethodEntity $fakePaymentMethod = null;

    private ?ShippingMethodEntity $fakeShippingMethod = null;
    private ?CurrencyEntity $fakeCurrency = null;
    private ?ShippingLocation $fakeShippingLocation = null;

    public function __construct(
        string $salesChannelId = 'sales-channel-id',
        string $token = 'cart-token',
        ?Context $context = null,
    ) {
        $this->fakeSalesChannelId = $salesChannelId;
        $this->fakeToken = $token;
        $this->fakeContext = $context ?? Context::createDefaultContext();
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->fakeCustomer = $customer;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->fakeCurrency = $currency;
    }

    public function getCurrency(): CurrencyEntity
    {
        if ($this->fakeCurrency === null) {
            $currency = new CurrencyEntity();
            $currency->setId('currency-id');
            $currency->setIsoCode('EUR');

            return $currency;
        }

        return $this->fakeCurrency;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->fakePaymentMethod = $paymentMethod;
    }

    public function getSalesChannelId(): string
    {
        return $this->fakeSalesChannelId;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($this->fakeSalesChannelId);
        $salesChannel->setName('Fake Sales Channel');
        $salesChannel->setLanguageId($this->fakeContext->getLanguageId());

        return $salesChannel;
    }

    public function getToken(): string
    {
        return $this->fakeToken;
    }

    public function getContext(): Context
    {
        return $this->fakeContext;
    }

    /**
     * The parent delegates the rule ids to its inner Context, which the fake never builds - so
     * both accessors go to the fake context instead.
     *
     * @return array<string>
     */
    public function getRuleIds(): array
    {
        return $this->fakeContext->getRuleIds();
    }

    /**
     * @param array<string> $ruleIds
     */
    public function setRuleIds(array $ruleIds): void
    {
        $this->fakeContext->setRuleIds($ruleIds);
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->fakeCustomer;
    }

    public function getLanguageId(): string
    {
        return $this->fakeContext->getLanguageId();
    }

    /**
     * The parent reads its own promoted constructor property, which the fake never fills - so both
     * the customer id and the shipping method come from what the test set here.
     */
    public function getCustomerId(): ?string
    {
        return $this->fakeCustomer?->getId();
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->fakeShippingMethod = $shippingMethod;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        if ($this->fakeShippingMethod === null) {
            throw new \LogicException('FakeSalesChannelContext::getShippingMethod() called without configured shipping method. Use setShippingMethod() in the test.');
        }

        return $this->fakeShippingMethod;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        if ($this->fakePaymentMethod === null) {
            throw new \LogicException('FakeSalesChannelContext::getPaymentMethod() called without configured payment method. Use setPaymentMethod() in the test.');
        }

        return $this->fakePaymentMethod;
    }

    public function setShippingLocation(ShippingLocation $shippingLocation): void
    {
        $this->fakeShippingLocation = $shippingLocation;
    }

    public function getShippingLocation(): ShippingLocation
    {
        if ($this->fakeShippingLocation === null) {
            throw new \LogicException('FakeSalesChannelContext::getShippingLocation() called without configured shipping location. Use setShippingLocation() in the test.');
        }

        return $this->fakeShippingLocation;
    }
}
