<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Gateways;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayDirectDomainAllowListItem;
use Kiener\MolliePayments\Components\ApplePayDirect\Models\Collections\ApplePayDirectDomainAllowList;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayDirectDomainAllowListGateway
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * ApplePayDirectDomainAllowListItem constructor.
     *
     * @param SettingsService $settingsService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get the ApplePayDirectDomainAllowList
     *
     * @param SalesChannelContext $context
     * @return ApplePayDirectDomainAllowList
     */
    public function getAllowList(SalesChannelContext $context): ApplePayDirectDomainAllowList
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());
        $allowList = $settings->applePayDirectDomainAllowList ?? '';

        if (empty($allowList) || !is_string($allowList)) {
            return ApplePayDirectDomainAllowList::create();
        }

        $allowList = trim($allowList);

        $items = explode(',', $allowList);
        $items = array_map([ApplePayDirectDomainAllowListItem::class, 'create'], $items);

        return ApplePayDirectDomainAllowList::create(...$items);
    }
}
