<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaypalExpressCheckoutSubscriber implements EventSubscriberInterface
{
    public const MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID = 'molliePayPalEcsButtonData';

    /** @var SettingsService */
    private $settingsService;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    public function __construct(
        SettingsService $settingsService,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->settingsService = $settingsService;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            CheckoutRegisterPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            NavigationPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            OffcanvasCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            ProductPageLoadedEvent::class => 'addExpressCheckoutDataToPage',

            CmsPageLoadedEvent::class => 'addExpressCheckoutDataToCmsPage',
        ];
    }

    public function addExpressCheckoutDataToPage(PageLoadedEvent $event): void
    {
        $event->getPage()->addExtension(
            self::MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
            $this->paypalSettings($event->getSalesChannelContext())
        );
    }

    public function addExpressCheckoutDataToCmsPage(CmsPageLoadedEvent $event): void
    {
        /** @var CmsPageCollection $pages */
        $pages = $event->getResult();

        $cmsPage = $pages->first();

        if ($cmsPage === null) {
            return;
        }

        $cmsPage->addExtension(
            self::MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
            $this->paypalSettings($event->getSalesChannelContext())
        );
    }

    private function paypalSettings(SalesChannelContext $context): ArrayStruct
    {
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        $isAvailable = false;

        if($settings->isEnablePayPalShortcut()) {
            /** @var string[]|null $paymentMethodIds */
            $paymentMethodIds = $context->getSalesChannel()->getPaymentMethodIds();

            $paymentMethod = $this->getPaymentMethod(PayPalPayment::class, $context->getContext());

            $isAvailable = $paymentMethod->getActive() && in_array($paymentMethod->getId(), $paymentMethodIds);
        }

        return new ArrayStruct([
            'available' => $isAvailable,
            'color' => $settings->getPaypalShortcutButtonColor(),
            'label' => $settings->getPaypalShortcutButtonLabel(),
        ]);
    }

    /**
     * Returns a payment method by it's handler.
     *
     * @param string $handlerIdentifier
     * @param Context|null $context
     *
     * @return PaymentMethodEntity|null
     */
    private function getPaymentMethod(string $handlerIdentifier, Context $context = null): ?PaymentMethodEntity
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

            // Get payment methods
            return $this->paymentMethodRepository->search(
                $criteria,
                $context ?? Context::createDefaultContext()
            )->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
