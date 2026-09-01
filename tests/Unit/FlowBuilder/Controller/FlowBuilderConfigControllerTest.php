<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Controller;

use Mollie\Shopware\Component\FlowBuilder\Controller\FlowBuilderConfigController;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\FlowBuilder\Fake\FakeSnippetFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * The admin asks this route before it lets a merchant add the "ship order" flow action. When a
 * sales channel already ships automatically, the flow would ship a second time - hence the warning.
 */
#[CoversClass(FlowBuilderConfigController::class)]
final class FlowBuilderConfigControllerTest extends TestCase
{
    private const WARNING = 'Automatic shipping is already switched on for this sales channel.';

    private const SNIPPETS = [
        'mollie-payments' => [
            'sw-flow' => [
                'actions' => [
                    'warnings' => [
                        'automaticShipping' => self::WARNING,
                    ],
                ],
            ],
        ],
    ];

    public function testAMerchantIsWarnedWhenASalesChannelAlreadyShipsAutomatically(): void
    {
        $body = $this->validate(automaticShipment: true);

        $this->assertSame([self::WARNING], $body['actions']['shipping']['warnings']);
    }

    public function testThereIsNoWarningWhenNoSalesChannelShipsAutomatically(): void
    {
        $body = $this->validate(automaticShipment: false);

        $this->assertSame([], $body['actions']['shipping']['warnings']);
    }

    /**
     * A shop without a single sales channel has nothing that could ship twice.
     */
    public function testThereIsNoWarningWithoutAnySalesChannel(): void
    {
        $body = $this->validate(automaticShipment: true, salesChannelIds: []);

        $this->assertSame([], $body['actions']['shipping']['warnings']);
    }

    public function testTheWarningIsReadInTheAdminUsersLanguage(): void
    {
        $snippetFinder = new FakeSnippetFinder(self::SNIPPETS);

        $this->validate(automaticShipment: true, locale: 'de-DE', snippetFinder: $snippetFinder);

        $this->assertSame(['de-DE'], $snippetFinder->getRequestedLocales());
    }

    public function testTheRequestedLocaleIsReportedBack(): void
    {
        $body = $this->validate(automaticShipment: false, locale: 'nl-NL');

        $this->assertSame('nl-NL', $body['locale']);
    }

    /**
     * The admin does not always send a locale; English is what the snippet files always have.
     */
    public function testARequestWithoutALocaleFallsBackToEnglish(): void
    {
        $body = $this->validate(automaticShipment: false, locale: '');

        $this->assertSame('en-GB', $body['locale']);
    }

    /**
     * @param list<string> $salesChannelIds
     *
     * @return array<string, mixed>
     */
    private function validate(
        bool $automaticShipment,
        string $locale = 'en-GB',
        array $salesChannelIds = ['sales-channel-1', 'sales-channel-2'],
        ?FakeSnippetFinder $snippetFinder = null,
    ): array {
        $salesChannelRepository = new FakeSalesChannelRepository();
        foreach ($salesChannelIds as $salesChannelId) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId($salesChannelId);
            $salesChannelRepository->add($salesChannel);
        }

        $controller = new FlowBuilderConfigController(
            $salesChannelRepository,
            new FakeSettingsService(paymentSettings: PaymentSettings::createFromShopwareArray([
                PaymentSettings::KEY_AUTOMATIC_SHIPMENT => $automaticShipment,
            ])),
            $snippetFinder ?? new FakeSnippetFinder(self::SNIPPETS)
        );

        $response = $controller->validateFlowBuilder(new Request([], ['locale' => $locale]), Context::createDefaultContext());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
