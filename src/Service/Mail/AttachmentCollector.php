<?php

namespace Kiener\MolliePayments\Service\Mail;

use Kiener\MolliePayments\Service\Mail\AttachmentGenerator\GeneratorInterface;
use Shopware\Core\Framework\Context;

class AttachmentCollector
{
    /**
     * @var GeneratorInterface[]
     */
    protected $generators;

    public function __construct(iterable $generators)
    {
        $this->generators = $generators;
    }

    public function collect(Context $context)
    {
        $attachments = [];

        foreach ($this->generators as $generator) {
            $attachments[] = $generator->generate($context);
        }

        return $attachments;
    }
}
