<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class Attachment implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public string $content,
        public string $fileName,
        public string $mimeType,
    ) {
    }

    /**
     * @return array{content: string, fileName: string, mimeType: string}
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
        ];
    }
}
