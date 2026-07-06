<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Attachment implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly string $content,
        public readonly string $fileName,
        public readonly string $mimeType,
    ) {
    }
}
