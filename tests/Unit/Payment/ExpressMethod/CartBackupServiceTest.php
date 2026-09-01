<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Payment\ExpressMethod\CartBackupService;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Fake\FakeCartPersister;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * An express checkout empties the shopper's cart to run the payment on its own items, so the
 * original cart is parked under a second token and put back afterwards. Losing it means the
 * shopper returns from a cancelled wallet payment to an empty cart.
 */
#[CoversClass(CartBackupService::class)]
final class CartBackupServiceTest extends TestCase
{
    private const CART_TOKEN = 'cart-token';
    private const BACKUP_TOKEN = 'mollie_backup_cart-token';

    private FakeCartPersister $cartPersister;

    protected function setUp(): void
    {
        $this->cartPersister = new FakeCartPersister();
    }

    public function testTheBackupCarriesTheItemsOfTheOriginalCart(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN, 'shirt', 'trousers'));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $backupService->backupCart($this->context());

        $this->assertSame(self::BACKUP_TOKEN, $cartService->getLastSetCart()->getToken());
        $this->assertCount(2, $cartService->getLastSetCart()->getLineItems());
    }

    public function testAnEmptyCartIsNotBackedUp(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $backupService->backupCart($this->context());

        $this->assertSame(0, $cartService->getSetCartCount());
    }

    public function testRestoringPutsTheBackedUpItemsBackOnTheOriginalToken(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $cartService->addCart(self::BACKUP_TOKEN, $this->cart(self::BACKUP_TOKEN, 'shirt'));
        $this->cartPersister->addStoredCart(self::BACKUP_TOKEN, $this->cart(self::BACKUP_TOKEN, 'shirt'));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $restoredCart = $backupService->restoreCart($this->context());

        $this->assertSame(self::CART_TOKEN, $restoredCart->getToken());
        $this->assertCount(1, $restoredCart->getLineItems());
    }

    /**
     * Shopware has no empty cart, so without a backup the shopper keeps the cart they have -
     * cleared of the items the express checkout put in.
     */
    public function testRestoringWithoutABackupClearsTheCurrentCart(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN, 'express-item'));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $restoredCart = $backupService->restoreCart($this->context());

        $this->assertSame(self::CART_TOKEN, $restoredCart->getToken());
        $this->assertCount(0, $restoredCart->getLineItems());
    }

    public function testClearingTheBackupEmptiesIt(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $cartService->addCart(self::BACKUP_TOKEN, $this->cart(self::BACKUP_TOKEN, 'shirt'));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $backupService->clearBackup($this->context());

        $this->assertSame(self::BACKUP_TOKEN, $cartService->getLastSetCart()->getToken());
        $this->assertCount(0, $cartService->getLastSetCart()->getLineItems());
    }

    /**
     * A guest that logs in during the express checkout gets a new cart token, and the backup
     * has to move with it or it can never be found again.
     */
    public function testReplacingTheTokenMovesTheBackupToTheNewToken(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $backupService->replaceToken('old-token', 'new-token', $this->context());

        $this->assertSame(
            [['oldToken' => 'mollie_backup_old-token', 'newToken' => 'mollie_backup_new-token']],
            $this->cartPersister->getReplacedTokens()
        );
    }

    public function testABackupIsFoundWhenACartIsStoredUnderTheBackupToken(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $this->cartPersister->addStoredCart(self::BACKUP_TOKEN, $this->cart(self::BACKUP_TOKEN, 'shirt'));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $this->assertTrue($backupService->isBackupExisting($this->context()));
    }

    public function testNoBackupIsFoundWithoutAStoredCart(): void
    {
        $cartService = new FakeCartService($this->cart(self::CART_TOKEN));
        $backupService = new CartBackupService($cartService, $this->cartPersister);

        $this->assertFalse($backupService->isBackupExisting($this->context()));
    }

    public function testTheServiceCannotBeDecorated(): void
    {
        $backupService = new CartBackupService(new FakeCartService($this->cart(self::CART_TOKEN)), $this->cartPersister);

        $this->expectException(DecorationPatternException::class);

        $backupService->getDecorated();
    }

    private function cart(string $token, string ...$lineItemIds): Cart
    {
        $cartBuilder = CartBuilder::create()->withToken($token);
        foreach ($lineItemIds as $lineItemId) {
            $cartBuilder->withLineItem(LineItemBuilder::regular($lineItemId)->build());
        }

        return $cartBuilder->build();
    }

    private function context(): FakeSalesChannelContext
    {
        return new FakeSalesChannelContext(token: self::CART_TOKEN);
    }
}
