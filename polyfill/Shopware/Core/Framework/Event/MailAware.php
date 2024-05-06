<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

if (interface_exists(__NAMESPACE__ . '/MailAware')) {
    return;
}


use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;


interface MailAware extends BusinessEventInterface
{
    public const MAIL_STRUCT = 'mailStruct';

    public const SALES_CHANNEL_ID = 'salesChannelId';

    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
