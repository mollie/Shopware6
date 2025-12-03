<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class Payment extends Struct implements \JsonSerializable
{
    use JsonSerializableTrait;

    private PaymentStatus $status;
    private OrderTransactionEntity $shopwareTransaction;

    private string $thirdPartyPaymentId = '';
    private string $checkoutUrl = '';

    private string $finalizeUrl = '';
    private int $countPayments = 1;

    public function __construct(private string $id, private PaymentMethod $method)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = $status;
    }

    public function getFinalizeUrl(): string
    {
        return $this->finalizeUrl;
    }

    public function setFinalizeUrl(string $finalizeUrl): void
    {
        $this->finalizeUrl = $finalizeUrl;
    }

    public function getCountPayments(): int
    {
        return $this->countPayments;
    }

    public function setCountPayments(int $countPayments): void
    {
        $this->countPayments = $countPayments;
    }

    public function getShopwareTransaction(): OrderTransactionEntity
    {
        return $this->shopwareTransaction;
    }

    public function setShopwareTransaction(OrderTransactionEntity $shopwareTransaction): void
    {
        $this->shopwareTransaction = $shopwareTransaction;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getThirdPartyPaymentId(): string
    {
        return $this->thirdPartyPaymentId;
    }

    public function setThirdPartyPaymentId(string $thirdPartyPaymentId): void
    {
        $this->thirdPartyPaymentId = $thirdPartyPaymentId;
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $payment = new self($body['id'], PaymentMethod::from($body['method']));
        $payment->setStatus(PaymentStatus::from($body['status']));
        $thirdPartyPaymentId = $body['details']['paypalReference'] ?? null;
        $checkoutUrl = $body['_links']['checkout']['href'] ?? null;

        if ($thirdPartyPaymentId !== null) {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }
        if ($checkoutUrl !== null) {
            $payment->setCheckoutUrl($checkoutUrl);
        }

        return $payment;
    }

    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    public function setCheckoutUrl(string $checkoutUrl): void
    {
        $this->checkoutUrl = $checkoutUrl;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $data = json_decode((string) json_encode($this), true);
        unset($data['shopwareTransaction']);

        return array_filter($data);
    }
}
