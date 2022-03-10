<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail;

use Kiener\MolliePayments\Exception\MailBodyEmptyException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailService implements MailServiceInterface
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
     * @param array $data
     * @param array $attachments
     * @throws ConstraintViolationException
     * @throws MailTransportFailedException
     * @return void
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

    private function buildContents(array $data): array
    {
        $plain = strip_tags(str_replace(['<div>', '</div>'], "\r\n", $data['contentHtml']));

        return [
            'text/html' => '<div style="font-family:arial; font-size:12px;">' . $data['contentHtml'] . '</div>',
            'text/plain' => $plain,
        ];
    }

    private function getValidationDefinition(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('contentHtml', new NotBlank());
        $definition->add('subject', new NotBlank());
        $definition->add('senderName', new NotBlank());
        $definition->add('senderEmail', new NotBlank(), new EmailConstraint());

        return $definition;
    }


}
