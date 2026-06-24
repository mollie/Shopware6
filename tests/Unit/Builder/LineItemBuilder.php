<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

final class LineItemBuilder
{
    private string $id = 'line-item-id';
    private string $type = LineItem::PRODUCT_LINE_ITEM_TYPE;
    private ?string $referencedId = null;
    private int $quantity = 1;
    private bool $isSubscription = false;
    private ?Interval $interval = null;

    public static function create(string $id): self
    {
        $instance = new self();
        $instance->id = $id;

        return $instance;
    }

    public static function subscription(string $id, int $intervalValue = 1, IntervalUnit $intervalUnit = IntervalUnit::MONTHS): self
    {
        $instance = self::create($id);
        $instance->isSubscription = true;
        $instance->interval = new Interval($intervalValue, $intervalUnit);

        return $instance;
    }

    public static function regular(string $id): self
    {
        $instance = self::create($id);
        $instance->isSubscription = false;

        return $instance;
    }

    public function withType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function withReferencedId(string $referencedId): self
    {
        $this->referencedId = $referencedId;

        return $this;
    }

    public function withQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function build(): LineItem
    {
        $lineItem = new LineItem($this->id, $this->type, $this->referencedId, $this->quantity);

        $product = new Product();
        $product->setIsSubscription($this->isSubscription);
        if ($this->interval instanceof Interval) {
            $product->setInterval($this->interval);
        }
        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }
}
