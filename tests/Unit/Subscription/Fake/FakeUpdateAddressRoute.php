<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\Route\AbstractUpdateAddressRoute;
use Mollie\Shopware\Component\Subscription\Route\UpdateAddressResponse;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeUpdateAddressRoute extends AbstractUpdateAddressRoute
{
    /** @var list<array{type:string,subscriptionId:string,data:RequestDataBag}> */
    private array $calls = [];

    private ?\Throwable $exception = null;

    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @return list<array{type:string,subscriptionId:string,data:RequestDataBag}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    public function getDecorated(): AbstractUpdateAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse
    {
        return $this->record(self::TYPE_BILLING, $subscriptionId, $data);
    }

    public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse
    {
        return $this->record(self::TYPE_SHIPPING, $subscriptionId, $data);
    }

    private function record(string $type, string $subscriptionId, RequestDataBag $data): UpdateAddressResponse
    {
        $this->calls[] = [
            'type' => $type,
            'subscriptionId' => $subscriptionId,
            'data' => $data,
        ];

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return new UpdateAddressResponse($subscriptionId, 'address-id', $type);
    }
}