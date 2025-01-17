<?php

namespace PHPUnit\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use MolliePayments\Tests\Traits\BuilderTestTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieDataBuilderTest extends TestCase
{
    use BuilderTestTrait;


    /**
     * This test verifies that our builder creates a correct
     * payload structure for the subscription that should be created in Mollie.
     * We build a SubscriptionEntity and let our builder convert that to our payload.
     */
    public function testBuildSubscriptionPayload()
    {
        $routingBuilder = $this->buildRoutingBuilder($this, 'https://local.mollie.shop/mollie/webhook/subscription/abc/renew');

        $builder = new MollieDataBuilder($routingBuilder);

        $subscription = new SubscriptionEntity();
        $subscription->setId('ID123');
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');
        $subscription->setCurrency($currency);
        $subscription->setAmount(10.5);
        $subscription->setDescription('Subscription Product A');

        $payload = $builder->buildRequestPayload($subscription, '2022-01-01', '2', 'days', 5, 'mdt_123');

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
