<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Entity\OrderTransaction\OrderTransaction;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Payment implements \JsonSerializable
{
    use JsonSerializableTrait;
    private string $status;
    private OrderTransactionEntity $shopwareTransaction;
    private OrderTransaction $mollieTransaction;

    public function __construct(private string $id, PaymentStatus $status)
    {
        $this->setStatus($status);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): PaymentStatus
    {
        return new PaymentStatus($this->status);
    }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = (string) $status;
    }

    public function getShopwareTransaction(): OrderTransactionEntity
    {
        return $this->shopwareTransaction;
    }

    public function setShopwareTransaction(OrderTransactionEntity $shopwareTransaction): void
    {
        $this->shopwareTransaction = $shopwareTransaction;
    }

    public function getMollieTransaction(): OrderTransaction
    {
        return $this->mollieTransaction;
    }

    public function setMollieTransaction(OrderTransaction $mollieTransaction): void
    {
        $this->mollieTransaction = $mollieTransaction;
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }
}
