<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRouteResponse;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartOrderRoute extends AbstractCartOrderRoute
{
    private ?OrderEntity $response = null;

    /** @var list<array{cart:Cart,context:SalesChannelContext}> */
    private array $calls = [];

    public function setResponse(OrderEntity $order): void
    {
        $this->response = $order;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{cart:Cart,context:SalesChannelContext}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        throw new \LogicException('FakeCartOrderRoute::getDecorated not implemented');
    }

    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse
    {
        $this->calls[] = [
            'cart' => $cart,
            'context' => $context,
        ];

        if (! $this->response instanceof OrderEntity) {
            throw new \RuntimeException('FakeCartOrderRoute::order called without configured response. Use setResponse() in the test.');
        }

        return new CartOrderRouteResponse($this->response);
    }
}
