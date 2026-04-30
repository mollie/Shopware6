<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSalesChannelContext extends SalesChannelContext
{
    private string $fakeSalesChannelId;
    private string $fakeToken;
    private Context $fakeContext;
    private ?CustomerEntity $fakeCustomer = null;
    private ?PaymentMethodEntity $fakePaymentMethod = null;

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

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->fakePaymentMethod = $paymentMethod;
    }

    public function getSalesChannelId(): string
    {
        return $this->fakeSalesChannelId;
    }

    public function getToken(): string
    {
        return $this->fakeToken;
    }

    public function getContext(): Context
    {
        return $this->fakeContext;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->fakeCustomer;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        if ($this->fakePaymentMethod === null) {
            throw new \LogicException('FakeSalesChannelContext::getPaymentMethod() called without configured payment method. Use setPaymentMethod() in the test.');
        }

        return $this->fakePaymentMethod;
    }
}
