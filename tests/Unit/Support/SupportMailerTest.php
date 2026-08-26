<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Mollie\Shopware\Component\Support\SupportMailer;
use Mollie\Shopware\Unit\Support\Fake\FakeAttachmentGenerator;
use Mollie\Shopware\Unit\Support\Fake\FakeMailFactory;
use Mollie\Shopware\Unit\Support\Fake\FakeMailSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

/**
 * The support form in the plugin configuration sends the merchant's request straight to Mollie.
 * The address it goes to depends on the admin language, and every attachment generator has to end
 * up in the mail - a missing one costs support a round trip.
 */
#[CoversClass(SupportMailer::class)]
final class SupportMailerTest extends TestCase
{
    public function testAGermanMerchantReachesTheGermanSupportTeam(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', 'de-DE', 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame(['meinsupport@mollie.com' => 'Mollie Support DE'], $factory->getLastCall()['recipients']);
    }

    public function testEveryOtherLanguageReachesTheInternationalTeam(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', 'nl-NL', 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame(['info@mollie.com' => 'Mollie Support'], $factory->getLastCall()['recipients']);
    }

    public function testAMerchantWithoutALanguageReachesTheInternationalTeam(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame(['info@mollie.com' => 'Mollie Support'], $factory->getLastCall()['recipients']);
    }

    /**
     * Mollie support sorts by this prefix, so it has to be there even when the merchant typed
     * their own subject.
     */
    public function testTheSubjectSaysItComesFromShopware(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame('Support Shopware 6: Refunds', $factory->getLastCall()['subject']);
    }

    /**
     * The mail leaves the shop's own domain, so it does not get caught as a spoofed sender.
     */
    public function testTheMailIsSentFromTheShopsOwnNoReplyAddress(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame(['no-reply@shop.test' => 'no-reply@shop.test'], $factory->getLastCall()['sender']);
    }

    public function testTheMerchantsNameAndAddressAreInTheMailBody(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Please help', Context::createDefaultContext());

        $html = $factory->getLastCall()['contents']['text/html'];
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('jane@shop.test', $html);
        $this->assertStringContainsString('Please help', $html);
    }

    /**
     * Not every mail client renders HTML, so the same information goes out as plain text.
     */
    public function testThePlainTextPartCarriesTheSameInformationWithoutMarkup(): void
    {
        $factory = new FakeMailFactory();

        $this->mailer($factory)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Please help', Context::createDefaultContext());

        $plain = $factory->getLastCall()['contents']['text/plain'];
        $this->assertStringContainsString('Jane Doe', $plain);
        $this->assertStringContainsString('Please help', $plain);
        $this->assertStringNotContainsString('<br />', $plain);
    }

    public function testEveryAttachmentGeneratorEndsUpInTheMail(): void
    {
        $factory = new FakeMailFactory();
        $mailer = $this->mailer($factory, [
            new FakeAttachmentGenerator(new Attachment('{}', 'plugin_configuration.json', 'application/json')),
            new FakeAttachmentGenerator(new Attachment('logs', 'mollie_logs.zip', 'application/zip')),
        ]);

        $mailer->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertSame(
            ['plugin_configuration.json', 'mollie_logs.zip'],
            array_column($factory->getLastCall()['binAttachments'], 'fileName')
        );
    }

    public function testTheMailIsHandedToTheMailSender(): void
    {
        $sender = new FakeMailSender();

        $this->mailer(new FakeMailFactory(), sender: $sender)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $this->assertCount(1, $sender->getSent());
    }

    /**
     * Mollie support answers the merchant, not the no-reply address the mail was sent from.
     */
    public function testAnAnswerGoesBackToTheMerchant(): void
    {
        $sender = new FakeMailSender();

        $this->mailer(new FakeMailFactory(), sender: $sender)->send('Jane Doe', 'jane@shop.test', null, 'shop.test', 'Refunds', 'Help', Context::createDefaultContext());

        $replyTo = $sender->getSent()[0]->getReplyTo();
        $this->assertSame('jane@shop.test', $replyTo[0]->getAddress());
        $this->assertSame('Jane Doe', $replyTo[0]->getName());
    }

    /**
     * @param list<FakeAttachmentGenerator> $generators
     */
    private function mailer(FakeMailFactory $factory, array $generators = [], ?FakeMailSender $sender = null): SupportMailer
    {
        return new SupportMailer($factory, $sender ?? new FakeMailSender(), $generators);
    }
}
