<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber\Subscription;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    public const ALLOWED_METHODS = [
        'bancontact',
        'ideal',
        'sofort',
        'belfius',
        'kbc',
        'giropay',
        'eps',
    ];

    /**
     * @var AbstractPaymentMethodRoute
     */
    private AbstractPaymentMethodRoute $paymentMethodRoute;

    /**
     * @param AbstractPaymentMethodRoute $paymentMethodRoute
     */
    public function __construct(AbstractPaymentMethodRoute $paymentMethodRoute)
    {
        $this->paymentMethodRoute = $paymentMethodRoute;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addDataToPage'
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $args
     */
    public function addDataToPage(CheckoutConfirmPageLoadedEvent $args): void
    {
        $mollieSubscriptions = false;
        $products = $args->getPage()->getCart('elements')->getLineItems();

        foreach ($products->getElements() as $product) {
            $customFields = $product->getPayload()['customFields'];
            if (isset($customFields["mollie_subscription"]['mollie_subscription_product'])
                && $customFields["mollie_subscription"]['mollie_subscription_product']) {
                $mollieSubscriptions = true;
            }
        }

        if ($mollieSubscriptions) {
            $args->getPage()->setPaymentMethods($this->getPaymentMethods($args->getSalesChannelContext()));
        }
    }

    /**
     * @param SalesChannelContext $context
     * @return PaymentMethodCollection
     */
    private function getPaymentMethods(SalesChannelContext $context): PaymentMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customFields.mollie_payment_method_name', self::ALLOWED_METHODS));

        return $this->paymentMethodRoute->load($request, $context, $criteria)->getPaymentMethods();
    }
}
