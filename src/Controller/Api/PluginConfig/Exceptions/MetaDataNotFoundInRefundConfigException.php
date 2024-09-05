<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions;

class MetaDataNotFoundInRefundConfigException extends MollieRefundConfigException
{
    public static function create(): self
    {
        return new self('The meta data for the refund could not be found.');
    }
}
