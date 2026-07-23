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
    private bool $reconciled = false;
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

    /**
     * Set for "pay by link" transactions: the Mollie payment link id created before an actual
     * payment exists. Persisted alongside the regular payment data on the transaction so the
     * return/webhook flow can resolve the real payment via the payment link.
     */
    private ?string $paymentLinkId = null;

    public function __construct(private string $id = '')
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

    public function isReconciled(): bool
    {
        return $this->reconciled;
    }

    public function setReconciled(bool $reconciled): void
    {
        $this->reconciled = $reconciled;
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
        $hydrator = new PaymentHydrator();

        return $hydrator->hydrate($body);
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
