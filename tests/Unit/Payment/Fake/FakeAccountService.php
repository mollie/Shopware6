<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractAccountService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeAccountService extends AbstractAccountService
{
    public function __construct(private SalesChannelContext $newContext)
    {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function loginOrCreateAccount(string $paymentMethodId, Address $billingAddress, Address $shippingAddress, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        return $this->newContext;
    }
}
