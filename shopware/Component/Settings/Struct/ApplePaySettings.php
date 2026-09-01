<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class ApplePaySettings extends Struct
{
    use JsonSerializableTrait;
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
        $visibilityRestrictions = VisibilityRestrictionCollection::fromArray($visibilityRestrictionsArray);
        $allowedDomainList = (string) ($settings[self::KEY_ALLOWED_DOMAIN_LIST] ?? '');

        return new self($applePayDirectEnabled, $visibilityRestrictions, self::parseDomainList($allowedDomainList));
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

    /**
     * Merchants separate the domains with commas and usually put a space behind each one.
     * Without trimming, the untrimmed entry never matches the requested domain and Apple Pay
     * refuses the session.
     *
     * @return string[]
     */
    private static function parseDomainList(string $allowedDomainList): array
    {
        $configuredDomains = explode(',', $allowedDomainList);
        $domains = [];

        foreach ($configuredDomains as $domain) {
            $domain = trim($domain);
            if (strlen($domain) === 0) {
                continue;
            }
            $domains[] = $domain;
        }

        return $domains;
    }
}
