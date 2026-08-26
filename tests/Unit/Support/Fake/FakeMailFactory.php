<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Fake;

use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Symfony\Component\Mime\Email;

/**
 * Records the arguments the mail was built from, so a test can assert on the recipients and the
 * attachments without going through Symfony's mime layer.
 */
final class FakeMailFactory extends AbstractMailFactory
{
    /** @var array<string, mixed> */
    private array $lastCall = [];

    public function getDecorated(): AbstractMailFactory
    {
        return $this;
    }

    public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email {
        $this->lastCall = [
            'subject' => $subject,
            'sender' => $sender,
            'recipients' => $recipients,
            'contents' => $contents,
            'binAttachments' => $binAttachments,
        ];

        return new Email();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastCall(): array
    {
        if ($this->lastCall === []) {
            throw new \RuntimeException('No mail has been created.');
        }

        return $this->lastCall;
    }
}
