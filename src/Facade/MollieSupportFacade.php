<?php

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\Mail\MailService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Mime\Email;

class MollieSupportFacade
{
    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        MailService     $mailService,
        LoggerInterface $logger
    )
    {
        $this->mailService = $mailService;
        $this->logger = $logger;
    }

    public function request(
        string  $senderName,
        string  $senderEmail,
        string  $subject,
        string  $contentHtml,
        Context $context
    ): ?Email
    {
        $data = compact('senderName', 'senderEmail', 'subject', 'contentHtml');

        return $this->mailService->send($data);
    }
}
