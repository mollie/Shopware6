<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Mail;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailService
{
    const RECIPIENTS = [
    ];

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
        AbstractMailSender  $emailSender,
        LoggerInterface     $logger
    )
    {
        $this->dataValidator = $dataValidator;
        $this->mailFactory = $mailFactory;
        $this->mailSender = $emailSender;
        $this->logger = $logger;
    }

    public function send(array $data, ?array $attachments = null): ?Email
    {
        $definition = $this->getValidationDefinition();
        $this->dataValidator->validate($data, $definition);

        $contents = $this->buildContents($data);

        $mail = $this->mailFactory->create(
            $data['subject'],
            [$data['senderEmail'] => $data['senderName']],
            self::RECIPIENTS,
            $contents,
            [],
            [],
            $attachments
        );

        if ($mail->getBody()->toString() === '') {
            $this->logger->error(
                "message is null:\n"
                . 'Data:'
                . json_encode($data) . "\n"
            );

            return null;
        }

        $this->mailSender->send($mail);
        return $mail;
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
