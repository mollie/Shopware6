<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Voucher;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Fixture\SalesChannelTrait;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VoucherFixture extends AbstractFixture
{
    use SalesChannelTrait;

    /**
     * @param EntityRepository<PromotionCollection<PromotionEntity>> $promotionRepository
     */
    public function __construct(
        #[Autowire(service: 'promotion.repository')]
        private readonly EntityRepository $promotionRepository,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $salesChannelId = $this->getSalesChannelId($context);
        $promotions = [];
        $promotions[] = $this->getPromotionData('Mollie test: 5 euro', 'mollie_5', 5.00, $salesChannelId);
        $promotions[] = $this->getPromotionData('Mollie test: 50 percent', 'mollie_50', 50.00, $salesChannelId, type: 'percentage');
        $promotions[] = $this->getPromotionData('Mollie test: free shipping', 'mollie_free_shipping', 100.00, $salesChannelId, 'delivery', 'percentage');
        $promotions[] = $this->getPromotionData('Mollie test: reduced shipping', 'mollie_shipping', 2.00, $salesChannelId, 'delivery');

        $this->promotionRepository->upsert($promotions, $context);
    }

    public function uninstall(Context $context): void
    {
        $promotions = [
            ['id' => $this->getId('mollie_5')],
            ['id' => $this->getId('mollie_50')],
            ['id' => $this->getId('mollie_free_shipping')],
            ['id' => $this->getId('mollie_shipping')],
        ];
        $this->promotionRepository->delete($promotions, $context);
    }

    private function getId(string $code): string
    {
        return Uuid::fromStringToHex('mollie-promotion-' . $code);
    }

    /**
     * @return array<mixed>
     */
    private function getPromotionData(string $name, string $code, float $value, string $salesChannelId, string $scope = 'cart', string $type = 'absolute'): array
    {
        return [
            'id' => $this->getId($code),
            'name' => $name,
            'priority' => 1,
            'exclusive' => false,
            'useCodes' => true,
            'code' => $code,
            'salesChannels' => [
                [
                    'salesChannelId' => $salesChannelId,
                    'priority' => 1
                ]
            ],
            'discounts' => [
                [
                    'id' => Uuid::fromStringToHex('promotion-discount-' . $code),
                    'scope' => $scope,
                    'type' => $type,
                    'value' => $value
                ]
            ],
            'active' => true,
        ];
    }
}
