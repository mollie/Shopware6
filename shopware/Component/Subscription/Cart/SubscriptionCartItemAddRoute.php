<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart;

use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

#[AsDecorator(decorates: CartItemAddRoute::class)]
class SubscriptionCartItemAddRoute extends AbstractCartItemAddRoute
{
    public function __construct(
        #[AutowireDecorated]
        private readonly AbstractCartItemAddRoute $decorated
    ) {
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        return $this->decorated;
    }

    /**
     * @param null|array<LineItem> $items
     */
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        $referencedId = $this->resolveSubscribeReferencedId($request);

        if ($referencedId !== '' && $items !== null) {
            foreach ($items as $item) {
                if (! $item instanceof LineItem) {
                    continue;
                }
                if ($item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                    continue;
                }
                if ($item->getReferencedId() !== $referencedId) {
                    continue;
                }

                $item->setId(Mollie::SUBSCRIPTION_LINE_ITEM_PREFIX . $referencedId);
                $item->setPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY, true);
            }
        }

        return $this->getDecorated()->add($request, $cart, $context, $items);
    }

    private function resolveSubscribeReferencedId(Request $request): string
    {
        $referencedId = (string) $request->request->get(Mollie::SUBSCRIBE_REQUEST_KEY, '');
        if ($referencedId !== '') {
            return $referencedId;
        }

        // CartService::add() calls this route with an empty Request, so the storefront form
        // fields only live in the global request (same reason as the express-checkout decorator).
        $globalRequest = Request::createFromGlobals();

        return (string) $globalRequest->request->get(Mollie::SUBSCRIBE_REQUEST_KEY, '');
    }
}
