<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\AddProductStruct;
use Kiener\MolliePayments\Service\Cart\CartBackupService;
use Kiener\MolliePayments\Service\CartService;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddProductRoute
{

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;


    /**
     * @param CartService $cartService
     * @param CartBackupService $cartBackupService
     */
    public function __construct(CartService $cartService, CartBackupService $cartBackupService)
    {
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
    }


    /**
     * @param string $productId
     * @param int $quantity
     * @param SalesChannelContext $context
     * @return AddProductStruct
     * @throws \Exception
     */
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): AddProductStruct
    {

        if (empty($productId)) {
            throw new \Exception('Please provide a product ID!');
        }

        if ($quantity <= 0) {
            throw new \Exception('Please provide a valid quantity > 0!');
        }

        # if we already have a backup cart, then do NOT backup again.
        # because this could backup our temp. apple pay cart
        if (!$this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->backupCart($context);
        }


        $cart = $this->cartService->getCalculatedMainCart($context);

        # clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());
        $this->cartService->updateCart($cart);

        # add new product to cart
        $cart = $this->cartService->addProduct($productId, $quantity, $context);

        return new AddProductStruct($cart);
    }

}
