<?php

namespace PHPUnit\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
use MolliePayments\Tests\Fakes\FakePluginSettings;
use MolliePayments\Tests\Fakes\FakeRouter;
use PHPUnit\Framework\TestCase;

class MollieDataBuilderTest extends TestCase
{

    /**
     * This test verifies that our builder creates a correct
     * payload structure for the subscription that should be created in Mollie.
     * We build a SubscriptionEntity and let our builder convert that to our payload.
     */
    public function testBuildSubscriptionPayload()
    {
        $fakeRouter = new FakeRouter('https://local.mollie.shop/mollie/webhook/subscription/abc/renew');
        $webhookBuilder = new WebhookBuilder($fakeRouter, new FakePluginSettings(''));
        $builder = new MollieDataBuilder($webhookBuilder);


        $subscription = new SubscriptionEntity();
        $subscription->setId('ID123');
        $subscription->setCurrency('USD');
        $subscription->setAmount(10.5);
        $subscription->setDescription('Subscription Product A');
        $subscription->setMetadata(
            new SubscriptionMetadata(
                '2022-01-01',
                2,
                'days',
                '5',
                ''
            )
        );

        $payload = $builder->buildRequestPayload($subscription, 'mdt_123');

        $expected = [
            'amount' => [
                'currency' => 'USD',
                'value' => '10.50',
            ],
            'description' => 'Subscription Product A',
            'metadata' => [],
            'webhookUrl' => 'https://local.mollie.shop/mollie/webhook/subscription/abc/renew',
            'startDate' => '2022-01-01',
            'interval' => '2 days',
            'times' => 5,
            'mandateId' => 'mdt_123',
        ];

        $this->assertEquals($expected, $payload);
    }

}
