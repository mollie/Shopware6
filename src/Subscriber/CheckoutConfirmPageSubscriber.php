<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Config\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    protected $config;

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
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param CheckoutConfirmPageLoadedEvent $args
     */
    public function addComponentsVariable(CheckoutConfirmPageLoadedEvent $args)
    {
        $args->getPage()->assign([
            'enable_credit_card_components' => $this->config::enableCreditCardComponents()
        ]);
    }
}