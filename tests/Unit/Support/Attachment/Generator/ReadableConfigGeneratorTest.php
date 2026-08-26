<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Attachment\Generator;

use GuzzleHttp\Client;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Support\Attachment\Generator\ReadableConfigGenerator;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * The human readable half of the support attachment. It says whether the keys work, which is the
 * first thing support checks - but it must never print the keys themselves.
 */
#[CoversClass(ReadableConfigGenerator::class)]
final class ReadableConfigGeneratorTest extends TestCase
{
    public function testNeitherApiKeyIsPrinted(): void
    {
        $content = $this->generate();

        $this->assertStringNotContainsString('test_the_secret_key', $content);
        $this->assertStringNotContainsString('live_the_secret_key', $content);
    }

    /**
     * A key Mollie accepts is the difference between "the plugin is misconfigured" and "there is a
     * real bug", so support gets the verdict, not the key.
     */
    public function testAKeyMollieAcceptsIsReportedAsValid(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('Live API Key: Valid', $content);
        $this->assertStringContainsString('Test API Key: Valid', $content);
    }

    public function testAKeyMollieRefusesIsReportedAsInvalid(): void
    {
        // A client without a body answers 500 and raises a ClientException, like a rejected key.
        $content = $this->generate(client: new FakeClient());

        $this->assertStringContainsString('Live API Key: Invalid', $content);
        $this->assertStringContainsString('Test API Key: Invalid', $content);
    }

    /**
     * A key that was never entered is not invalid - saying so would send support hunting for a
     * wrong key instead of a missing one.
     */
    public function testAKeyThatWasNeverEnteredIsReportedAsEmpty(): void
    {
        $content = $this->generate(apiSettings: new ApiSettings('', '', Mode::TEST, ''));

        $this->assertStringContainsString('Live API Key: Empty', $content);
        $this->assertStringContainsString('Test API Key: Empty', $content);
        $this->assertStringContainsString('Profile ID: Empty', $content);
    }

    public function testTheModeTheShopRunsInIsReported(): void
    {
        $this->assertStringContainsString('Mode: Test', $this->generate());
        $this->assertStringContainsString('Mode: Live', $this->generate(apiSettings: new ApiSettings('k', 'k', Mode::LIVE, 'pfl_1')));
    }

    public function testTheProfileTheShopTalksToIsReported(): void
    {
        $this->assertStringContainsString('Profile ID: pfl_1', $this->generate());
    }

    /**
     * Support reads this by eye, so a switch has to say Enabled/Disabled instead of 1/0.
     */
    public function testASwitchIsPrintedAsEnabledOrDisabled(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('automaticShipment: Enabled', $content);
        $this->assertStringContainsString('automaticCancellation: Disabled', $content);
    }

    public function testAnUnsetTextSettingIsPrintedAsEmpty(): void
    {
        $this->assertStringContainsString('fixRoundingDiffSku: Empty', $this->generate());
    }

    public function testANumberIsPrintedAsItsValue(): void
    {
        $this->assertStringContainsString('dueDateDays: 14', $this->generate());
    }

    public function testTheGlobalConfigAndEverySalesChannelAreReported(): void
    {
        $content = $this->generate(salesChannelNames: ['Storefront', 'Headless']);

        $this->assertStringContainsString('[ Global ]', $content);
        $this->assertStringContainsString('[ Storefront ]', $content);
        $this->assertStringContainsString('[ Headless ]', $content);
    }

    public function testTheAttachmentIsSentAsAPlainTextFile(): void
    {
        $attachment = $this->generator()->generate(Context::createDefaultContext());

        $this->assertSame('plugin_configuration.txt', $attachment->fileName);
        $this->assertSame('text/plain', $attachment->mimeType);
    }

    /**
     * @param list<string> $salesChannelNames
     */
    private function generate(
        ?ApiSettings $apiSettings = null,
        ?Client $client = null,
        array $salesChannelNames = ['Storefront'],
    ): string {
        return $this->generator($apiSettings, $client, $salesChannelNames)->generate(Context::createDefaultContext())->content;
    }

    /**
     * @param list<string> $salesChannelNames
     */
    private function generator(
        ?ApiSettings $apiSettings = null,
        ?Client $client = null,
        array $salesChannelNames = ['Storefront'],
    ): ReadableConfigGenerator {
        $settings = new FakeSettingsService(
            apiSettings: $apiSettings ?? new ApiSettings('test_the_secret_key', 'live_the_secret_key', Mode::TEST, 'pfl_1'),
            paymentSettings: PaymentSettings::createFromShopwareArray([
                PaymentSettings::KEY_AUTOMATIC_SHIPMENT => true,
                PaymentSettings::KEY_DUE_DATE_DAYS => 14,
            ]),
        );

        $repository = new FakeSalesChannelRepository();
        foreach ($salesChannelNames as $index => $name) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId('sales-channel-' . $index);
            $salesChannel->setName($name);
            $salesChannel->setTranslated(['name' => $name]);
            $repository->add($salesChannel);
        }

        return new ReadableConfigGenerator(
            $repository,
            $settings,
            new FakeClientFactory($client ?? new FakeClient(body: ['id' => 'pfl_1'])),
            new FakeLogger()
        );
    }
}
