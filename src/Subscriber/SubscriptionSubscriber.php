<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Storefront\Struct\SubscriptionCartExtensionStruct;
use Kiener\MolliePayments\Storefront\Struct\SubscriptionDataExtensionStruct;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var TranslatorInterface
     */
    private $translator;


    /**
     * @param SettingsService $settingsService
     * @param TranslatorInterface $translator
     */
    public function __construct(SettingsService $settingsService, TranslatorInterface $translator)
    {
        $this->settingsService = $settingsService;
        $this->translator = $translator;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            CartBeforeSerializationEvent::class => 'onBeforeSerializeCart',
            # ------------------------------------------------------------------------
            StorefrontRenderEvent::class => 'onStorefrontRender',
            ProductPageLoadedEvent::class => 'addSubscriptionData',
            CheckoutConfirmPageLoadedEvent::class => 'addSubscriptionData',
        ];
    }

    /**
     * this is required to allow our custom fields
     * if we don't add them in here, then they will be removed for cart lineItems
     * https://github.com/shopware/platform/blob/trunk/UPGRADE-6.5.md
     *
     * @param CartBeforeSerializationEvent $event
     * @return void
     */
    public function onBeforeSerializeCart(CartBeforeSerializationEvent $event): void
    {
        $allowed = $event->getCustomFieldAllowList();

        foreach (LineItemAttributes::getKeyList() as $key) {
            $allowed[] = $key;
        }

        $event->setCustomFieldAllowList($allowed);
    }

    /**
     * @param StorefrontRenderEvent $event
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $event->setParameter('mollie_subscriptions_enabled', $settings->isSubscriptionsEnabled());
    }

    /**
     * @param PageLoadedEvent $event
     * @return void
     */
    public function addSubscriptionData(PageLoadedEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());


        if (!$settings->isSubscriptionsEnabled()) {
            $struct = new SubscriptionDataExtensionStruct(
                false,
                '',
                false
            );

            $event->getPage()->addExtension('mollieSubscription', $struct);
            return;
        }


        $page = $event->getPage();


        if ($page instanceof ProductPage) {
            $product = $page->getProduct();
            $productAttributes = new ProductAttributes($product);

            $isSubscription = $productAttributes->isSubscriptionProduct();

            # only load our data if we really
            # have a subscription product
            if ($isSubscription) {
                $interval = (int)$productAttributes->getSubscriptionInterval();
                $unit = (string)$productAttributes->getSubscriptionIntervalUnit();
                $repetition = (int)$productAttributes->getSubscriptionRepetitionCount();
                $translatedInterval = $this->getTranslatedInterval($interval, $unit, $repetition);
                $showIndicator = $settings->isSubscriptionsShowIndicator();
            } else {
                $translatedInterval = '';
                $showIndicator = false;
            }

            $struct = new SubscriptionDataExtensionStruct(
                $isSubscription,
                $translatedInterval,
                $showIndicator
            );

            $event->getPage()->addExtension('mollieSubscription', $struct);
            return;
        }


        if ($page instanceof CheckoutConfirmPage) {
            $subscriptionFound = false;

            foreach ($page->getCart()->getLineItems()->getFlat() as $lineItem) {
                $lineItemAttributes = new LineItemAttributes($lineItem);

                $isSubscription = $lineItemAttributes->isSubscriptionProduct();

                if ($isSubscription) {
                    $subscriptionFound = true;

                    $interval = (int)$lineItemAttributes->getSubscriptionInterval();
                    $unit = (string)$lineItemAttributes->getSubscriptionIntervalUnit();
                    $repetition = (int)$lineItemAttributes->getSubscriptionRepetition();

                    $translatedInterval = $this->getTranslatedInterval($interval, $unit, $repetition);

                    $struct = new SubscriptionDataExtensionStruct(
                        $isSubscription,
                        $translatedInterval,
                        false
                    );

                    $lineItem->addExtension('mollieSubscription', $struct);
                }
            }

            # we need this for some checks on the cart
            $cartStruct = new SubscriptionCartExtensionStruct($subscriptionFound);
            $event->getPage()->addExtension('mollieSubscriptionCart', $cartStruct);
        }
    }

    /**
     * @param int $interval
     * @param string $unit
     * @param int $repetition
     * @return string
     */
    private function getTranslatedInterval(int $interval, string $unit, int $repetition): string
    {
        $snippetKey = '';

        switch ($unit) {
            case IntervalType::DAYS:
                {
                    if ($interval === 1) {
                        $snippetKey = 'molliePayments.subscriptions.options.everyDay';
                    } else {
                        $snippetKey = 'molliePayments.subscriptions.options.everyDays';
                    }
                }
                break;

            case IntervalType::WEEKS:
                {
                    if ($interval === 1) {
                        $snippetKey = 'molliePayments.subscriptions.options.everyWeek';
                    } else {
                        $snippetKey = 'molliePayments.subscriptions.options.everyWeeks';
                    }
                }
                break;

            case IntervalType::MONTHS:
                {
                    if ($interval === 1) {
                        $snippetKey = 'molliePayments.subscriptions.options.everyMonth';
                    } else {
                        $snippetKey = 'molliePayments.subscriptions.options.everyMonths';
                    }
                }
                break;
        }

        $mainText = $this->translator->trans($snippetKey, ['%value%' => $interval]);

        if ($repetition >= 1) {
            $mainText .= ', ' . $this->translator->trans('molliePayments.subscriptions.options.repetitionCount', ['%value%' => $repetition]);
        }

        return $mainText;
    }
}
