<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface MandateServiceInterface
{
    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void;
    public function getCreditCardMandatesByCustomerId(string $customerId, SalesChannelContext $context): MandateCollection;
}
