<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Shopware\Core\Framework\Struct\Struct;

final class ApplePaySettings extends Struct
{
    public const KEY_APPLE_PAY_DIRECT_ENABLED = 'enableApplePayDirect';
    public const KEY_RESTRICTIONS = 'applePayDirectRestrictions';

    public const KEY_ALLOWED_DOMAIN_LIST = 'applePayDirectDomainAllowList';

    /**
     * @param string[] $allowDomainList
     */
    public function __construct(private bool $applePayDirectEnabled, private VisibilityRestrictionCollection $visibilityRestrictions, private array $allowDomainList)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $applePayDirectEnabled = $settings[self::KEY_APPLE_PAY_DIRECT_ENABLED] ?? false;
        $visibilityRestrictionsArray = $settings[self::KEY_RESTRICTIONS] ?? [];
        $visibilityRestrictions = new VisibilityRestrictionCollection();
        foreach ($visibilityRestrictionsArray as $visibilityRestriction) {
            $visibilityRestrictions->add(VisibilityRestriction::from($visibilityRestriction));
        }
        $allowedDomainList = $settings[self::KEY_ALLOWED_DOMAIN_LIST] ?? '';
        $allowedDomainListArray = explode(',', $allowedDomainList);

        return new self($applePayDirectEnabled,$visibilityRestrictions,$allowedDomainListArray);
    }

    public function isApplePayDirectEnabled(): bool
    {
        return $this->applePayDirectEnabled;
    }

    public function getVisibilityRestrictions(): VisibilityRestrictionCollection
    {
        return $this->visibilityRestrictions;
    }

    /**
     * @return string[]
     */
    public function getAllowDomainList(): array
    {
        return $this->allowDomainList;
    }
}
