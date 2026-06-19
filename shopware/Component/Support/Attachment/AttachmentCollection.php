<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment;

final class AttachmentCollection
{
    /** @var Attachment[] */
    private array $attachments = [];

    public function add(Attachment $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->attachments as $attachment) {
            $result[] = json_decode((string) json_encode($attachment), true);
        }

        return $result;
    }
}
