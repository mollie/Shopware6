<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Gateways;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayValidationUrlAllowListItem;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections\ApplePayValidationUrlAllowList;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ApplePayValidationUrlAllowListGateway
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * ApplePayValidationUrlAllowListItem constructor.
     *
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Get the ApplePayValidationUrlAllowList
     *
     * @return ApplePayValidationUrlAllowList
     */
    public function getAllowList(): ApplePayValidationUrlAllowList
    {
        $allowList = $this->systemConfigService->get('MolliePayments.config.ApplePayValidationAllowList');

        if (is_string($allowList) === false || empty($allowList)) {
            return ApplePayValidationUrlAllowList::create();
        }

        $allowList = trim($allowList);

        $items = explode(',', $allowList);
        $items = array_map([ApplePayValidationUrlAllowListItem::class, 'create'], $items);

        return ApplePayValidationUrlAllowList::create(...$items);
    }
}
