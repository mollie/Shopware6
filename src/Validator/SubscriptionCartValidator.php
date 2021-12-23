<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Validator;

use Kiener\MolliePayments\Exception\SubscriptionCartNotice;
use Kiener\MolliePayments\Service\ConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionCartValidator implements CartValidatorInterface
{
    /**
     * @var ConfigService
     */
    private $settings;

    /**
     * @param ConfigService $settings
     */
    public function __construct(ConfigService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param Cart $cart
     * @param ErrorCollection $errorCollection
     * @param SalesChannelContext $salesChannelContext
     */
    public function validate(
        Cart $cart,
        ErrorCollection $errorCollection,
        SalesChannelContext $salesChannelContext
    ): void {
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $customFields = $lineItem->getPayload()['customFields'];
            if (!isset($customFields["mollie_subscription"])) {
                continue;
            }

            if ($lineItem->getType() == LineItem::PRODUCT_LINE_ITEM_TYPE
                && count($customFields["mollie_subscription"]) > 0) {
                if ($this->settings->get(ConfigService::ENABLE_SUBSCRIPTION)
                    && $customFields["mollie_subscription"]["mollie_subscription_product"]) {
                    $errorCollection->add(new SubscriptionCartNotice($lineItem->getId()));
                }
                return;
            }
        }
    }
}
