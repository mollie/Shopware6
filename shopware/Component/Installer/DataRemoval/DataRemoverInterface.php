<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A single, self-contained step of the Mollie data removal that runs on uninstall (when the
 * merchant chose to remove all data) or via the mollie:delete-data command. Every implementer is
 * auto-tagged and collected by {@see \Mollie\Shopware\Component\Installer\MollieDataRemover}.
 */
#[AutoconfigureTag('mollie.data_remover')]
interface DataRemoverInterface
{
    public function remove(Context $context): void;
}
