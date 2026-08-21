<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Shipping;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ShippingMethodFixture extends AbstractFixture
{
    /**
     * @param EntityRepository<ShippingMethodCollection<ShippingMethodEntity>> $shippingMethodRepository
     * @param EntityRepository<RuleCollection<RuleEntity>> $ruleRepository
     * @param EntityRepository<DeliveryTimeCollection<DeliveryTimeEntity>> $deliveryTimeRepository
     * @param EntityRepository<SalesChannelCollection<SalesChannelEntity>> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'shipping_method.repository')]
        private readonly EntityRepository $shippingMethodRepository,
        #[Autowire(service: 'rule.repository')]
        private readonly EntityRepository $ruleRepository,
        #[Autowire(service: 'delivery_time.repository')]
        private readonly EntityRepository $deliveryTimeRepository,
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $shippingMethods = [
            $this->buildShippingMethod('mollie-shipping-method', 'Mollie Test Shipment', 'mollie_fixture_shipment', 4.99),
            // express checkouts have to be tested with a real card, so shipping costs are kept
            // at a level where a live test does not waste money
            $this->buildShippingMethod('mollie-cheap-shipping-method', 'Mollie Cheap Test Shipment', 'mollie_fixture_cheap_shipment', 0.01),
        ];

        $this->shippingMethodRepository->upsert($shippingMethods, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();
        $salesChannelUpsert = [];
        foreach ($salesChannels as $salesChannel) {
            $salesChannelUpsert[] = [
                'id' => $salesChannel->getId(),
                'shippingMethods' => array_map(static fn (array $shippingMethod): array => ['id' => $shippingMethod['id']], $shippingMethods),
            ];
        }
        $this->salesChannelRepository->upsert($salesChannelUpsert, $context);
    }

    public function uninstall(Context $context): void
    {
        $shippingMethods = [
            ['id' => Uuid::fromStringToHex('mollie-shipping-method')],
            ['id' => Uuid::fromStringToHex('mollie-cheap-shipping-method')],
        ];
        $this->shippingMethodRepository->delete($shippingMethods, $context);

        $rules = [
            [
                'id' => Uuid::fromStringToHex('mollie-always-valid-rule'),
            ]
        ];
        $this->ruleRepository->delete($rules, $context);

        $deliveryTimes = [
            [
                'id' => Uuid::fromStringToHex('mollie-delivery-time'),
            ]
        ];
        $this->deliveryTimeRepository->delete($deliveryTimes, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShippingMethod(string $key, string $name, string $technicalName, float $price, int $taxRate = 19): array
    {
        $netPrice = round($price / (1 + $taxRate / 100), 2);

        return [
            'id' => Uuid::fromStringToHex($key),
            'name' => $name,
            'active' => true,
            'trackingUrl' => 'https://www.carrier.com/de/tracking/%s',
            'technicalName' => $technicalName,
            'taxId' => Uuid::fromStringToHex('tax-' . $taxRate),
            'availabilityRule' => [
                'id' => Uuid::fromStringToHex('mollie-always-valid-rule'),
                'name' => 'Always valid',
                'priority' => 100,
                'conditions' => [
                    [
                        'id' => Uuid::fromStringToHex('mollie-always-valid-condition'),
                        'type' => 'alwaysValid',
                        'position' => 1,
                    ]
                ]
            ],
            'deliveryTime' => [
                'id' => Uuid::fromStringToHex('mollie-delivery-time'),
                'name' => '1-3 days',
                'min' => 1,
                'max' => 3,
                'unit' => 'day',
            ],
            'prices' => [
                [
                    'id' => Uuid::fromStringToHex($key . '-price'),
                    'calculation' => 2,
                    'quantityStart' => 0,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => $netPrice,
                            'gross' => $price,
                            'linked' => true
                        ]
                    ]
                ]
            ],
        ];
    }
}
