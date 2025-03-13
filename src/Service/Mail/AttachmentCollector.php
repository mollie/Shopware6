<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail;

use Shopware\Core\Framework\Context;

class AttachmentCollector
{
    /**
     * @var iterable<mixed>
     */
    protected $generators;

    /**
     * @param iterable<mixed> $generators
     */
    public function __construct(iterable $generators)
    {
        $this->generators = $generators;
    }

    /**
     * @return array<mixed>
     */
    public function collect(Context $context): array
    {
        $attachments = [];

        foreach ($this->generators as $generator) {
            $attachments[] = $generator->generate($context);
        }

        return $attachments;
    }
}
