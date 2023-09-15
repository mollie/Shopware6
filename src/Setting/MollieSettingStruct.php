<?php

namespace Kiener\MolliePayments\Setting;

use DateTime;
use DateTimeZone;
use Exception;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Struct\Struct;

class MollieSettingStruct extends Struct
{
    public const ORDER_STATE_SKIP = 'skip';

    public const ORDER_EXPIRES_AT_MIN_DAYS = 1;
    public const ORDER_EXPIRES_AT_MAX_DAYS = 100;

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
     * @return string
     */
    public function getLiveApiKey(): string
    {
        return (string)$this->liveApiKey;
    }

    /**
     * @param string $liveApiKey
     *
     * @return self
     */
    public function setLiveApiKey(string $liveApiKey): self
    {
        $this->liveApiKey = $liveApiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getTestApiKey(): string
    {
        return (string)$this->testApiKey;
    }

    /**
     * @param string $testApiKey
     *
     * @return self
     */
    public function setTestApiKey(string $testApiKey): self
    {
        $this->testApiKey = $testApiKey;
        return $this;
    }

    /**
     * @return null|string
     */
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

    /**
     * @param string $profileId
     *
     * @return MollieSettingStruct
     */
    public function setProfileId(string $profileId): self
    {
        $this->profileId = $profileId;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return (bool)$this->testMode;
    }

    /**
     * @param bool $testMode
     *
     * @return self
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShopwareStandardFailureMode(): bool
    {
        return (bool)$this->shopwareFailedPayment;
    }

    /**
     * @param bool $shopwareFailedPayment
     *
     * @return self
     */
    public function setShopwareFailedPaymentMethod(bool $shopwareFailedPayment): self
    {
        $this->shopwareFailedPayment = $shopwareFailedPayment;
        return $this;
    }

    /**
     * @return bool
     */
    public function createCustomersAtMollie(): bool
    {
        return $this->createCustomersAtMollie;
    }

    /**
     * @param bool $createCustomersAtMollie
     *
     * @return self
     */
    public function setCreateCustomersAtMollie(bool $createCustomersAtMollie): self
    {
        $this->createCustomersAtMollie = $createCustomersAtMollie;
        return $this;
    }


    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool)$this->debugMode;
    }

    /**
     * @param bool $debugMode
     *
     * @return self
     */
    public function setDebugMode(bool $debugMode): self
    {
        $this->debugMode = $debugMode;
        return $this;
    }

    /**
     * @return bool
     */
    public function getUseMolliePaymentMethodLimits(): bool
    {
        return (bool)$this->useMolliePaymentMethodLimits;
    }

    /**
     * @param bool $useMolliePaymentMethodLimits
     * @return self
     */
    public function setUseMolliePaymentMethodLimits(bool $useMolliePaymentMethodLimits): self
    {
        $this->useMolliePaymentMethodLimits = $useMolliePaymentMethodLimits;
        return $this;
    }

    /**
     * @return string
     */
    public function getFormatOrderNumber(): string
    {
        return (string)$this->formatOrderNumber;
    }

    /**
     * @param string $formatOrderNumber
     */
    public function setFormatOrderNumber(string $formatOrderNumber): void
    {
        $this->formatOrderNumber = $formatOrderNumber;
    }

    /**
     * @return bool
     */
    public function getEnableCreditCardComponents()
    {
        return (bool)$this->enableCreditCardComponents;
    }

    /**
     * @param bool $enableCreditCardComponents
     *
     * @return self
     */
    public function setEnableCreditCardComponents(bool $enableCreditCardComponents): self
    {
        $this->enableCreditCardComponents = $enableCreditCardComponents;
        return $this;
    }

    /**
     * @return bool
     */
    public function isOneClickPaymentsEnabled(): bool
    {
        return $this->oneClickPaymentsEnabled;
    }

    /**
     * @param bool $oneClickPaymentsEnabled
     */
    public function setOneClickPaymentsEnabled(bool $oneClickPaymentsEnabled): void
    {
        $this->oneClickPaymentsEnabled = $oneClickPaymentsEnabled;
    }

    /**
     * @return bool
     */
    public function isOneClickPaymentsCompactView(): bool
    {
        return $this->oneClickPaymentsCompactView;
    }

    /**
     * @param bool $oneClickPaymentsCompactView
     *
     * @return self
     */
    public function setOneClickPaymentsCompactView(bool $oneClickPaymentsCompactView): self
    {
        $this->oneClickPaymentsCompactView = $oneClickPaymentsCompactView;
        return $this;
    }

    /**
     * @return int
     */
    public function getPaymentMethodBankTransferDueDateDays(): ?int
    {
        if (!$this->paymentMethodBankTransferDueDateDays) {
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
     * @throws Exception
     * @return null|string
     */
    public function getPaymentMethodBankTransferDueDate(): ?string
    {
        $dueDate = $this->getPaymentMethodBankTransferDueDateDays();
        if (!$dueDate) {
            return null;
        }

        return (new DateTime())
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $dueDate))
            ->format('Y-m-d');
    }

    /**
     * @return ?int
     */
    public function getOrderLifetimeDays(): ?int
    {
        if (!$this->orderLifetimeDays) {
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
     * @throws Exception
     * @return string
     */
    public function getOrderLifetimeDate(): ?string
    {
        $orderLifeTimeDays = $this->getOrderLifetimeDays();
        if (!$orderLifeTimeDays) {
            return null;
        }
        return (new DateTime())
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('+%d day', $orderLifeTimeDays))
            ->format('Y-m-d');
    }

    /**
     * @return string[]
     */
    public function getRestrictApplePayDirect(): array
    {
        return $this->applePayDirectRestrictions;
    }

    /**
     * @return string
     */
    public function getOrderStateWithAPaidTransaction(): string
    {
        return (string)$this->orderStateWithAPaidTransaction;
    }

    /**
     * @return string
     */
    public function getOrderStateWithAFailedTransaction(): string
    {
        return (string)$this->orderStateWithAFailedTransaction;
    }

    /**
     * @return string
     */
    public function getOrderStateWithACancelledTransaction(): string
    {
        return (string)$this->orderStateWithACancelledTransaction;
    }

    /**
     * @return string
     */
    public function getOrderStateWithAAuthorizedTransaction(): string
    {
        return (string)$this->orderStateWithAAuthorizedTransaction;
    }

    /**
     * @return string
     */
    public function getOrderStateWithAChargebackTransaction(): string
    {
        return $this->orderStateWithAChargebackTransaction;
    }

    /**
     * @param string $orderStateWithAChargebackTransaction
     */
    public function setOrderStateWithAChargebackTransaction(string $orderStateWithAChargebackTransaction): void
    {
        $this->orderStateWithAChargebackTransaction = $orderStateWithAChargebackTransaction;
    }


    /**
     * @return bool
     */
    public function isEnableApplePayDirect(): bool
    {
        return $this->enableApplePayDirect;
    }

    /**
     * @param bool $enableApplePayDirect
     */
    public function setEnableApplePayDirect(bool $enableApplePayDirect): void
    {
        $this->enableApplePayDirect = $enableApplePayDirect;
    }

    /**
     * @return bool
     */
    public function getAutomaticShipping(): bool
    {
        return (bool)$this->automaticShipping;
    }

    /**
     * @param bool $automaticShipping
     */
    public function setAutomaticShipping(bool $automaticShipping): void
    {
        $this->automaticShipping = $automaticShipping;
    }

    /**
     * @return bool
     */
    public function isAutomaticCancellation(): bool
    {
        return $this->automaticCancellation;
    }

    /**
     * @param bool $automaticCancellation
     */
    public function setAutomaticCancellation(bool $automaticCancellation): void
    {
        $this->automaticCancellation = $automaticCancellation;
    }

    /**
     * @return bool
     */
    public function isRefundManagerEnabled(): bool
    {
        return (bool)$this->refundManagerEnabled;
    }

    /**
     * @param bool $refundManagerEnabled
     */
    public function setRefundManagerEnabled(bool $refundManagerEnabled): void
    {
        $this->refundManagerEnabled = $refundManagerEnabled;
    }

    /**
     * @return bool
     */
    public function isRefundManagerVerifyRefund(): bool
    {
        return (bool)$this->refundManagerVerifyRefund;
    }

    /**
     * @param bool $refundManagerVerifyRefund
     */
    public function setRefundManagerVerifyRefund(bool $refundManagerVerifyRefund): void
    {
        $this->refundManagerVerifyRefund = $refundManagerVerifyRefund;
    }

    /**
     * @return bool
     */
    public function isRefundManagerAutoStockReset(): bool
    {
        return (bool)$this->refundManagerAutoStockReset;
    }

    /**
     * @param bool $refundManagerAutoStockReset
     */
    public function setRefundManagerAutoStockReset(bool $refundManagerAutoStockReset): void
    {
        $this->refundManagerAutoStockReset = $refundManagerAutoStockReset;
    }

    /**
     * @return bool
     */
    public function isRefundManagerShowInstructions(): bool
    {
        return (bool)$this->refundManagerShowInstructions;
    }

    /**
     * @param bool $refundManagerShowInstructions
     */
    public function setRefundManagerShowInstructions(bool $refundManagerShowInstructions): void
    {
        $this->refundManagerShowInstructions = $refundManagerShowInstructions;
    }

    /**
     * @return string
     */
    public function getOrderStateFinalState(): string
    {
        return (string)$this->orderStateFinalState;
    }

    /**
     * @param string $orderStateFinalState
     */
    public function setOrderStateFinalState(string $orderStateFinalState): void
    {
        $this->orderStateFinalState = $orderStateFinalState;
    }

    /**
     * @return string
     */
    public function getOrderStateWithPartialRefundTransaction(): string
    {
        return $this->orderStateWithPartialRefundTransaction;
    }

    /**
     * @param string $orderStateWithPartialRefundTransaction
     */
    public function setOrderStateWithPartialRefundTransaction(string $orderStateWithPartialRefundTransaction): void
    {
        $this->orderStateWithPartialRefundTransaction = $orderStateWithPartialRefundTransaction;
    }

    /**
     * @return string
     */
    public function getOrderStateWithRefundTransaction(): string
    {
        return $this->orderStateWithRefundTransaction;
    }

    /**
     * @param string $orderStateWithRefundTransaction
     */
    public function setOrderStateWithRefundTransaction(string $orderStateWithRefundTransaction): void
    {
        $this->orderStateWithRefundTransaction = $orderStateWithRefundTransaction;
    }

    /**
     * @return bool
     */
    public function isSubscriptionsShowIndicator(): bool
    {
        return (bool)$this->subscriptionsShowIndicator;
    }

    /**
     * @return int
     */
    public function getSubscriptionsReminderDays(): int
    {
        return (int)$this->subscriptionsReminderDays;
    }

    /**
     * @return int
     */
    public function getSubscriptionsCancellationDays(): int
    {
        return (int)$this->subscriptionsCancellationDays;
    }

    /**
     * @return bool
     */
    public function isSubscriptionsAllowAddressEditing(): bool
    {
        return (bool)$this->subscriptionsAllowAddressEditing;
    }

    /**
     * @param bool $subscriptionsAllowAddressEditing
     */
    public function setSubscriptionsAllowAddressEditing(bool $subscriptionsAllowAddressEditing): void
    {
        $this->subscriptionsAllowAddressEditing = $subscriptionsAllowAddressEditing;
    }

    /**
     * @return bool
     */
    public function isSubscriptionsAllowPauseResume(): bool
    {
        return (bool)$this->subscriptionsAllowPauseResume;
    }

    /**
     * @param bool $subscriptionsAllowPauseResume
     */
    public function setSubscriptionsAllowPauseResume(bool $subscriptionsAllowPauseResume): void
    {
        $this->subscriptionsAllowPauseResume = $subscriptionsAllowPauseResume;
    }

    /**
     * @return bool
     */
    public function isSubscriptionsAllowSkip(): bool
    {
        return (bool)$this->subscriptionsAllowSkip;
    }

    /**
     * @param bool $subscriptionsAllowSkip
     */
    public function setSubscriptionsAllowSkip(bool $subscriptionsAllowSkip): void
    {
        $this->subscriptionsAllowSkip = $subscriptionsAllowSkip;
    }

    /**
     * @return bool
     */
    public function isSubscriptionSkipRenewalsOnFailedPayments(): bool
    {
        return (bool)$this->subscriptionSkipRenewalsOnFailedPayments;
    }

    /**
     * @param bool $subscriptionSkipRenewalsOnFailedPayments
     */
    public function setSubscriptionSkipRenewalsOnFailedPayments(bool $subscriptionSkipRenewalsOnFailedPayments): void
    {
        $this->subscriptionSkipRenewalsOnFailedPayments = $subscriptionSkipRenewalsOnFailedPayments;
    }

    /**
     * @return bool
     */
    public function isSubscriptionsEnabled(): bool
    {
        return (bool)$this->subscriptionsEnabled;
    }

    /**
     * @param bool $subscriptionsEnabled
     */
    public function setSubscriptionsEnabled(bool $subscriptionsEnabled): void
    {
        $this->subscriptionsEnabled = $subscriptionsEnabled;
    }

    /**
     * @return bool
     */
    public function isFixRoundingDiffEnabled(): bool
    {
        return $this->fixRoundingDiffEnabled;
    }

    /**
     * @param bool $fixRoundingDiffEnabled
     */
    public function setFixRoundingDiffEnabled(bool $fixRoundingDiffEnabled): void
    {
        $this->fixRoundingDiffEnabled = $fixRoundingDiffEnabled;
    }

    /**
     * @return string
     */
    public function getFixRoundingDiffName(): string
    {
        return $this->fixRoundingDiffName;
    }

    /**
     * @param string $fixRoundingDiffName
     */
    public function setFixRoundingDiffName(string $fixRoundingDiffName): void
    {
        $this->fixRoundingDiffName = $fixRoundingDiffName;
    }

    /**
     * @return string
     */
    public function getFixRoundingDiffSKU(): string
    {
        return $this->fixRoundingDiffSKU;
    }

    /**
     * @param string $fixRoundingDiffSKU
     */
    public function setFixRoundingDiffSKU(string $fixRoundingDiffSKU): void
    {
        $this->fixRoundingDiffSKU = $fixRoundingDiffSKU;
    }

    /**
     * @return bool
     */
    public function isUseShopwareJavascript(): bool
    {
        return $this->useShopwareJavascript;
    }

    /**
     * @param bool $useShopwareJavascript
     */
    public function setUseShopwareJavascript(bool $useShopwareJavascript): void
    {
        $this->useShopwareJavascript = $useShopwareJavascript;
    }
}
