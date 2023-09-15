<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface MandateServiceInterface
{
    /**
     * @param string $customerId
     * @param string $mandateId
     * @param SalesChannelContext $context
     * @return void
     */
    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void;

    /**
     * @param string $customerId
     * @param SalesChannelContext $context
     * @return MandateCollection
     */
    public function getCreditCardMandatesByCustomerId(string $customerId, SalesChannelContext $context): MandateCollection;
}
