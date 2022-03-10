<?php

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\Mail\AttachmentCollector;
use Kiener\MolliePayments\Service\Mail\AbstractMailService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class MollieSupportFacade
{

    /**
     * @var AttachmentCollector
     */
    protected $attachmentCollector;

    /**
     * @var AbstractMailService
     */
    protected $mailService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        AbstractMailService $mailService,
        AttachmentCollector $attachmentCollector,
        LoggerInterface     $logger
    )
    {
        $this->attachmentCollector = $attachmentCollector;
        $this->mailService = $mailService;
        $this->logger = $logger;
    }

    public function request(
        string  $senderName,
        string  $senderEmail,
        string  $subject,
        string  $contentHtml,
        Context $context
    ): void
    {
        $data = compact('senderName', 'senderEmail', 'subject', 'contentHtml');
        $attachments = $this->attachmentCollector->collect($context);

        $this->mailService->send($data, $attachments);
    }
}
