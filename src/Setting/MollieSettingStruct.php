<?php

namespace Kiener\MolliePayments\Setting;

use Shopware\Core\Framework\Struct\Struct;

class MollieSettingStruct extends Struct
{
    /**
     * @var string
     */
    protected $liveApiKey;

    /**
     * @var string
     */
    protected $testApiKey;

    /**
     * @var bool
     */
    protected $testMode = true;

    /**
     * @return string
     */
    public function getLiveApiKey() : string
    {
        return $this->liveApiKey;
    }

    /**
     * @param string $liveApiKey
     * @return MollieSettingStruct
     */
    public function setLiveApiKey(string $liveApiKey) : MollieSettingStruct
    {
        $this->liveApiKey = $liveApiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getTestApiKey() : string
    {
        return $this->testApiKey;
    }

    /**
     * @param string $testApiKey
     * @return MollieSettingStruct
     */
    public function setTestApiKey(string $testApiKey) : MollieSettingStruct
    {
        $this->testApiKey = $testApiKey;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTestMode() : bool
    {
        return $this->testMode;
    }

    /**
     * @param bool $testMode
     * @return MollieSettingStruct
     */
    public function setTestMode(bool $testMode) : MollieSettingStruct
    {
        $this->testMode = $testMode;
        return $this;
    }
}