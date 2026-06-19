<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support;

use Mollie\Shopware\Component\Support\Attachment\AttachmentCollection;
use Mollie\Shopware\Component\Support\Attachment\Generator\AttachmentGeneratorInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class SupportMailer implements SupportMailerInterface
{
    private const RECIPIENTS = [
        'de-DE' => ['meinsupport@mollie.com' => 'Mollie Support DE'],
    ];

    private const INT_RECIPIENTS = ['info@mollie.com' => 'Mollie Support'];

    /**
     * @param iterable<AttachmentGeneratorInterface> $attachmentGenerators
     */
    public function __construct(
        private readonly AbstractMailFactory $mailFactory,
        private readonly AbstractMailSender $mailSender,
        #[AutowireIterator('mollie.support.attachment_generator')]
        private readonly iterable $attachmentGenerators,
    ) {
    }

    public function send(
        string $name,
        string $email,
        ?string $recipientLocale,
        string $host,
        string $subject,
        string $message,
        Context $context
    ): void {
        $subject = 'Support Shopware 6: ' . $subject;

        $contentHtml = 'Name: ' . $name . '<br />';
        $contentHtml .= 'E-Mail: ' . $email . '<br />';
        $contentHtml .= '<br />';
        $contentHtml .= '<br />';
        $contentHtml .= $message;

        $plain = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\r\n", $contentHtml));

        $noReplyAddress = 'no-reply@' . $host;

        $collection = new AttachmentCollection();
        foreach ($this->attachmentGenerators as $generator) {
            $collection->add($generator->generate($context));
        }

        $mail = $this->mailFactory->create(
            $subject,
            [$noReplyAddress => $noReplyAddress],
            $this->getRecipients($recipientLocale),
            [
                'text/html' => '<div style="font-family:arial; font-size:12px;">' . $contentHtml . '</div>',
                'text/plain' => $plain,
            ],
            [],
            [],
            $collection->toArray()
        );

        $replyTo = $name . ' <' . $email . '>';
        $mail->addReplyTo($replyTo);
        $mail->returnPath($replyTo);

        $this->mailSender->send($mail);
    }

    /**
     * @return array<string, string>
     */
    private function getRecipients(?string $locale): array
    {
        if ($locale !== null && array_key_exists($locale, self::RECIPIENTS)) {
            return self::RECIPIENTS[$locale];
        }

        return self::INT_RECIPIENTS;
    }
}
