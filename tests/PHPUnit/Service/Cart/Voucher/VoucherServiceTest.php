<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Cart\Voucher;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use MolliePayments\Tests\Fakes\Repositories\FakeProductRepository;
use MolliePayments\Tests\Traits\MockTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherServiceTest extends TestCase
{
    use MockTrait;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
    }

    /**
     * This test verifies that line items with invalid product numbers
     * just return NO voucher type at all.
     */
    public function testInvalidProductNumber()
    {
        // build a repo that would return nothing...just in case ;)
        $fakeRepoProducts = new FakeProductRepository(null, null);

        // build a product line item that
        // has no valid product number...
        $item = $this->buildProductLineItem('');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $voucherType = $vouchers->getFinalVoucherType($item, $this->salesChannelContext);

        $this->assertEquals(VoucherType::TYPE_NOTSET, $voucherType);
    }

    /**
     * This test verifies that we do NOT get an exception if the
     * product numbers would be valid, but no product has been found.
     */
    public function testUnknownProductThrowsNoException()
    {
        // build a repo that would return nothing...just in case ;)
        $fakeRepoProducts = new FakeProductRepository(null, null);

        $item = $this->buildProductLineItem('10001');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $voucherType = $vouchers->getFinalVoucherType($item, $this->salesChannelContext);

        $this->assertEquals(VoucherType::TYPE_NOTSET, $voucherType);
    }

    /**
     * This test verifies that we get the MEAL voucher value
     * if our product entity has that one stored in its custom fields.
     */
    public function testProductHasMealVoucher()
    {
        $foundProduct = new ProductEntity();
        $foundProduct->setId('ID-123');
        $foundProduct->setCustomFields([
            'mollie_payments_product_voucher_type' => VoucherType::TYPE_MEAL,
        ]);
        $foundProduct->setTranslated(['customFields' => $foundProduct->getCustomFields()]);
        // build a repo that would return nothing...just in case ;)
        $fakeRepoProducts = new FakeProductRepository(null, $foundProduct);

        $item = $this->buildProductLineItem('10001');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $voucherType = $vouchers->getFinalVoucherType($item, $this->salesChannelContext);

        $this->assertEquals(VoucherType::TYPE_MEAL, $voucherType);
    }

    /**
     * This test verifies that we get a correct exception, if our
     * product has no voucher data, a parentID has been set, but that
     * parent product is not found.
     */
    public function testUnknownParentThrowsException()
    {
        $this->expectException(ProductNotFoundException::class);

        $foundProduct = new ProductEntity();

        $foundProduct->setId('ID-123');
        $foundProduct->setParentId('PARENT-123');
        $foundProduct->setTranslated(['customFields' => $foundProduct->getCustomFields()]);

        $fakeRepoProducts = new FakeProductRepository(null, $foundProduct);
        $fakeRepoProducts->throwExceptions = true;
        $item = $this->buildProductLineItem('10001');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $vouchers->getFinalVoucherType($item, $this->salesChannelContext);
    }

    /**
     * This test verifies that we get the voucher type of the parent.
     * Our product has no voucher, but a valid parent ID.
     * The returned parent product, has then a valid voucher, which must be returned
     * from our voucher services.
     */
    public function testVoucherOfParent()
    {
        $foundProduct = new ProductEntity();
        $foundProduct->setId('ID-123');
        $foundProduct->setParentId('PARENT-123');
        $foundProduct->setTranslated(['customFields' => $foundProduct->getCustomFields()]);

        $foundParentProduct = new ProductEntity();
        $foundParentProduct->setId('ID-456');
        $foundParentProduct->setCustomFields([
            'mollie_payments_product_voucher_type' => VoucherType::TYPE_GIFT,
        ]);
        $foundParentProduct->setTranslated(['customFields' => $foundParentProduct->getCustomFields()]);

        $fakeRepoProducts = new FakeProductRepository($foundParentProduct, null);

        $item = $this->buildProductLineItem('10001');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $voucherType = $vouchers->getFinalVoucherType($item, $this->salesChannelContext);

        $this->assertEquals(VoucherType::TYPE_GIFT, $voucherType);
    }

    /**
     * This test verifies that products with a number "*" do not
     * return a voucher type. This is indeed happening when using the plugin CustomProducts in SW6.
     */
    public function testCustomProductPluginIsSkipped()
    {
        // build a repo that would return nothing...just in case ;)
        $fakeRepoProducts = new FakeProductRepository(null, null);

        // build a product line item with * as number.
        // this is what custom products does.
        // and just to be safe, let's try it with a space ;)
        $item = $this->buildProductLineItem('* ');

        $vouchers = new VoucherService($fakeRepoProducts, new NullLogger());
        $voucherType = $vouchers->getFinalVoucherType($item, $this->salesChannelContext);

        $this->assertEquals('', $voucherType);
    }

    private function buildProductLineItem(string $productNumber): LineItem
    {
        $item = new LineItem('id-123', 'product');
        $item->setPayloadValue('productNumber', $productNumber);

        return $item;
    }
}
