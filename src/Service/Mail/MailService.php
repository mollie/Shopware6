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

        $mail = $this->mailFactory->create(
            $data['subject'],
            $this->getNoReplyAddress($data),
            $this->getRecipients($data['recipientLocale'] ?? null),
            $this->buildContents($data),
            $this->filterFileAttachments($attachments),
            [], // Additional data, but doesn't work properly.
            $this->filterBinaryAttachments($attachments)
        );

        $mail->addReplyTo(...$this->formatMailAddresses([$data['replyToEmail'] => $data['replyToName']]));
        $mail->returnPath(...$this->formatMailAddresses([$data['replyToEmail'] => $data['replyToName']]));

        $this->mailSender->send($mail);
    }

    /**
     * Copied from MailFactory
     * @param array $addresses
     * @return array
     */
    private function formatMailAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $mail => $name) {
            $formattedAddresses[] = $name . ' <' . $mail . '>';
        }

        return $formattedAddresses;
    }
}
