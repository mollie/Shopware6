<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CartBackupService extends AbstractCartBackupService
{
    private const BACKUP_TOKEN = 'mollie_backup_%s';

    /**
     * @var array<string, bool>
     */
    private array $existingBackups = [];

    public function __construct(private CartService $cartService,
        #[Autowire(service: CartPersister::class)]
        private AbstractCartPersister $cartPersister)
    {
    }

    public function getDecorated(): AbstractCartBackupService
    {
        throw new DecorationPatternException(self::class);
    }

    public function backupCart(SalesChannelContext $context): void
    {
        $originalCart = $this->cartService->getCart($context->getToken(), $context);

        // do not backup empty carts
        if ($originalCart->getLineItems()->count() <= 0) {
            return;
        }

        // create new cart with our backup token
        $newCart = $this->cartService->createNew($this->getToken($context->getToken()));

        // assign our items to the backup
        // this is the only thing we really need to backup at this stage.
        // the rest (deliveries, etc.) are usually set in the checkout process anyway.
        $newCart->setLineItems($originalCart->getLineItems());

        // set and persist
        $this->cartService->setCart($newCart);
        $this->cartService->recalculate($newCart, $context);
    }

    public function restoreCart(SalesChannelContext $context): Cart
    {
        // empty carts do not exist in Shopware
        // so restoring such a cart just means use the existing and clear the line items
        if (! $this->isBackupExisting($context)) {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            $cart->setLineItems(new LineItemCollection());

            return $cart;
        }

        // get our backup cart
        $backupCart = $this->cartService->getCart($this->getToken($context->getToken()), $context);

        // create a new "old" original cart (to avoid foreign reference problems)
        // and set the items from our backup
        $newCart = $this->cartService->createNew($context->getToken());
        $newCart->setLineItems($backupCart->getLineItems());

        // set and persist
        $this->cartService->setCart($newCart);

        return $this->cartService->recalculate($newCart, $context);
    }

    public function clearBackup(SalesChannelContext $context): void
    {
        $backupCart = $this->cartService->getCart($this->getToken($context->getToken()), $context);

        // removing does not really work
        // but we can set the item count to 0, which means "not existing" for us
        $backupCart->setLineItems(new LineItemCollection());

        // set and persist
        $this->cartService->setCart($backupCart);
        $this->cartService->recalculate($backupCart, $context);
    }

    public function replaceToken(string $oldToken, string $currentToken, SalesChannelContext $context): void
    {
        $oldToken = $this->getToken($oldToken);
        $currentToken = $this->getToken($currentToken);
        $this->cartPersister->replace($oldToken, $currentToken, $context);
    }

    public function isBackupExisting(SalesChannelContext $context): bool
    {
        $backupToken = $this->getToken($context->getToken());

        if (isset($this->existingBackups[$backupToken])) {
            return true;
        }

        try {
            $this->cartPersister->load($backupToken, $context);
            // we just assign true to save memory
            $this->existingBackups[$backupToken] = true;

            return true;
        } catch (\Throwable $exception) {
        }

        return false;
    }

    private function getToken(string $token): string
    {
        return sprintf(self::BACKUP_TOKEN, $token);
    }
}
