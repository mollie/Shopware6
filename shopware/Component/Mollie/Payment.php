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

    private string $creditCardLabel = '';
    private string $creditCardNumber = '';
    private string $creditCardHolder = '';

    private string $paypalPayerId = '';

    private string $bankName = '';
    private string $bankAccount = '';
    private string $bankBic = '';
    private string $transferReference = '';
    private string $consumerName = '';
    private string $consumerAccount = '';
    private string $consumerBic = '';

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

    private ?Money $capturedAmount = null;

    private ?Money $amountRemaining = null;

    private ?Money $amount = null;

    private Money $amountRefunded;

    private ?Money $amountChargedBack = null;

    private float $voucherAmount = 0.0;

    private float $roundingDiff = 0.0;

    private RefundCollection $refunds;

    private bool $cancelable = false;

    private ?string $orderId = null;

    private ?string $paymentLinkId = null;

    public function __construct(private string $id)
    {
        $this->refunds = new RefundCollection();
    }

    public function isCancelable(): bool
    {
        return $this->cancelable;
    }

    public function setCancelable(bool $cancelable): void
    {
        $this->cancelable = $cancelable;
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
     * @return null|array{label: string, number: string, holder: string}
     */
    public function getCreditCardDetails(): ?array
    {
        if ($this->creditCardLabel === '') {
            return null;
        }

        return [
            'label' => $this->creditCardLabel,
            'number' => $this->creditCardNumber,
            'holder' => $this->creditCardHolder,
        ];
    }

    public function getCreditCardLabel(): string
    {
        return $this->creditCardLabel;
    }

    public function setCreditCardLabel(string $creditCardLabel): void
    {
        $this->creditCardLabel = $creditCardLabel;
    }

    public function getCreditCardNumber(): string
    {
        return $this->creditCardNumber;
    }

    public function setCreditCardNumber(string $creditCardNumber): void
    {
        $this->creditCardNumber = $creditCardNumber;
    }

    public function getCreditCardHolder(): string
    {
        return $this->creditCardHolder;
    }

    public function setCreditCardHolder(string $creditCardHolder): void
    {
        $this->creditCardHolder = $creditCardHolder;
    }

    public function getPaypalPayerId(): string
    {
        return $this->paypalPayerId;
    }

    public function setPaypalPayerId(string $paypalPayerId): void
    {
        $this->paypalPayerId = $paypalPayerId;
    }

    /**
     * @return null|array{payerId: string}
     */
    public function getPaypalDetails(): ?array
    {
        if ($this->paypalPayerId === '') {
            return null;
        }

        return ['payerId' => $this->paypalPayerId];
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): void
    {
        $this->bankName = $bankName;
    }

    public function getBankAccount(): string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(string $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
    }

    public function getBankBic(): string
    {
        return $this->bankBic;
    }

    public function setBankBic(string $bankBic): void
    {
        $this->bankBic = $bankBic;
    }

    public function getTransferReference(): string
    {
        return $this->transferReference;
    }

    public function setTransferReference(string $transferReference): void
    {
        $this->transferReference = $transferReference;
    }

    public function getConsumerName(): string
    {
        return $this->consumerName;
    }

    public function setConsumerName(string $consumerName): void
    {
        $this->consumerName = $consumerName;
    }

    public function getConsumerAccount(): string
    {
        return $this->consumerAccount;
    }

    public function setConsumerAccount(string $consumerAccount): void
    {
        $this->consumerAccount = $consumerAccount;
    }

    public function getConsumerBic(): string
    {
        return $this->consumerBic;
    }

    public function setConsumerBic(string $consumerBic): void
    {
        $this->consumerBic = $consumerBic;
    }

    /**
     * @return null|array{bankName: string, bankAccount: string, bankBic: string, transferReference: string, consumerName: string, consumerAccount: string, consumerBic: string}
     */
    public function getBankTransferDetails(): ?array
    {
        if ($this->bankAccount === '') {
            return null;
        }

        return [
            'bankName' => $this->bankName,
            'bankAccount' => $this->bankAccount,
            'bankBic' => $this->bankBic,
            'transferReference' => $this->transferReference,
            'consumerName' => $this->consumerName,
            'consumerAccount' => $this->consumerAccount,
            'consumerBic' => $this->consumerBic,
        ];
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $payment = new self($body['id']);
        $paymentMethod = PaymentMethod::tryFrom($body['method'] ?? '');
        $thirdPartyPaymentId = $body['details']['paypalReference'] ?? null;
        $checkoutUrl = $body['_links']['checkout']['href'] ?? null;
        $changePaymentStateUrl = $body['_links']['changePaymentState']['href'] ?? null;
        $customerId = $body['customerId'] ?? null;
        $mandateId = $body['mandateId'] ?? null;
        $profileId = $body['profileId'] ?? null;
        $createdAt = $body['createdAt'] ?? null;
        $subscriptionId = $body['subscriptionId'] ?? null;
        $capturedAmount = $body['amountCaptured'] ?? null;
        $amountRemaining = $body['amountRemaining'] ?? null;
        $amount = $body['amount'] ?? null;
        $amountRefunded = $body['amountRefunded'] ?? null;

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
        if ($createdAt !== null) {
            $createdAtDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $createdAt);
            if ($createdAtDate instanceof \DateTimeInterface) {
                $payment->setCreatedAt($createdAtDate);
            }
        }

        if ($subscriptionId !== null) {
            $payment->setSubscriptionId($subscriptionId);
        }

        if ($capturedAmount !== null) {
            $payment->setCapturedAmount(new Money((float) $capturedAmount['value'], $capturedAmount['currency']));
        }

        if ($amountRemaining !== null) {
            $payment->setAmountRemaining(new Money((float) $amountRemaining['value'], $amountRemaining['currency']));
        }
        if ($amount !== null) {
            $payment->setAmount(new Money((float) $amount['value'], $amount['currency']));
        }
        $payment->setAmountRefunded(new Money(
            (float) ($amountRefunded['value'] ?? 0.0),
            (string) ($amountRefunded['currency'] ?? $amount['currency'] ?? ''),
        ));

        $amountChargedBack = $body['amountChargedBack'] ?? null;
        if ($amountChargedBack !== null) {
            $payment->setAmountChargedBack(new Money((float) $amountChargedBack['value'], $amountChargedBack['currency']));
        }

        foreach ($body['details']['vouchers'] ?? [] as $voucher) {
            $payment->voucherAmount += (float) ($voucher['amount']['value'] ?? 0.0);
        }

        foreach ($body['lines'] ?? [] as $line) {
            $metadata = $line['metadata'] ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?: [];
            }
            $isRoundingLine = ($line['sku'] ?? '') === RoundingDifferenceFixer::SKU
                || ($metadata['type'] ?? '') === RoundingDifferenceFixer::METADATA_TYPE;
            if ($isRoundingLine) {
                $payment->roundingDiff += (float) ($line['totalAmount']['value'] ?? 0.0);
            }
        }

        $payment->setCancelable((bool) ($body['isCancelable'] ?? false));

        $cardLabel = $body['details']['cardLabel'] ?? null;
        if ($cardLabel !== null) {
            $payment->setCreditCardLabel((string) $cardLabel);
            $payment->setCreditCardNumber((string) ($body['details']['cardNumber'] ?? ''));
            $payment->setCreditCardHolder((string) ($body['details']['cardHolder'] ?? ''));
        }

        $paypalPayerId = $body['details']['paypalPayerId'] ?? null;
        if ($paypalPayerId !== null) {
            $payment->setPaypalPayerId((string) $paypalPayerId);
        }

        $bankAccount = $body['details']['bankAccount'] ?? null;
        if ($bankAccount !== null) {
            $payment->setBankName((string) ($body['details']['bankName'] ?? ''));
            $payment->setBankAccount((string) $bankAccount);
            $payment->setBankBic((string) ($body['details']['bankBic'] ?? ''));
            $payment->setTransferReference((string) ($body['details']['transferReference'] ?? ''));
            $payment->setConsumerName((string) ($body['details']['consumerName'] ?? ''));
            $payment->setConsumerAccount((string) ($body['details']['consumerAccount'] ?? ''));
            $payment->setConsumerBic((string) ($body['details']['consumerBic'] ?? ''));
        }

        foreach ($body['_embedded']['refunds'] ?? [] as $refundData) {
            $payment->refunds->add(Refund::createFromClientResponse($refundData));
        }

        // The Mollie Payments API has no dedicated chargeback/refund status, it keeps "paid" and
        // only exposes the corresponding amounts. Derive the implicit status here, after all
        // amounts are populated, so the rest of the application relies on a single status value.
        $status = PaymentStatus::from($body['status']);
        if ($payment->isPartiallyRefunded()) {
            $status = PaymentStatus::PARTIALLY_REFUNDED;
        }
        if ($payment->isFullyRefunded()) {
            $status = PaymentStatus::REFUNDED;
        }
        if ($payment->hasChargeback()) {
            $status = PaymentStatus::CHARGEBACK;
        }
        $payment->setStatus($status);

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

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentLinkId(): ?string
    {
        return $this->paymentLinkId;
    }

    public function setPaymentLinkId(?string $paymentLinkId): void
    {
        $this->paymentLinkId = $paymentLinkId;
    }

    public function getAuthenticationId(): ?string
    {
        return $this->authenticationId;
    }

    public function setAuthenticationId(?string $authenticationId): void
    {
        $this->authenticationId = $authenticationId;
    }

    public function getCapturedAmount(): ?Money
    {
        return $this->capturedAmount;
    }

    public function setCapturedAmount(Money $capturedAmount): void
    {
        $this->capturedAmount = $capturedAmount;
    }

    public function getAmountRemaining(): ?Money
    {
        return $this->amountRemaining;
    }

    public function setAmountRemaining(Money $amountRemaining): void
    {
        $this->amountRemaining = $amountRemaining;
    }

    public function getAmount(): ?Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getAmountRefunded(): Money
    {
        return $this->amountRefunded;
    }

    public function setAmountRefunded(Money $amountRefunded): void
    {
        $this->amountRefunded = $amountRefunded;
    }

    public function getRefunds(): RefundCollection
    {
        return $this->refunds;
    }

    public function getAmountChargedBack(): ?Money
    {
        return $this->amountChargedBack;
    }

    public function setAmountChargedBack(Money $amountChargedBack): void
    {
        $this->amountChargedBack = $amountChargedBack;
    }

    public function getVoucherAmount(): float
    {
        return $this->voucherAmount;
    }

    public function setVoucherAmount(float $voucherAmount): void
    {
        $this->voucherAmount = $voucherAmount;
    }

    public function getRoundingDiff(): float
    {
        return $this->roundingDiff;
    }

    public function setRoundingDiff(float $roundingDiff): void
    {
        $this->roundingDiff = $roundingDiff;
    }

    /**
     * The Mollie Payments API has no dedicated chargeback status. A charged back payment keeps
     * its "paid" status and only exposes a positive "amountChargedBack" value instead.
     */
    public function hasChargeback(): bool
    {
        return $this->amountChargedBack !== null && $this->amountChargedBack->getValue() > 0.0;
    }

    public function hasRefund(): bool
    {
        return isset($this->amountRefunded) && $this->amountRefunded->getValue() > 0.0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->hasRefund()
            && $this->amountRemaining !== null
            && $this->amountRemaining->getValue() <= 0.0;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->hasRefund() && ! $this->isFullyRefunded();
    }
}
