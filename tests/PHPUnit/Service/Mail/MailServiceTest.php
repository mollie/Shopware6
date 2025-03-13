<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Mail;

use Kiener\MolliePayments\Service\Mail\MailService;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailServiceTest extends TestCase
{
    private const RECIPIENT_DE = 'Mollie Support DE <meinsupport@mollie.com>';
    private const RECIPIENT_INTL = 'Mollie Support <info@mollie.com>';

    /**
     * @var MailFactory
     */
    private $mailFactory;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DataValidator|(DataValidator&\PHPUnit\Framework\MockObject\MockObject)
     */
    private $dataValidator;

    public function setUp(): void
    {
        $this->dataValidator = $this->createMock(DataValidator::class);

        $validator = $this->createConfiguredMock(ValidatorInterface::class, [
            'validate' => (new ConstraintViolationList()),
        ]);

        $fileSystem = $this->createConfiguredMock(FilesystemInterface::class, [
            'getMimetype' => 'application/fake',
        ]);

        $fileSystem->method('read')->willReturnCallback(function ($url) {
            return sprintf('Fake body for url %s', $url);
        });

        $this->mailFactory = new MailFactory($validator, $fileSystem);
    }

    /**
     * This test verifies that we send the correct mail data
     * to the mail server.
     *
     * @dataProvider getMailData
     *
     * @param $expectedData
     * @param $mailData
     * @param $attachments
     *
     * @return void
     */
    public function testMailSenderGetsCorrectData($expectedData, $mailData, $attachments)
    {
        $expectedMail = $this->buildExpectedMailObject(
            $mailData,
            $attachments,
            $expectedData
        );

        $mailSenderMock = $this->createMock(MailSender::class);

        $mailSenderMock->method('send')->willReturnCallback(function (Email $actualMail) use ($expectedMail) {
            $this->assertEquals($expectedMail->getSubject(), $actualMail->getSubject(), 'subject is wrong');
            $this->assertEquals($expectedMail->getTo(), $actualMail->getTo(), 'to-email is wrong');
            $this->assertEquals($expectedMail->getFrom(), $actualMail->getFrom(), 'from-email is wrong');
            $this->assertEquals($expectedMail->getReturnPath(), $actualMail->getReturnPath(), 'return path is wrong');
            $this->assertEquals($expectedMail->getReplyTo(), $actualMail->getReplyTo(), 'reply-to is wrong');
            $this->assertEquals($expectedMail->getHtmlBody(), $actualMail->getHtmlBody(), 'html body is wrong');
            $this->assertEquals($expectedMail->getTextBody(), $actualMail->getTextBody(), 'text body is wrong');

            $index = 0;

            foreach ($expectedMail->getAttachments() as $expectedAttachment) {
                $actualAttachment = $actualMail->getAttachments()[$index] ?? null;

                $this->assertInstanceOf(DataPart::class, $actualAttachment, 'No attachment found in actual mail');
                $this->assertEquals($expectedAttachment->getBody(), $actualAttachment->getBody(), 'attachment body is wrong');

                ++$index;
            }
        });

        $mailService = new MailService(
            $this->dataValidator,
            $this->mailFactory,
            $mailSenderMock
        );

        $mailService->send($mailData, $attachments);
    }

    public function getMailData(): array
    {
        return [
            '1. German support, no attachments' => [
                [
                    'expectedTo' => self::RECIPIENT_DE,
                ],
                $this->buildMailArrayData(
                    'Help needed',
                    'localhost',
                    'de-DE',
                    'Hello world',
                    'Max Mustermann',
                    'maxmustermann@localhost'
                ),
                [],
            ],
            '2. German support, binary attachment' => [
                [
                    'expectedTo' => self::RECIPIENT_DE,
                ],
                $this->buildMailArrayData(
                    'Help needed',
                    'localhost',
                    'de-DE',
                    'Hello world',
                    'Max Mustermann',
                    'maxmustermann@localhost'
                ),
                [
                    [
                        'content' => 'foo',
                        'fileName' => 'bar.txt',
                        'mimeType' => 'text/plain',
                    ],
                ],
            ],
            '4. International support, no attachments' => [
                [
                    'expectedTo' => self::RECIPIENT_INTL,
                ],
                $this->buildMailArrayData(
                    'Help needed',
                    'localhost',
                    'en-GB',
                    'Hello world',
                    'Max Mustermann',
                    'maxmustermann@localhost'
                ),
                [],
            ],
            '5. International support without passing locale, no attachments' => [
                [
                    'expectedTo' => self::RECIPIENT_INTL,
                ],
                $this->buildMailArrayData(
                    'Help needed',
                    'localhost',
                    '',
                    'Hello world',
                    'Max Mustermann',
                    'maxmustermann@localhost'
                ),
                [],
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function buildMailArrayData(string $subject, string $host, string $locale, string $html, string $replyName, string $replyMail)
    {
        return [
            'subject' => $subject,
            'noReplyHost' => $host,
            'recipientLocale' => $locale,
            'contentHtml' => $html,
            'replyToName' => $replyName,
            'replyToEmail' => $replyMail,
        ];
    }

    /**
     * @return Email
     */
    private function buildExpectedMailObject(array $data, array $attachments, array $expectedData)
    {
        $email = new Email();

        $email->subject($data['subject']);
        $email->to($expectedData['expectedTo']);

        $email->from(sprintf('no-reply@%s <no-reply@%s>', $data['noReplyHost'], $data['noReplyHost']));
        $email->returnPath(sprintf('%s <%s>', $data['replyToName'], $data['replyToEmail']));
        $email->replyTo(sprintf('%s <%s>', $data['replyToName'], $data['replyToEmail']));

        $html = sprintf('<div style="font-family:arial; font-size:12px;">%s</div>', $data['contentHtml']);
        $text = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\r\n", $html));

        $email->html($html);
        $email->text($text);

        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                // embed our file if we have a filename
                // TODO Daniel: This has changed in 6.4.20ish, file attachments work differently. Probably disallow adding filepath attachments and redo removed test.
                //$email->embedFromPath($attachment, basename($attachment), 'application/fake');
                continue;
            }

            $email->embed($attachment['content'], $attachment['fileName'], $attachment['mimeType']);
        }

        return $email;
    }
}
