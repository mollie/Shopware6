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
    private string $changePaymentStateUrl;

    private ?string $authenticationId = null;
    private ?PaymentMethod $method = null;

    private ?string $profileId = null;
    private ?string $customerId = null;
    private ?string $mandateId = null;
    private ?string $subscriptionId = null;

    private ?\DateTimeInterface $createdAt = null;

    public function __construct(private string $id)
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

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
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
        $payment = new self($body['id']);
        $paymentMethod = PaymentMethod::tryFrom($body['method'] ?? '');
        $payment->setStatus(PaymentStatus::from($body['status']));
        $thirdPartyPaymentId = $body['details']['paypalReference'] ?? null;
        $checkoutUrl = $body['_links']['checkout']['href'] ?? null;
        $changePaymentStateUrl = $body['_links']['changePaymentState']['href'] ?? null;
        $customerId = $body['customerId'] ?? null;
        $mandateId = $body['mandateId'] ?? null;
        $profileId = $body['profileId'] ?? null;
        $createdAt = $body['createdAt'] ?? null;
        $subscriptionId = $body['subscriptionId'] ?? null;

        if ($paymentMethod !== null) {
            $payment->setMethod($paymentMethod);
        }
        if ($thirdPartyPaymentId !== null) {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }
        if ($checkoutUrl !== null) {
            $payment->setCheckoutUrl($checkoutUrl);
        }
        if ($changePaymentStateUrl !== null) {
            $payment->setChangePaymentStateUrl($changePaymentStateUrl);
        }
        if ($customerId !== null) {
            $payment->setCustomerId($customerId);
        }
        if ($mandateId !== null) {
            $payment->setMandateId($mandateId);
        }
        if ($profileId !== null) {
            $payment->setProfileId($profileId);
        }
        if($createdAt !== null) {
            $createdAtDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $createdAt);
            if ($createdAtDate instanceof \DateTimeInterface) {
                $payment->setCreatedAt($createdAtDate);
            }
        }

        if($subscriptionId !== null) {
            $payment->setSubscriptionId($subscriptionId);
        }

        return $payment;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(?string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getProfileId(): ?string
    {
        return $this->profileId;
    }

    public function setProfileId(string $profileId): void
    {
        $this->profileId = $profileId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getMandateId(): ?string
    {
        return $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
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

    public function getChangePaymentStateUrl(): string
    {
        return $this->changePaymentStateUrl;
    }

    public function setChangePaymentStateUrl(string $changePaymentStateUrl): void
    {
        $this->changePaymentStateUrl = $changePaymentStateUrl;
    }

    public function getAuthenticationId(): ?string
    {
        return $this->authenticationId;
    }

    public function setAuthenticationId(?string $authenticationId): void
    {
        $this->authenticationId = $authenticationId;
    }
}
