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
     * @return list<array{content: string, fileName: string, mimeType: null|string}>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->attachments as $attachment) {
            $result[] = [
                'content' => $attachment->content,
                'fileName' => $attachment->fileName,
                'mimeType' => $attachment->mimeType,
            ];
        }

        return $result;
    }
}
