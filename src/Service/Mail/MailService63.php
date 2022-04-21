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

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DataValidator   $dataValidator,
        MessageFactory  $mailFactory, // @phpstan-ignore-line
        MailSender      $mailSender, // @phpstan-ignore-line
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

        $mail = $this->mailFactory->createMessage(
            $data['subject'],
            $this->getNoReplyAddress($data),
            $this->getRecipients($data['recipientLocale'] ?? null),
            $this->buildContents($data),
            $this->filterFileAttachments($attachments),
            $this->filterBinaryAttachments($attachments)
        );

        $mail->addReplyTo($data['replyToEmail'], $data['replyToName']);
        $mail->setReturnPath($data['replyToEmail']);

        $this->mailSender->send($mail);
    }
}
