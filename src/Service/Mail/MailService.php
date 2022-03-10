<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Validation\DataValidator;

class MailService extends AbstractMailService
{
    /**
     * @var DataValidator
     */
    private $dataValidator;

    /**
     * @var AbstractMailFactory
     */
    private $mailFactory;

    /**
     * @var AbstractMailSender
     */
    private $mailSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DataValidator       $dataValidator,
        AbstractMailFactory $mailFactory,
        AbstractMailSender  $mailSender,
        LoggerInterface     $logger
    )
    {
        $this->dataValidator = $dataValidator;
        $this->mailFactory = $mailFactory;
        $this->mailSender = $mailSender;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     * @throws MailTransportFailedException
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

        $mail = $this->mailFactory->create(
            $data['subject'],
            [$data['senderEmail'] => $data['senderName']],
            self::RECIPIENTS,
            $contents,
            $fileAttachments,
            [],
            $binAttachments
        );

        $this->mailSender->send($mail);
    }
}
