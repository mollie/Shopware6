<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Shopware\Core\Framework\Context;

interface GeneratorInterface
{
    /**
     * Generates an attachment to be added to an email
     * @return array<string, mixed>
     */
    public function generate(Context $context): array;
}
