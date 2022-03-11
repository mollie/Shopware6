<?php

namespace Kiener\MolliePayments\Service\Mail;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Framework\Validation\DataValidator;

class MailService63 extends AbstractMailService
{
    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var MessageFactory
     */
    private $mailFactory;

    /**
     * @var MailSender
     */
    private $mailSender;


    public function __construct(
        DataValidator   $dataValidator,
        MessageFactory  $mailFactory,
        MailSender      $mailSender,
        LoggerInterface $logger)
    {
        $this->dataValidator = $dataValidator;
        $this->mailFactory = $mailFactory;
        $this->mailSender = $mailSender;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function send(array $data, array $attachments = []): void
    {
        $definition = $this->getValidationDefinition();
        $this->dataValidator->validate($data, $definition);

        $contents = $this->buildContents($data);

        $fileAttachments = [];
        $binAttachments = [];

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_string($attachment)) {
                    $fileAttachments[] = $attachment;
                } else {
                    $binAttachments[] = $attachment;
                }
            }
        }

        $mail = $this->mailFactory->createMessage(
            $data['subject'],
            [$data['senderEmail'] => $data['senderName']],
            self::RECIPIENTS,
            $contents,
            $fileAttachments,
            $binAttachments
        );

        $mail->addReplyTo($data['replyToEmail'], $data['replyToName']);
        $mail->setReturnPath($data['replyToEmail']);

        $this->mailSender->send($mail);
    }
}
