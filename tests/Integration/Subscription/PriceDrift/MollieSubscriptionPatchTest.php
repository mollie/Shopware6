<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Subscription\PriceDrift;

use GuzzleHttp\Client;
use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Gateway\ClientFactory;
use Mollie\Shopware\Component\Mollie\Gateway\ClientFactoryInterface;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\TestCase;

/**
 * Validates that Mollie's PATCH /subscriptions endpoint accepts an `amount` change
 * and returns the new value on a subsequent GET. If this test fails, the
 * `PriceMigrationHandler` needs a cancel + recreate fallback path.
 *
 * Uses the real SalesChannel and its configured API key (the same one the
 * production gateway uses). Skipped when no key is configured.
 */
final class MollieSubscriptionPatchTest extends TestCase
{
    use ShopwareTestBehaviour;
    use SalesChannelTestBehaviour;

    private const TEST_IBAN = 'NL55INGB0000000000';

    private SubscriptionGatewayInterface $gateway;

    private Client $rawClient;

    private string $salesChannelId = '';

    private string $mollieCustomerId = '';

    private string $mollieMandateId = '';

    private string $mollieSubscriptionId = '';

    protected function setUp(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $settingsService = $this->getContainer()->get(SettingsService::class);
        if (! $settingsService instanceof SettingsService) {
            $this->markTestSkipped('SettingsService not available in container.');
        }

        $apiSettings = $settingsService->getApiSettings($this->salesChannelId);
        if (! $apiSettings instanceof ApiSettings || $apiSettings->getApiKey() === '') {
            $this->markTestSkipped('No Mollie API key configured for the default SalesChannel.');
        }

        $clientFactory = $this->getContainer()->get(ClientFactory::class);
        if (! $clientFactory instanceof ClientFactoryInterface) {
            $this->markTestSkipped('ClientFactory not available in container.');
        }

        $gateway = $this->getContainer()->get(SubscriptionGateway::class);
        if (! $gateway instanceof SubscriptionGatewayInterface) {
            $this->markTestSkipped('SubscriptionGateway not available in container.');
        }

        $this->gateway = $gateway;
        $this->rawClient = $clientFactory->create($this->salesChannelId);
    }

    protected function tearDown(): void
    {
        if ($this->mollieSubscriptionId !== '' && $this->mollieCustomerId !== '') {
            try {
                $this->rawClient->delete(sprintf('customers/%s/subscriptions/%s', $this->mollieCustomerId, $this->mollieSubscriptionId));
            } catch (\Throwable) {
            }
        }
        if ($this->mollieMandateId !== '' && $this->mollieCustomerId !== '') {
            try {
                $this->rawClient->delete(sprintf('customers/%s/mandates/%s', $this->mollieCustomerId, $this->mollieMandateId));
            } catch (\Throwable) {
            }
        }
    }

    public function testPatchAmountIsAcceptedAndReflectedOnReload(): void
    {
        $this->mollieCustomerId = $this->createCustomer();
        $this->mollieMandateId = $this->createDirectDebitMandate($this->mollieCustomerId);

        $created = $this->gateway->createSubscription(
            $this->buildCreateSubscription(amount: 10.00, mandateId: $this->mollieMandateId),
            $this->mollieCustomerId,
            'integration-test',
            $this->salesChannelId
        );
        $this->mollieSubscriptionId = $created->getId();

        $this->assertSame('10.00', $created->getAmount()->getValue());

        $loaded = $this->gateway->getSubscription(
            $this->mollieSubscriptionId,
            $this->mollieCustomerId,
            'integration-test',
            $this->salesChannelId
        );
        $this->assertSame('10.00', $loaded->getAmount()->getValue(), 'Initial amount should match the value used at creation.');

        $loaded->setAmount(new Money(20.00, $loaded->getAmount()->getCurrency()));
        $patched = $this->gateway->updateSubscription(
            $loaded,
            $this->mollieCustomerId,
            'integration-test',
            $this->salesChannelId
        );
        $this->assertSame('20.00', $patched->getAmount()->getValue(), 'Mollie should return the patched amount in the PATCH response.');

        $reloaded = $this->gateway->getSubscription(
            $this->mollieSubscriptionId,
            $this->mollieCustomerId,
            'integration-test',
            $this->salesChannelId
        );
        $this->assertSame('20.00', $reloaded->getAmount()->getValue(), 'Reloaded subscription should reflect the patched amount.');
    }

    private function createCustomer(): string
    {
        $response = $this->rawClient->post('customers', [
            'form_params' => [
                'name' => 'Integration Test Customer',
                'email' => 'integration-test-' . bin2hex(random_bytes(4)) . '@example.com',
            ],
        ]);
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('id', $body);

        return (string) $body['id'];
    }

    private function createDirectDebitMandate(string $customerId): string
    {
        $response = $this->rawClient->post(sprintf('customers/%s/mandates', $customerId), [
            'form_params' => [
                'method' => 'directdebit',
                'consumerName' => 'Integration Test Holder',
                'consumerAccount' => self::TEST_IBAN,
            ],
        ]);
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('id', $body);

        return (string) $body['id'];
    }

    private function buildCreateSubscription(float $amount, string $mandateId): CreateSubscription
    {
        $createSubscription = new CreateSubscription(
            'Integration Test Subscription',
            new Interval(1, IntervalUnit::MONTHS),
            new Money($amount, 'EUR')
        );
        $createSubscription->setMandateId($mandateId);
        $createSubscription->setStartDate((new \DateTimeImmutable('+1 day'))->format('Y-m-d'));
        $createSubscription->setTimes(2);
        $createSubscription->setWebhookUrl('https://example.com/integration-test/webhook');

        return $createSubscription;
    }
}
