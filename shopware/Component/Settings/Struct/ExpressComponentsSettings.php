<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class ExpressComponentsSettings extends Struct
{
    use JsonSerializableTrait;
    public const KEY_RESTRICTIONS = 'expressComponentsRestrictions';

    private VisibilityRestrictionCollection $restrictions;

    public function __construct(private bool $enabled)
    {
        $this->restrictions = new VisibilityRestrictionCollection();
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getRestrictions(): VisibilityRestrictionCollection
    {
        return $this->restrictions;
    }

    public function setRestrictions(VisibilityRestrictionCollection $restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }
}
