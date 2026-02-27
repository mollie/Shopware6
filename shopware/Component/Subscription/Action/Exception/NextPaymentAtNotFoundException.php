<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action\Exception;

final class NextPaymentAtNotFoundException extends \Exception
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(sprintf('Next payment date of subscription %s not found',$subscriptionId));
    }
}
