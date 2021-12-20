<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber\Subscription;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    private EntityRepositoryInterface $paymentMethodRepository;

    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
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
            $args->getPage()->setPaymentMethods($this->getPaymentMethods());
        }
    }

    /**
     * @return PaymentMethodCollection
     */
    private function getPaymentMethods(): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customFields.mollie_payment_method_name', self::ALLOWED_METHODS));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
