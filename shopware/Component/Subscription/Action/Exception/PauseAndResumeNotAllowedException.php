<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action\Exception;

final class PauseAndResumeNotAllowedException extends \Exception
{
    public function __construct(string $salesChannelId)
    {
        $message = sprintf('Pausing and resuming subscriptions is not allowed for sales channel with id %s.', $salesChannelId);

        parent::__construct($message);
    }
}
