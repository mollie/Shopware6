<?php

namespace Kiener\MolliePayments\Service\Mail;

use Kiener\MolliePayments\Service\Mail\AttachmentGenerator\GeneratorInterface;
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
     * @param Context $context
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
