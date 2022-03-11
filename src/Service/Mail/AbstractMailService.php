<?php

namespace Kiener\MolliePayments\Service\Mail;

use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

abstract class AbstractMailService
{
    const RECIPIENTS = [
        // email => name
    ];

    /**
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>> $attachments
     * @return void
     * @throws ConstraintViolationException
     */
    public abstract function send(array $data, array $attachments = []): void;

    /**
     * @param array $data
     * @return array
     */
    protected function buildContents(array $data): array
    {
        $plain = strip_tags(str_replace(['<div>', '</div>'], "\r\n", $data['contentHtml']));

        return [
            'text/html' => '<div style="font-family:arial; font-size:12px;">' . $data['contentHtml'] . '</div>',
            'text/plain' => $plain,
        ];
    }

    /**
     * @return DataValidationDefinition
     */
    protected function getValidationDefinition(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('contentHtml', new NotBlank());
        $definition->add('subject', new NotBlank());
        $definition->add('senderName', new NotBlank());
        $definition->add('senderEmail', new NotBlank(), new Email());

        return $definition;
    }
}
