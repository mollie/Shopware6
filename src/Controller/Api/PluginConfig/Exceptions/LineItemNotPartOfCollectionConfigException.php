<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions;

class LineItemNotPartOfCollectionConfigException extends MollieRefundConfigException
{
    public static function create(string $id): self
    {
        $message = sprintf('The line item with ID "%s" is not part of the collection.', $id);

        return new self($message);
    }
}
