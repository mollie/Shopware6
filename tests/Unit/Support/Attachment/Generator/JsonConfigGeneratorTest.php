<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Attachment\Generator;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Support\Attachment\Generator\JsonConfigGenerator;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * The attachment travels to Mollie support by mail, so the API keys must never be in it - a leaked
 * live key can charge the merchant's customers.
 */
#[CoversClass(JsonConfigGenerator::class)]
final class JsonConfigGeneratorTest extends TestCase
{
    public function testTheApiKeysAreRedacted(): void
    {
        $configs = $this->generate();

        $this->assertSame('(hidden)', $configs[0]['config']['api']['testApiKey']);
        $this->assertSame('(hidden)', $configs[0]['config']['api']['liveApiKey']);
    }

    public function testNeitherKeyAppearsAnywhereInTheAttachment(): void
    {
        $content = $this->attachmentContent();

        $this->assertStringNotContainsString('test_the_secret_key', $content);
        $this->assertStringNotContainsString('live_the_secret_key', $content);
    }

    /**
     * Support needs to know which mode the shop runs in and which profile it talks to, so that has
     * to survive the redaction. The keys are the property names the structs serialize under.
     */
    public function testTheModeAndProfileStayReadable(): void
    {
        $configs = $this->generate();

        $this->assertSame(Mode::TEST->value, $configs[0]['config']['api']['mode']);
        $this->assertSame('pfl_1', $configs[0]['config']['api']['profileId']);
    }

    /**
     * Most settings are per sales channel, so support needs the global block and one per channel.
     */
    public function testTheGlobalConfigAndEverySalesChannelAreReported(): void
    {
        $configs = $this->generate(['Storefront', 'Headless']);

        $this->assertSame(['Global', 'Storefront', 'Headless'], array_column($configs, 'label'));
    }

    public function testAShopWithoutSalesChannelsStillReportsItsGlobalConfig(): void
    {
        $configs = $this->generate([]);

        $this->assertSame(['Global'], array_column($configs, 'label'));
    }

    public function testEverySettingsSectionIsInTheAttachment(): void
    {
        $configs = $this->generate();

        $this->assertSame(
            ['api', 'payment', 'logger', 'creditCard', 'applePay', 'payPalExpress', 'account', 'orderState', 'refund', 'subscription'],
            array_keys($configs[0]['config'])
        );
    }

    public function testThePaymentSettingsAreReported(): void
    {
        $configs = $this->generate();

        $this->assertTrue($configs[0]['config']['payment']['automaticShipment']);
    }

    public function testTheAttachmentIsSentAsAJsonFile(): void
    {
        $attachment = $this->generator()->generate(Context::createDefaultContext());

        $this->assertSame('plugin_configuration.json', $attachment->fileName);
        $this->assertSame('application/json', $attachment->mimeType);
    }

    /**
     * @param list<string> $salesChannelNames
     *
     * @return array<int, array<string, mixed>>
     */
    private function generate(array $salesChannelNames = ['Storefront']): array
    {
        return json_decode($this->attachmentContent($salesChannelNames), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $salesChannelNames
     */
    private function attachmentContent(array $salesChannelNames = ['Storefront']): string
    {
        return $this->generator($salesChannelNames)->generate(Context::createDefaultContext())->content;
    }

    /**
     * @param list<string> $salesChannelNames
     */
    private function generator(array $salesChannelNames = ['Storefront']): JsonConfigGenerator
    {
        $settings = new FakeSettingsService(
            apiSettings: new ApiSettings('test_the_secret_key', 'live_the_secret_key', Mode::TEST, 'pfl_1'),
            paymentSettings: PaymentSettings::createFromShopwareArray([PaymentSettings::KEY_AUTOMATIC_SHIPMENT => true]),
        );

        return new JsonConfigGenerator($this->salesChannelRepository($salesChannelNames), $settings, new FakeLogger());
    }

    /**
     * @param list<string> $names
     */
    private function salesChannelRepository(array $names): FakeSalesChannelRepository
    {
        $repository = new FakeSalesChannelRepository();

        foreach ($names as $index => $name) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId('sales-channel-' . $index);
            $salesChannel->setName($name);
            $salesChannel->setTranslated(['name' => $name]);
            $repository->add($salesChannel);
        }

        return $repository;
    }
}
