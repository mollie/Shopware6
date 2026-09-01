<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Fake;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Mollie\Shopware\Component\Support\Attachment\Generator\AttachmentGeneratorInterface;
use Shopware\Core\Framework\Context;

final class FakeAttachmentGenerator implements AttachmentGeneratorInterface
{
    public function __construct(private readonly Attachment $attachment)
    {
    }

    public function generate(Context $context): Attachment
    {
        return $this->attachment;
    }
}
