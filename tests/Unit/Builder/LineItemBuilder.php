<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

final class LineItemBuilder
{
    private string $id = 'line-item-id';
    private string $type = LineItem::PRODUCT_LINE_ITEM_TYPE;
    private ?string $referencedId = null;
    private int $quantity = 1;
    private bool $isSubscription = false;
    private ?Interval $interval = null;
    private ?string $label = null;
    private ?CalculatedPrice $price = null;
    /** @var array<string, mixed> */
    private array $payload = [];
    /** @var list<LineItem> */
    private array $children = [];

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

    public function withLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A line item only becomes a Mollie line once it is priced; without a price the conversion
     * rejects it.
     */
    public function withPrice(CalculatedPrice $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Children hang off their parent in a cart, and the flat list of a cart carries both.
     */
    public function withChild(LineItem $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function withPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function build(): LineItem
    {
        $lineItem = new LineItem($this->id, $this->type, $this->referencedId, $this->quantity);

        if ($this->label !== null) {
            $lineItem->setLabel($this->label);
        }

        if ($this->price instanceof CalculatedPrice) {
            $lineItem->setPrice($this->price);
        }

        if ($this->payload !== []) {
            $lineItem->setPayload($this->payload);
        }

        if ($this->children !== []) {
            $lineItem->setChildren(new LineItemCollection($this->children));
        }

        $product = new Product();
        $product->setIsSubscription($this->isSubscription);
        if ($this->interval instanceof Interval) {
            $product->setInterval($this->interval);
        }
        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }
}
