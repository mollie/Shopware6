<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Storefront\Struct\SubscriptionDataExtensionStruct;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
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
            ProductPageLoadedEvent::class => 'addSubscriptionData',
            CheckoutConfirmPageLoadedEvent::class => 'addSubscriptionData',
        ];
    }


    /**
     * @param PageLoadedEvent $event
     * @return void
     */
    public function addSubscriptionData(PageLoadedEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $page = $event->getPage();


        if ($page instanceof ProductPage) {

            $product = $event->getPage()->getProduct();
            $productAttributes = new ProductAttributes($product);

            $interval = (int)$productAttributes->getSubscriptionInterval();
            $unit = (string)$productAttributes->getSubscriptionIntervalUnit();
            $repetition = (int)$productAttributes->getSubscriptionRepetitionCount();

            $translatedInterval = $this->getTranslatedInterval($interval, $unit, $repetition);

            $showIndicator = $settings->isSubscriptionsShowIndicator();

            $struct = new SubscriptionDataExtensionStruct(
                true,
                $translatedInterval,
                $showIndicator
            );

            $event->getPage()->addExtension('mollieSubscription', $struct);
            return;
        }


        if ($page instanceof CheckoutConfirmPage) {

            foreach ($page->getCart()->getLineItems()->getFlat() as $lineItem) {

                $lineItemAttributes = new LineItemAttributes($lineItem);

                if ($lineItemAttributes->isSubscriptionProduct()) {

                    $interval = (int)$lineItemAttributes->getSubscriptionInterval();
                    $unit = (string)$lineItemAttributes->getSubscriptionIntervalUnit();
                    $repetition = (int)$lineItemAttributes->getSubscriptionRepetition();

                    $translatedInterval = $this->getTranslatedInterval($interval, $unit, $repetition);

                    $struct = new SubscriptionDataExtensionStruct(true, $translatedInterval);

                    $lineItem->addExtension('mollieSubscription', $struct);
                }
            }
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

            case IntervalType::WEEKS;
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
