<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\Mail;

use Kiener\MolliePayments\Service\Mail\MailService;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailServiceTest extends TestCase
{
    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var Email
     */
    protected $email;

    public function setUp(): void
    {
        $dataValidator = $this->createMock(DataValidator::class);

        $validator = $this->createConfiguredMock(ValidatorInterface::class, [
            'validate' => (new ConstraintViolationList())
        ]);

        $fileSystem = $this->createConfiguredMock(FilesystemInterface::class, [
            'getMimetype' => 'application/fake'
        ]);
        $fileSystem->method('read')->willReturnCallback(function ($url) {
            return sprintf('Fake body for url %s', $url);
        });

        $mailFactory = new MailFactory($validator, $fileSystem);

        $mailSender = $this->createMock(MailSender::class);
        $mailSender->method('send')->willReturnCallback(function (Email $email) {
            $this->assertEquals($this->email->getSubject(), $email->getSubject());
            $this->assertEquals($this->email->getTo(), $email->getTo());
            $this->assertEquals($this->email->getFrom(), $email->getFrom());
            $this->assertEquals($this->email->getReturnPath(), $email->getReturnPath());
            $this->assertEquals($this->email->getReplyTo(), $email->getReplyTo());
            $this->assertEquals($this->email->getHtmlBody(), $email->getHtmlBody());
            $this->assertEquals($this->email->getTextBody(), $email->getTextBody());

            foreach($this->email->getAttachments() as $index => $expectedAttachment) {
                $actualAttachment = $email->getAttachments()[$index] ?? null;

                $this->assertInstanceOf(DataPart::class, $actualAttachment);
                $this->assertEquals($expectedAttachment->getBody(), $actualAttachment->getBody());
            }
        });

        $this->mailService = new MailService(
            $dataValidator,
            $mailFactory,
            $mailSender,
            new NullLogger()
        );
    }

    /**
     * @param $data
     * @param $attachments
     * @return void
     * @dataProvider mailData
     */
    public function testSendingMail($data, $attachments = [])
    {
        $this->prepareExpectedEmail($data, $attachments);
        $this->mailService->send($data, $attachments);
    }

    /**
     * Mimic the mailfactory
     * @param $data
     * @param $attachments
     * @return void
     */
    private function prepareExpectedEmail($data, $attachments)
    {
        $this->email = new Email();

        if (isset($data['subject'])) {
            $this->email->subject($data['subject']);
        }

        if (isset($data['recipientLocale']) && $data['recipientLocale'] === 'de-DE') {
            $this->email->to('Mollie Support DE <meinsupport@mollie.com>');
        } else {
            $this->email->to('Mollie Support <info@mollie.com>');
        }

        if (isset($data['noReplyHost'])) {
            $this->email->from(sprintf('no-reply@%s <no-reply@%s>', $data['noReplyHost'], $data['noReplyHost']));
        }

        if (isset($data['replyToName']) && isset($data['replyToEmail'])) {
            $this->email->returnPath(sprintf('%s <%s>', $data['replyToName'], $data['replyToEmail']));
            $this->email->replyTo(sprintf('%s <%s>', $data['replyToName'], $data['replyToEmail']));
        }

        if (isset($data['contentHtml'])) {
            $html = sprintf('<div style="font-family:arial; font-size:12px;">%s</div>', $data['contentHtml']);
            $text = strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\r\n", $data['contentHtml']));

            $this->email->html($html);
            $this->email->text($text);
        }

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                $this->email->embed(
                    sprintf('Fake body for url %s', $attachment),
                    basename($attachment),
                    'application/fake'
                );
            } else if (is_array($attachment)
                && array_key_exists('content', $attachment)
                && array_key_exists('fileName', $attachment)
                && array_key_exists('mimeType', $attachment)) {
                $this->email->embed($attachment['content'], $attachment['fileName'], $attachment['mimeType']);
            }
        }
    }

    public function mailData()
    {
        return [
            'Mail German support, no attachments' => [
                [
                    'subject' => 'subject',
                    'noReplyHost' => 'localhost',
                    'recipientLocale' => 'de-DE',
                    'contentHtml' => 'Hello world',
                    'replyToName' => 'Max Mustermann',
                    'replyToEmail' => 'maxmustermann@localhost'
                ]
            ],
            'Mail German support, binary attachment' => [
                [
                    'subject' => 'subject',
                    'noReplyHost' => 'localhost',
                    'recipientLocale' => 'de-DE',
                    'contentHtml' => 'Hello world',
                    'replyToName' => 'Max Mustermann',
                    'replyToEmail' => 'maxmustermann@localhost'
                ],
                [
                    [
                        'content' => 'foo',
                        'fileName' => 'bar.txt',
                        'mimeType' => 'text/plain',
                    ]
                ]
            ],
            'Mail German support, url attachment' => [
                [
                    'subject' => 'subject',
                    'noReplyHost' => 'localhost',
                    'recipientLocale' => 'de-DE',
                    'contentHtml' => 'Hello world',
                    'replyToName' => 'Max Mustermann',
                    'replyToEmail' => 'maxmustermann@localhost'
                ],
                [
                    __FILE__ // Test with a real file because we check if the file exists before adding it as attachment
                ]
            ],
            'Mail International support, no attachments' => [
                [
                    'subject' => 'subject',
                    'noReplyHost' => 'localhost',
                    'recipientLocale' => null,
                    'contentHtml' => 'Hello world',
                    'replyToName' => 'Max Mustermann',
                    'replyToEmail' => 'maxmustermann@localhost'
                ]
            ],
            'Mail International support without passing locale, no attachments' => [
                [
                    'subject' => 'subject',
                    'noReplyHost' => 'localhost',
                    'contentHtml' => 'Hello world',
                    'replyToName' => 'Max Mustermann',
                    'replyToEmail' => 'maxmustermann@localhost'
                ]
            ],
        ];
    }
}
