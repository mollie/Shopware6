<?php

namespace Kiener\MolliePayments\Setting;

use DateTime;
use DateTimeZone;
use Exception;
use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
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
    protected $shopwareFailedPayment = false;

    /**
     * @var bool
     */
    protected $createCustomersAtMollie = true;

    /**
     * @var bool
     */
    protected $enableCreditCardComponents = false;

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
     * @return string|null
     */
    public function getProfileId(): ?string
    {
        return $this->profileId ?? ($this->isTestMode() ? $this->testProfileId : $this->liveProfileId);
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
    public function isShopwareFailedPaymentMethod(): bool
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
     * @return string|null
     * @throws Exception
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
     * @return string
     * @throws Exception
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
}
