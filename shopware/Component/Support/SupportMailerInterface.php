<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support;

use Shopware\Core\Framework\Context;

interface SupportMailerInterface
{
    /**
     * @throws \Throwable
     */
    public function send(
        string $name,
        string $email,
        ?string $recipientLocale,
        string $host,
        string $subject,
        string $message,
        Context $context
    ): void;
}
