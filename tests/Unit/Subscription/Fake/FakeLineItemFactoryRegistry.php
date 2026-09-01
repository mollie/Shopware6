<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Builds the line item from the data it is handed, exactly as the real registry does for a product
 * line - a new object every call, so a test can tell the created items apart.
 */
final class FakeLineItemFactoryRegistry extends LineItemFactoryRegistry
{
    /** @var list<array<string, mixed>> */
    private array $createdFrom = [];

    public function __construct()
    {
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        $this->createdFrom[] = $data;

        return new LineItem(
            (string) ($data['id'] ?? ''),
            (string) ($data['type'] ?? LineItem::PRODUCT_LINE_ITEM_TYPE),
            (string) ($data['referencedId'] ?? ''),
            (int) ($data['quantity'] ?? 1)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCreatedFrom(): array
    {
        return $this->createdFrom;
    }
}
