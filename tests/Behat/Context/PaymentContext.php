<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat;

use Behat\Step\Given;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;

final class PaymentContext extends ShopwareContext
{
    use PaymentMethodTestBehaviour;

    #[Given('payment method :arg1 exists and active')]
    public function paymentMethodExistsAndActive(string $paymentMethodIdentifier): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodIdentifier, $salesChannelContext->getContext());
        $this->activatePaymentMethod($paymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($paymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());
    }
}
