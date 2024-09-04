<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Tags;

class SubscriptionTag extends AbstractTag
{
    public const TAG_NAME = 'mollie-subscription-tag';
    public const TAG_ID = 'c4b7c9b6e0c5435c8a74f5de6051b678';

    public static function create(): self
    {
        return parent::createObject(self::TAG_NAME, self::TAG_ID);
    }
}
