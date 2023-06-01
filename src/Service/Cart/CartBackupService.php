<?php

namespace Kiener\MolliePayments\Service\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartBackupService
{
    /**
     *
     */
    private const BACKUP_TOKEN = 'mollie_backup';

    /**
     * @var CartService
     */
    private $cartService;


    /**
     * @param CartService $cartService
     */
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }


    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function isBackupExisting(SalesChannelContext $context): bool
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        return ($backupCart->getLineItems()->count() > 0);
    }

    /**
     * @param SalesChannelContext $context
     */
    public function backupCart(SalesChannelContext $context): void
    {
        $originalCart = $this->cartService->getCart($context->getToken(), $context);

        # additional language shops do not have a name, so make sure it has a string cast
        $salesChannelName = (string)$context->getSalesChannel()->getName();

        # create new cart with our backup token
        $newCart = $this->cartService->createNew(self::BACKUP_TOKEN);

        # assign our items to the backup
        # this is the only thing we really need to backup at this stage.
        # the rest (deliveries, etc.) are usually set in the checkout process anyway.
        $newCart->setLineItems($originalCart->getLineItems());

        # set and persist
        $this->cartService->setCart($newCart);
        $this->cartService->recalculate($newCart, $context);
    }

    /**
     * @param SalesChannelContext $context
     * @return Cart
     */
    public function restoreCart(SalesChannelContext $context): Cart
    {
        # get our backup cart
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        # create a new "old" original cart (to avoid foreign reference problems)
        # and set the items from our backup
        $newCart = $this->cartService->createNew($context->getToken());
        $newCart->setLineItems($backupCart->getLineItems());

        # set and persist
        $this->cartService->setCart($newCart);
        $newCart = $this->cartService->recalculate($newCart, $context);

        return $newCart;
    }

    /**
     * @param SalesChannelContext $context
     */
    public function clearBackup(SalesChannelContext $context): void
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        # removing does not really work
        # but we can set the item count to 0, which means "not existing" for us
        $backupCart->setLineItems(new LineItemCollection());

        # set and persist
        $this->cartService->setCart($backupCart);
        $this->cartService->recalculate($backupCart, $context);
    }
}
