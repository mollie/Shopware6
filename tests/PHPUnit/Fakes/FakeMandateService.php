<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeMandateService implements MandateServiceInterface
{
    private bool $throwException;

    public function __construct(bool $throwException = false)
    {
        $this->throwException = $throwException;
    }

    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void
    {
        if (! $this->throwException) {
            return;
        }

        throw new \Exception('Error');
    }

    public function getCreditCardMandatesByCustomerId(string $customerId, SalesChannelContext $context): MandateCollection
    {
        return new MandateCollection();
    }

    public function getConnectedSubscriptionByMandateId(string $customerId, string $mandateId, SalesChannelContext $context): SubscriptionCollection
    {
        return new SubscriptionCollection();
    }
}
