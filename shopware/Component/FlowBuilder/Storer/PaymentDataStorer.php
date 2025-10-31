<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Storer;

use Mollie\Shopware\Component\FlowBuilder\Event\MolliePaymentAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Framework\Event\FlowEventAware;

final class PaymentDataStorer extends FlowStorer
{
    public function store(FlowEventAware $event, array $stored): array
    {
        if (! $event instanceof MolliePaymentAware || isset($stored[MolliePaymentAware::PAYMENT_STORAGE_KEY])) {
            return $stored;
        }
        $stored[MolliePaymentAware::PAYMENT_STORAGE_KEY] = $event->getPayment();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (! $storable->hasStore(MolliePaymentAware::PAYMENT_STORAGE_KEY)) {
            return;
        }
        $storable->setData(MolliePaymentAware::PAYMENT_STORAGE_KEY, $storable->getStore(MolliePaymentAware::PAYMENT_STORAGE_KEY));
    }
}
