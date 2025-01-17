<?php

namespace Kiener\MolliePayments\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

class TestModePageExtensionStruct extends Struct
{
    /**
     * @var bool
     */
    protected $testMode = true;

    public function __construct(bool $testMode)
    {
        $this->testMode = $testMode;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }
}
