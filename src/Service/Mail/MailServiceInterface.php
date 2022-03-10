<?php

namespace Kiener\MolliePayments\Service\Mail;

use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;

interface MailServiceInterface
{
    const RECIPIENTS = [
        // email => name
        'daniel@memo-ict.nl' => 'Daniel Schipper'
    ];

    /**
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>> $attachments
     * @throws ConstraintViolationException
     * @throws MailTransportFailedException
     * @return void
     */
    public function send(array $data, array $attachments = []): void;
}
