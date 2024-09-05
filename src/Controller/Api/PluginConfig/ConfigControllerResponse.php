<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConfigControllerResponse extends JsonResponse
{
    final private function __construct(bool $enabled, bool $autoStockReset, bool $verifyRefund, bool $showInstructions)
    {
        parent::__construct([
            'enabled' => $enabled,
            'autoStockReset' => $autoStockReset,
            'verifyRefund' => $verifyRefund,
            'showInstructions' => $showInstructions,
        ], self::HTTP_OK);
    }

    public static function createFromMollieSettingStruct(MollieSettingStruct $config): self
    {
        return self::createFromValues(
            $config->isRefundManagerEnabled(),
            $config->isRefundManagerAutoStockReset(),
            $config->isRefundManagerVerifyRefund(),
            $config->isRefundManagerShowInstructions()
        );
    }

    public static function createFromValues(bool $enabled, bool $autoStockReset, bool $verifyRefund, bool $showInstructions): self
    {
        return new self($enabled, $autoStockReset, $verifyRefund, $showInstructions);
    }
}
