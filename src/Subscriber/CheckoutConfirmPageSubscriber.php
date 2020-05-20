<?php

namespace Kiener\MolliePayments\Subscriber;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /** @var SettingsService */
    private $settingsService;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addComponentsVariable'
        ];
    }

    /**
     * Creates a new instance of the checkout confirm page subscriber.
     *
     * @param ConfigService $configService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param CheckoutConfirmPageLoadedEvent $args
     */
    public function addComponentsVariable(CheckoutConfirmPageLoadedEvent $args)
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $args->getSalesChannelContext()->getSalesChannel()->getId(),
            $args->getContext()
        );

        $args->getPage()->assign([
            'enable_credit_card_components' => $settings->getEnableCreditCardComponents()
        ]);
    }
}