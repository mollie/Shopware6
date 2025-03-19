<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Setting;

use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Shopware\Core\Framework\Struct\Struct;

class MollieSettingStruct extends Struct
{
    public const ORDER_STATE_SKIP = 'skip';

    public const ORDER_EXPIRES_AT_MIN_DAYS = 1;
    public const ORDER_EXPIRES_AT_MAX_DAYS = 100;

    public const APPLE_PAY_DIRECT_DOMAIN_ALLOW_LIST = 'applePayDirectDomainAllowList';

    /**
     * @var string
     */
    protected $liveApiKey;

    /**
     * @var string
     */
    protected $testApiKey;

    /**
     * @var string
     */
    protected $profileId;

    /**
     * @var string
     */
    protected $liveProfileId;

    /**
     * @var string
     */
    protected $testProfileId;

    /**
     * @var bool
     */
    protected $testMode = true;

    /**
     * @var bool
     */
    protected $debugMode = false;

    /**
     * @var int
     */
    protected $logFileDays = 0;

    /**
     * @var bool
     */
    protected $useMolliePaymentMethodLimits = false;

    /**
     * @var bool
     */
    protected $shopwareFailedPayment = false;

    /**
     * @var bool
     */
    protected $createCustomersAtMollie = true;

    /**
     * @var string
     */
    protected $formatOrderNumber = '';

    /**
     * @var bool
     */
    protected $enableCreditCardComponents = false;

    /**
     * @var bool
     */
    protected $oneClickPaymentsCompactView = false;

    /**
     * @var bool
     */
    protected $oneClickPaymentsEnabled = false;

    /**
     * @var bool
     */
    protected $enableApplePayDirect = false;

    /**
     * @var int
     */
    protected $paymentMethodBankTransferDueDateDays;

    /**
     * @var int
     */
    protected $orderLifetimeDays;

    /**
     * @var bool
     */
    protected $automaticShipping;

    /**
     * @var bool
     */
    protected $automaticCancellation;

    /**
     * @var bool
     */
    protected $refundManagerEnabled;

    /**
     * @var bool
     */
    protected $refundManagerVerifyRefund;

    /**
     * @var bool
     */
    protected $refundManagerAutoStockReset;

    /**
     * @var bool
     */
    protected $refundManagerShowInstructions;

    /**
     * @var string[]
     */
    protected $applePayDirectRestrictions = [];

    /**
     * @var string
     */
    protected $orderStateFinalState;

    /**
     * @var string
     */
    protected $orderStateWithAPaidTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithAFailedTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithACancelledTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithAAuthorizedTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithAChargebackTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithPartialRefundTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var string
     */
    protected $orderStateWithRefundTransaction = self::ORDER_STATE_SKIP;

    /**
     * @var bool
     */
    protected $subscriptionsShowIndicator;

    /**
     * @var bool
     */
    protected $subscriptionsAllowAddressEditing;

    /**
     * @var bool
     */
    protected $subscriptionsAllowPauseResume;

    /**
     * @var bool
     */
    protected $subscriptionsAllowSkip;

    /**
     * @var int
     */
    protected $subscriptionsReminderDays;

    /**
     * @var int
     */
    protected $subscriptionsCancellationDays;

    /**
     * @var bool
     */
    protected $subscriptionSkipRenewalsOnFailedPayments;

    /**
     * @var bool
     */
    protected $subscriptionsEnabled;

    /**
     * @var bool
     */
    protected $fixRoundingDiffEnabled = false;

    /**
     * @var string
     */
    protected $fixRoundingDiffName = '';

    /**
     * @var string
     */
    protected $fixRoundingDiffSKU = '';

    /**
     * @var bool
     */
    protected $useShopwareJavascript = false;

    /**
     * @var bool
     */
    protected $phoneNumberFieldRequired = false;

    /**
     * @var bool
     */
    protected $showPhoneNumberField = false;

    /**
     * @var string
     */
    protected $applePayDirectDomainAllowList = '';

    /**
     * @var int
     */
    protected $paymentFinalizeTransactionTime;

    /**
     * @var bool
     */
    protected $requireDataProtectionCheckbox = false;

    /**
     * @var bool
     */
    protected $refundManagerCreateCreditNotes = false;

    /**
     * @var string
     */
    protected $refundManagerCreateCreditNotesPrefix = '';

    /**
     * @var string
     */
    protected $refundManagerCreateCreditNotesSuffix = '';

    /**
     * @var bool
     */
    protected $automaticOrderExpire = false;

    protected bool $paypalExpressEnabled = false;

    /**
     * @var int
     */
    protected $paypalExpressButtonStyle = 1;

    /**
     * @var int
     */
    protected $paypalExpressButtonShape = 1;

    /**
     * @var array<mixed>
     */
    protected $paypalExpressRestrictions = [];

    public function getLiveApiKey(): string
    {
        return (string) $this->liveApiKey;
    }

    public function setLiveApiKey(string $liveApiKey): self
    {
        $this->liveApiKey = $liveApiKey;

        return $this;
    }

    public function getTestApiKey(): string
    {
        return (string) $this->testApiKey;
    }

    public function setTestApiKey(string $testApiKey): self
    {
        $this->testApiKey = $testApiKey;

        return $this;
    }

    public function getProfileId(): ?string
    {
        if ($this->profileId !== null) {
            return $this->profileId;
        }

        if ($this->isTestMode()) {
            return $this->testProfileId;
        }

        return $this->liveProfileId;
    }

    public function setProfileId(string $profileId): self
    {
        $this->profileId = $profileId;

        return $this;
    }

    public function isTestMode(): bool
    {
        return (bool) $this->testMode;
    }

    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    public function getLogFileDays(): int
    {
        if ($this->logFileDays === 0) {
            // better be safe than sorry, default was always 14
            return 14;
        }

        return $this->logFileDays;
    }

    public function setLogFileDays(int $logFileDays): void
    {
        $this->logFileDays = $logFileDays;
    }

    public function isShopwareStandardFailureMode(): bool
    {
        return (bool) $this->shopwareFailedPayment;
    }

    public function setShopwareFailedPaymentMethod(bool $shopwareFailedPayment): self
    {
        $this->shopwareFailedPayment = $shopwareFailedPayment;

        return $this;
    }

    public function createCustomersAtMollie(): bool
    {
        return $this->createCustomersAtMollie;
    }

    public function setCreateCustomersAtMollie(bool $createCustomersAtMollie): self
    {
        $this->createCustomersAtMollie = $createCustomersAtMollie;

        return $this;
    }

    public function isDebugMode(): bool
    {
        return (bool) $this->debugMode;
    }

    public function setDebugMode(bool $debugMode): self
    {
        $this->debugMode = $debugMode;

        return $this;
    }

    public function getUseMolliePaymentMethodLimits(): bool
    {
        return (bool) $this->useMolliePaymentMethodLimits;
    }

    public function setUseMolliePaymentMethodLimits(bool $useMolliePaymentMethodLimits): self
    {
        $this->useMolliePaymentMethodLimits = $useMolliePaymentMethodLimits;

        return $this;
    }

    public function getFormatOrderNumber(): string
    {
        return (string) $this->formatOrderNumber;
    }

    public function setFormatOrderNumber(string $formatOrderNumber): void
    {
        $this->formatOrderNumber = $formatOrderNumber;
    }

    /**
     * @return bool
     */
    public function getEnableCreditCardComponents()
    {
        return (bool) $this->enableCreditCardComponents;
    }

    public function setEnableCreditCardComponents(bool $enableCreditCardComponents): self
    {
        $this->enableCreditCardComponents = $enableCreditCardComponents;

        return $this;
    }

    public function isOneClickPaymentsEnabled(): bool
    {
        return $this->oneClickPaymentsEnabled;
    }

    public function setOneClickPaymentsEnabled(bool $oneClickPaymentsEnabled): void
    {
        $this->oneClickPaymentsEnabled = $oneClickPaymentsEnabled;
    }

    public function isOneClickPaymentsCompactView(): bool
    {
        return $this->oneClickPaymentsCompactView;
    }

    public function setOneClickPaymentsCompactView(bool $oneClickPaymentsCompactView): self
    {
        $this->oneClickPaymentsCompactView = $oneClickPaymentsCompactView;

        return $this;
    }

    public function getPaymentMethodBankTransferDueDateDays(): ?int
    {
        if (! $this->paymentMethodBankTransferDueDateDays) {
            return null;
        }

        return min(
            max(
                BankTransferPayment::DUE_DATE_MIN_DAYS,
                $this->paymentMethodBankTransferDueDateDays
            ),
            BankTransferPayment::DUE_DATE_MAX_DAYS
        );
    }

    /**
     * returns bank transfer due date in YYYY-MM-DD format or null
     *
     * @throws \Exception
     */
    public function getPaymentMethodBankTransferDueDate(): ?string
    {
        $dueDate = $this->getPaymentMethodBankTransferDueDateDays();
        if (! $dueDate) {
            return null;
        }

        return (new \DateTime())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $dueDate))
            ->format('Y-m-d')
        ;
    }

    public function getOrderLifetimeDays(): ?int
    {
        if (! $this->orderLifetimeDays) {
            return null;
        }

        return min(
            max(
                self::ORDER_EXPIRES_AT_MIN_DAYS,
                $this->orderLifetimeDays
            ),
            self::ORDER_EXPIRES_AT_MAX_DAYS
        );
    }

    /**
     * @throws \Exception
     */
    public function getOrderLifetimeDate(): ?string
    {
        $orderLifeTimeDays = $this->getOrderLifetimeDays();
        if (! $orderLifeTimeDays) {
            return null;
        }

        return (new \DateTime())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $orderLifeTimeDays))
            ->format('Y-m-d')
        ;
    }

    /**
     * @return string[]
     */
    public function getRestrictApplePayDirect(): array
    {
        return $this->applePayDirectRestrictions;
    }

    public function getOrderStateWithAPaidTransaction(): string
    {
        return (string) $this->orderStateWithAPaidTransaction;
    }

    public function getOrderStateWithAFailedTransaction(): string
    {
        return (string) $this->orderStateWithAFailedTransaction;
    }

    public function getOrderStateWithACancelledTransaction(): string
    {
        return (string) $this->orderStateWithACancelledTransaction;
    }

    public function getOrderStateWithAAuthorizedTransaction(): string
    {
        return (string) $this->orderStateWithAAuthorizedTransaction;
    }

    public function getOrderStateWithAChargebackTransaction(): string
    {
        return $this->orderStateWithAChargebackTransaction;
    }

    public function setOrderStateWithAChargebackTransaction(string $orderStateWithAChargebackTransaction): void
    {
        $this->orderStateWithAChargebackTransaction = $orderStateWithAChargebackTransaction;
    }

    public function isEnableApplePayDirect(): bool
    {
        return $this->enableApplePayDirect;
    }

    public function setEnableApplePayDirect(bool $enableApplePayDirect): void
    {
        $this->enableApplePayDirect = $enableApplePayDirect;
    }

    public function getAutomaticShipping(): bool
    {
        return (bool) $this->automaticShipping;
    }

    public function setAutomaticShipping(bool $automaticShipping): void
    {
        $this->automaticShipping = $automaticShipping;
    }

    public function isAutomaticCancellation(): bool
    {
        return $this->automaticCancellation;
    }

    public function setAutomaticCancellation(bool $automaticCancellation): void
    {
        $this->automaticCancellation = $automaticCancellation;
    }

    public function isRefundManagerEnabled(): bool
    {
        return (bool) $this->refundManagerEnabled;
    }

    public function setRefundManagerEnabled(bool $refundManagerEnabled): void
    {
        $this->refundManagerEnabled = $refundManagerEnabled;
    }

    public function isRefundManagerVerifyRefund(): bool
    {
        return (bool) $this->refundManagerVerifyRefund;
    }

    public function setRefundManagerVerifyRefund(bool $refundManagerVerifyRefund): void
    {
        $this->refundManagerVerifyRefund = $refundManagerVerifyRefund;
    }

    public function isRefundManagerAutoStockReset(): bool
    {
        return (bool) $this->refundManagerAutoStockReset;
    }

    public function setRefundManagerAutoStockReset(bool $refundManagerAutoStockReset): void
    {
        $this->refundManagerAutoStockReset = $refundManagerAutoStockReset;
    }

    public function isRefundManagerShowInstructions(): bool
    {
        return (bool) $this->refundManagerShowInstructions;
    }

    public function setRefundManagerShowInstructions(bool $refundManagerShowInstructions): void
    {
        $this->refundManagerShowInstructions = $refundManagerShowInstructions;
    }

    public function getOrderStateFinalState(): string
    {
        return (string) $this->orderStateFinalState;
    }

    public function setOrderStateFinalState(string $orderStateFinalState): void
    {
        $this->orderStateFinalState = $orderStateFinalState;
    }

    public function getOrderStateWithPartialRefundTransaction(): string
    {
        return $this->orderStateWithPartialRefundTransaction;
    }

    public function setOrderStateWithPartialRefundTransaction(string $orderStateWithPartialRefundTransaction): void
    {
        $this->orderStateWithPartialRefundTransaction = $orderStateWithPartialRefundTransaction;
    }

    public function getOrderStateWithRefundTransaction(): string
    {
        return $this->orderStateWithRefundTransaction;
    }

    public function setOrderStateWithRefundTransaction(string $orderStateWithRefundTransaction): void
    {
        $this->orderStateWithRefundTransaction = $orderStateWithRefundTransaction;
    }

    public function isSubscriptionsShowIndicator(): bool
    {
        return (bool) $this->subscriptionsShowIndicator;
    }

    public function getSubscriptionsReminderDays(): int
    {
        return (int) $this->subscriptionsReminderDays;
    }

    public function getSubscriptionsCancellationDays(): int
    {
        return (int) $this->subscriptionsCancellationDays;
    }

    public function isSubscriptionsAllowAddressEditing(): bool
    {
        return (bool) $this->subscriptionsAllowAddressEditing;
    }

    public function setSubscriptionsAllowAddressEditing(bool $subscriptionsAllowAddressEditing): void
    {
        $this->subscriptionsAllowAddressEditing = $subscriptionsAllowAddressEditing;
    }

    public function isSubscriptionsAllowPauseResume(): bool
    {
        return (bool) $this->subscriptionsAllowPauseResume;
    }

    public function setSubscriptionsAllowPauseResume(bool $subscriptionsAllowPauseResume): void
    {
        $this->subscriptionsAllowPauseResume = $subscriptionsAllowPauseResume;
    }

    public function isSubscriptionsAllowSkip(): bool
    {
        return (bool) $this->subscriptionsAllowSkip;
    }

    public function setSubscriptionsAllowSkip(bool $subscriptionsAllowSkip): void
    {
        $this->subscriptionsAllowSkip = $subscriptionsAllowSkip;
    }

    public function isSubscriptionSkipRenewalsOnFailedPayments(): bool
    {
        return (bool) $this->subscriptionSkipRenewalsOnFailedPayments;
    }

    public function setSubscriptionSkipRenewalsOnFailedPayments(bool $subscriptionSkipRenewalsOnFailedPayments): void
    {
        $this->subscriptionSkipRenewalsOnFailedPayments = $subscriptionSkipRenewalsOnFailedPayments;
    }

    public function isSubscriptionsEnabled(): bool
    {
        return (bool) $this->subscriptionsEnabled;
    }

    public function setSubscriptionsEnabled(bool $subscriptionsEnabled): void
    {
        $this->subscriptionsEnabled = $subscriptionsEnabled;
    }

    public function isFixRoundingDiffEnabled(): bool
    {
        return $this->fixRoundingDiffEnabled;
    }

    public function setFixRoundingDiffEnabled(bool $fixRoundingDiffEnabled): void
    {
        $this->fixRoundingDiffEnabled = $fixRoundingDiffEnabled;
    }

    public function getFixRoundingDiffName(): string
    {
        return $this->fixRoundingDiffName;
    }

    public function setFixRoundingDiffName(string $fixRoundingDiffName): void
    {
        $this->fixRoundingDiffName = $fixRoundingDiffName;
    }

    public function getFixRoundingDiffSKU(): string
    {
        return $this->fixRoundingDiffSKU;
    }

    public function setFixRoundingDiffSKU(string $fixRoundingDiffSKU): void
    {
        $this->fixRoundingDiffSKU = $fixRoundingDiffSKU;
    }

    public function isUseShopwareJavascript(): bool
    {
        return $this->useShopwareJavascript;
    }

    public function setUseShopwareJavascript(bool $useShopwareJavascript): void
    {
        $this->useShopwareJavascript = $useShopwareJavascript;
    }

    public function isPhoneNumberFieldRequired(): bool
    {
        return $this->phoneNumberFieldRequired;
    }

    public function setPhoneNumberFieldRequired(bool $phoneNumberFieldRequired): void
    {
        $this->phoneNumberFieldRequired = $phoneNumberFieldRequired;
    }

    public function isPhoneNumberFieldShown(): bool
    {
        return $this->showPhoneNumberField;
    }

    public function setShowPhoneNumberField(bool $showPhoneNumberField): void
    {
        $this->showPhoneNumberField = $showPhoneNumberField;
    }

    public function getApplePayDirectDomainAllowList(): string
    {
        return $this->applePayDirectDomainAllowList;
    }

    public function setApplePayDirectDomainAllowList(string $applePayDirectDomainAllowList): void
    {
        $this->applePayDirectDomainAllowList = $applePayDirectDomainAllowList;
    }

    public function getPaymentFinalizeTransactionTime(): int
    {
        return $this->paymentFinalizeTransactionTime;
    }

    public function setPaymentFinalizeTransactionTime(int $paymentFinalizeTransactionTime): void
    {
        $this->paymentFinalizeTransactionTime = $paymentFinalizeTransactionTime;
    }

    public function isRequireDataProtectionCheckbox(): bool
    {
        return $this->requireDataProtectionCheckbox;
    }

    public function setRequireDataProtectionCheckbox(bool $requireDataProtectionCheckbox): void
    {
        $this->requireDataProtectionCheckbox = $requireDataProtectionCheckbox;
    }

    public function isRefundManagerCreateCreditNotesEnabled(): bool
    {
        return $this->refundManagerCreateCreditNotes;
    }

    public function setRefundManagerCreateCreditNotesEnabled(bool $refundManagerCreateCreditNotes): void
    {
        $this->refundManagerCreateCreditNotes = $refundManagerCreateCreditNotes;
    }

    public function getRefundManagerCreateCreditNotesPrefix(): string
    {
        return $this->refundManagerCreateCreditNotesPrefix;
    }

    public function setRefundManagerCreateCreditNotesPrefix(string $refundManagerCreateCreditNotesPrefix): void
    {
        $this->refundManagerCreateCreditNotesPrefix = $refundManagerCreateCreditNotesPrefix;
    }

    public function getRefundManagerCreateCreditNotesSuffix(): string
    {
        return $this->refundManagerCreateCreditNotesSuffix;
    }

    public function setRefundManagerCreateCreditNotesSuffix(string $refundManagerCreateCreditNotesSuffix): void
    {
        $this->refundManagerCreateCreditNotesSuffix = $refundManagerCreateCreditNotesSuffix;
    }

    public function isAutomaticOrderExpire(): bool
    {
        return $this->automaticOrderExpire;
    }

    public function setAutomaticOrderExpire(bool $automaticOrderExpire): void
    {
        $this->automaticOrderExpire = $automaticOrderExpire;
    }

    public function isPaypalExpressEnabled(): bool
    {
        return $this->paypalExpressEnabled;
    }

    public function setPaypalExpressEnabled(bool $paypalExpressEnabled): void
    {
        $this->paypalExpressEnabled = $paypalExpressEnabled;
    }

    public function getPaypalExpressButtonStyle(): int
    {
        return $this->paypalExpressButtonStyle;
    }

    public function setPaypalExpressButtonStyle(int $paypalExpressButtonStyle): void
    {
        $this->paypalExpressButtonStyle = $paypalExpressButtonStyle;
    }

    public function getPaypalExpressButtonShape(): int
    {
        return $this->paypalExpressButtonShape;
    }

    public function setPaypalExpressButtonShape(int $paypalExpressButtonShape): void
    {
        $this->paypalExpressButtonShape = $paypalExpressButtonShape;
    }

    /**
     * @return string[]
     */
    public function getPaypalExpressRestrictions(): array
    {
        return $this->paypalExpressRestrictions;
    }

    /**
     * @param array<string> $paypalExpressRestrictions
     */
    public function setPaypalExpressRestrictions(array $paypalExpressRestrictions): void
    {
        $this->paypalExpressRestrictions = $paypalExpressRestrictions;
    }
}
