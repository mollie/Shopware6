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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ShippingMethodFixture extends AbstractFixture
{
    /**
     * @param EntityRepository<ShippingMethodCollection<ShippingMethodEntity>> $shippingMethodRepository
     * @param EntityRepository<RuleCollection<RuleEntity>> $ruleRepository
     * @param EntityRepository<DeliveryTimeCollection<DeliveryTimeEntity>> $deliveryTimeRepository
     */
    public function __construct(
        #[Autowire(service: 'shipping_method.repository')]
        private readonly EntityRepository $shippingMethodRepository,
        #[Autowire(service: 'rule.repository')]
        private readonly EntityRepository $ruleRepository,
        #[Autowire(service: 'delivery_time.repository')]
        private readonly EntityRepository $deliveryTimeRepository,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $price = 4.99;
        $taxRate = 19;
        $netPrice = round($price / (1 + $taxRate / 100), 2);
        $data = [
            'id' => Uuid::fromStringToHex('mollie-shipping-method'),
            'name' => 'Mollie test shipping method',
            'active' => true,
            'trackingUrl' => 'https://www.carrier.com/de/tracking/%s',
            'technicalName' => 'mollie_fixture_shipment',
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
                    'id' => Uuid::fromStringToHex('price-' . $price),
                    'calculation' => 2,
                    'quantityStart' => 0,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => $netPrice,
                            'gross' => 4.99,
                            'linked' => true
                        ]
                    ]
                ]
            ],
        ];
        $this->shippingMethodRepository->upsert([$data], $context);
    }

    public function uninstall(Context $context): void
    {
        $shippingMethods = [
            [
                'id' => Uuid::fromStringToHex('mollie-shipping-method'),
            ]
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
}
