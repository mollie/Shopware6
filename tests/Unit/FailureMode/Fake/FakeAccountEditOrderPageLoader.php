<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode\Fake;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Symfony\Component\HttpFoundation\Request;

final class FakeAccountEditOrderPageLoader extends AccountEditOrderPageLoader
{
    /**
     * @param ?\Throwable $failure the OrderException Shopware raises when the order cannot be
     *                             edited any more, e.g. because it was cancelled
     */
    public function __construct(
        private readonly PaymentMethodCollection $paymentMethods = new PaymentMethodCollection(),
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountEditOrderPage
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $page = new AccountEditOrderPage();
        $page->setPaymentMethods($this->paymentMethods);

        return $page;
    }
}
