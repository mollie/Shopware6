<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Mollie\Shopware\Component\ItemFilter\Subscriber\CustomizedProductsSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\DreiscProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\EasyCouponSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\GiftConfiguratorSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\NetiBundleSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\RepertusProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\SwkwebProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\ZeobvBundleSubscriber;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A filter wired with the same subscribers the container registers, so a test sees the payload a
 * shop would send. A new subscriber under Component/ItemFilter has to be added here as well.
 */
final class LineItemFilterBuilder
{
    public static function build(): LineItemFilter
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new RepertusProductSetSubscriber());
        $eventDispatcher->addSubscriber(new DreiscProductSetSubscriber());
        $eventDispatcher->addSubscriber(new SwkwebProductSetSubscriber());
        $eventDispatcher->addSubscriber(new CustomizedProductsSubscriber());
        $eventDispatcher->addSubscriber(new ZeobvBundleSubscriber());
        $eventDispatcher->addSubscriber(new NetiBundleSubscriber());
        $eventDispatcher->addSubscriber(new GiftConfiguratorSubscriber());
        $eventDispatcher->addSubscriber(new EasyCouponSubscriber());

        return new LineItemFilter($eventDispatcher, new FakeLogger());
    }
}
