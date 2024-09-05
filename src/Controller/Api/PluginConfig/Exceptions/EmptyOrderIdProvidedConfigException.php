<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions;

class EmptyOrderIdProvidedConfigException extends MollieRefundConfigException
{
    public static function create(): self
    {
        return new self('No order id provided');
    }
}
