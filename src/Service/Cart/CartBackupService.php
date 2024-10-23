<?php

namespace Kiener\MolliePayments\Service\Cart;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartBackupService
{
    /**
     *
     */
    private const BACKUP_TOKEN = 'mollie_backup_%s';

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractCartPersister
     */
    private $cartPersister;
    /**
     * @var array<string, bool>
     */
    private array $backupExistingCache;

    /**
     * @param CartService $cartService
     */
    public function __construct(CartService $cartService, AbstractCartPersister $cartPersister)
    {
        $this->cartService = $cartService;

        $this->cartPersister = $cartPersister;
    }


    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function isBackupExisting(SalesChannelContext $context): bool
    {
        $token = $this->getToken($context->getToken());
        $value = false;

        if (isset($this->backupExistingCache[$token])) {
            return $this->backupExistingCache[$token];
        }
        try {
            $backupCart = $this->cartPersister->load($token, $context);
            $value = $backupCart->getLineItems()->count() > 0;
        } catch (\Throwable $exception) {
        }
        $this->backupExistingCache[$token] = $value;

        return $this->backupExistingCache[$token];
    }

    private function getToken(string $token): string
    {
        return sprintf(self::BACKUP_TOKEN, $token);
    }

    /**
     * @param SalesChannelContext $context
     */
    public function backupCart(SalesChannelContext $context): void
    {
        $originalCart = $this->cartService->getCart($context->getToken(), $context);

        # create new cart with our backup token
        $newCart = $this->cartService->createNew($this->getToken($context->getToken()));

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
        $backupCart = $this->cartService->getCart($this->getToken($context->getToken()), $context);

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
        $backupCart = $this->cartService->getCart($this->getToken($context->getToken()), $context);

        # removing does not really work
        # but we can set the item count to 0, which means "not existing" for us
        $backupCart->setLineItems(new LineItemCollection());

        # set and persist
        $this->cartService->setCart($backupCart);
        $this->cartService->recalculate($backupCart, $context);
    }

    public function replaceToken(string $oldToken, string $currentToken, SalesChannelContext $context): void
    {
        $oldToken = $this->getToken($oldToken);
        $currentToken = $this->getToken($currentToken);
        $this->cartPersister->replace($oldToken, $currentToken, $context);
    }
}
