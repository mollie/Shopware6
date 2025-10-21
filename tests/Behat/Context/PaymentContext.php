<?php

namespace Mollie\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Mollie\Integration\Data\PaymentMethodTestBehaviour;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\RedirectResponse;


final class PaymentContext extends ShopwareContext
{
    use PaymentMethodTestBehaviour;

    /**
     * @Given payment method :arg1 exists and active
     */
    public function paymentMethodExistsAndActive(string $paymentMethodIdentifier): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodIdentifier, $salesChannelContext->getContext());
        $this->activatePaymentMethod($paymentMethod, $salesChannelContext->getContext());
    }


}