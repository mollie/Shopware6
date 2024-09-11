<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions;

class MollieOrderNotFoundConfigException extends MollieRefundConfigException
{
    public static function create(string $orderId): self
    {
        $message = sprintf('Order with ID "%s" not found', $orderId);
        return new self($message);
    }
}
