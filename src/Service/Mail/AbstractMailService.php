<?php

namespace Kiener\MolliePayments\Service\Mail;

use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

abstract class AbstractMailService
{
    private const RECIPIENTS = [
        // locale => recipients
        'de-DE' => [
            // email => name
            'meinsupport@mollie.com' => 'Mollie Support DE',
        ]
    ];

    private const INT_RECIPIENTS = [
        // email => name
        'info@mollie.com' => 'Mollie Support',
    ];

    /**
     * @param array<string, mixed> $data
     * @param array<array<string, mixed>> $attachments
     * @throws ConstraintViolationException
     * @return void
     */
    abstract public function send(array $data, array $attachments = []): void;

    /**
     * @param array<mixed> $data
     * @return string[]
     */
    protected function getNoReplyAddress(array $data): array
    {
        if (!array_key_exists('noReplyHost', $data)) {
            $request = Request::createFromGlobals();
            $data['noReplyHost'] = $request->getHost();
        }

        $address = 'no-reply@' . $data['noReplyHost'];

        return [$address => $address];
    }

    /**
     * @param null|string $locale
     * @return string[]
     */
    protected function getRecipients(?string $locale = null): array
    {
        if (!empty($locale) && array_key_exists($locale, self::RECIPIENTS)) {
            return self::RECIPIENTS[$locale];
        }

        return self::INT_RECIPIENTS;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    protected function buildContents(array $data): array
    {
        $plain = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\r\n", $data['contentHtml']));

        return [
            'text/html' => '<div style="font-family:arial; font-size:12px;">' . $data['contentHtml'] . '</div>',
            'text/plain' => $plain,
        ];
    }

    /**
     * @param array<mixed> $attachments
     * @return array<mixed>
     * @deprecated
     */
    protected function filterFileAttachments(array $attachments = []): array
    {
        return array_filter($attachments, function ($attachment) {
            return is_string($attachment)
                && file_exists($attachment);
        });
    }

    /**
     * @param array<mixed> $attachments
     * @return array<mixed>
     */
    protected function filterBinaryAttachments(array $attachments = []): array
    {
        return array_filter($attachments, function ($attachment) {
            return is_array($attachment)
                && array_key_exists('content', $attachment)
                && array_key_exists('fileName', $attachment)
                && array_key_exists('mimeType', $attachment);
        });
    }

    /**
     * @return DataValidationDefinition
     */
    protected function getValidationDefinition(): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('mail_service.send');

        $definition->add('contentHtml', new NotBlank());
        $definition->add('subject', new NotBlank());
        $definition->add('replyToName', new NotBlank());
        $definition->add('replyToEmail', new NotBlank(), new Email());

        return $definition;
    }
}
