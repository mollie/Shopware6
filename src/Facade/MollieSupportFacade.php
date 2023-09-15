<?php

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\Mail\AbstractMailService;
use Kiener\MolliePayments\Service\Mail\AttachmentCollector;
use Shopware\Core\Framework\Context;

class MollieSupportFacade
{
    /**
     * @var AttachmentCollector
     */
    protected $attachmentCollector;

    /**
     * @var ?AbstractMailService
     */
    protected $mailService;


    /**
     * @param null|AbstractMailService $mailService
     * @param AttachmentCollector $attachmentCollector
     */
    public function __construct(?AbstractMailService $mailService, AttachmentCollector $attachmentCollector)
    {
        $this->attachmentCollector = $attachmentCollector;
        $this->mailService = $mailService;
    }

    /**
     * @param string $replyToName
     * @param string $replyToEmail
     * @param null|string $recipientLocale
     * @param string $noReplyHost
     * @param string $subject
     * @param string $contentHtml
     * @param Context $context
     * @throws \Exception
     * @return void
     */
    public function sendSupportRequest(string $replyToName, string $replyToEmail, ?string $recipientLocale, string $noReplyHost, string $subject, string $contentHtml, Context $context): void
    {
        if ($this->mailService === null) {
            throw new \Exception('Support Mail failed! This Shopware version has no Mail service!');
        }

        # improve the automatic data a bit
        # we add some prefixes, and make sure a few things are
        # always present in the text
        $subject = 'Support Shopware 6: ' . $subject;

        $finalHTML = 'Name: ' . $replyToName . '<br />';
        $finalHTML .= 'E-Mail: ' . $replyToEmail . '<br />';
        $finalHTML .= '<br />';
        $finalHTML .= '<br />';
        $finalHTML .= $contentHtml;


        $data = [
            'replyToName' => $replyToName,
            'replyToEmail' => $replyToEmail,
            'recipientLocale' => $recipientLocale,
            'noReplyHost' => $noReplyHost,
            'subject' => $subject,
            'contentHtml' => $finalHTML,
        ];

        $attachments = $this->attachmentCollector->collect($context);

        $this->mailService->send($data, $attachments);
    }
}
