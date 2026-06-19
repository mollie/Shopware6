<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment\Generator;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mollie.support.attachment_generator')]
interface AttachmentGeneratorInterface
{
    public function generate(Context $context): Attachment;
}
