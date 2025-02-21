import { beforeEach, expect, test } from 'vitest'
import RefundItemService from '../../../../src/module/mollie-payments/components/mollie-refund-manager/services/RefundItemService';

const service = new RefundItemService();


let item = {};


beforeEach(() => {

    item = {
        'refunded': 0,
        'refundQuantity': 0,
        'shopware': {
            'quantity': 5,
            'unitPrice': 14.99,
            'isPromotion': false,
            'isDelivery': false,
            'promotion': {
                'discount': 0,
            },
        },
    };
});


// ---------------------------------------------------------------------------------------------------

test('Item can set a custom quantity for the stock reset', () => {
    service.setStockReset(item, 2);
    expect(item.resetStock).toBe(2);
});

test('Item does not overwrite the stock reset quantity if already configured', () => {
    item.resetStock = 1;
    service.setStockReset(item, 2);
    expect(item.resetStock).toBe(1);
});

// ---------------------------------------------------------------------------------------------------

test('Item can be refunded if not fully refunded', () => {
    item.refunded = 4;
    item.shopware.quantity = 5;

    const isRefundable = service.isRefundable(item);
    expect(isRefundable).toBe(true);
});

test('Item can still be refunded if already fully refunded', () => {
    // we have the use case that a merchant refunds an item with qty 1
    // but with half the price.
    // the customer complains and the merchant refunds the rest.
    // the merchant wants a reference to the refunded item and tries to use qty 0, so that
    // it will appear in the composition. Therefore isRefundable needs to be TRUE
    item.refunded = 5;
    item.shopware.quantity = 5;

    const isRefundable = service.isRefundable(item);
    expect(isRefundable).toBe(true);
});

test('Item cannot be refunded if price is 0,00', () => {
    item.shopware.quantity = 1;
    item.shopware.unitPrice = 0;

    const isRefundable = service.isRefundable(item);
    expect(isRefundable).toBe(false);
});

// ---------------------------------------------------------------------------------------------------

test('Item is no delivery type', () => {
    item.shopware.isDelivery = false;

    const isDelivery = service.isTypeDelivery(item);
    expect(isDelivery).toBe(false);
});

test('Item is delivery type', () => {
    item.shopware.isDelivery = true;
    const isDelivery = service.isTypeDelivery(item);
    expect(isDelivery).toBe(true);
});

// ---------------------------------------------------------------------------------------------------

test('Item is no promotion type', () => {
    item.shopware.isPromotion = false;

    const isPromotion = service.isTypePromotion(item);
    expect(isPromotion).toBe(false);
});

test('Item is promotion type', () => {
    item.shopware.isPromotion = true;

    const isPromotion = service.isTypePromotion(item);
    expect(isPromotion).toBe(true);
});

// ---------------------------------------------------------------------------------------------------

test('Item is discounted by promotion', () => {
    item.shopware.promotion.discount = 5;

    const isDiscounted = service.isDiscounted(item);
    expect(isDiscounted).toBe(true);
});

test('Item is not discounted by promotion', () => {
    item.shopware.promotion.discount = 0;

    const isDiscounted = service.isDiscounted(item);
    expect(isDiscounted).toBe(false);
});

// ---------------------------------------------------------------------------------------------------

test('Item properties are prepared correctly if set to be fully refunded', () => {
    item.refunded = 2;
    item.shopware.quantity = 5;
    item.shopware.unitPrice = 9.99;

    service.setFullRefund(item);

    // the diff for the full refund needs to be 3
    expect(item.refundQuantity).toBe(3);
    // the amount needs to be the price of 3
    expect(item.refundAmount).toBe(9.99 * 3);
});

// ---------------------------------------------------------------------------------------------------

test('Item values can be reset correctly', () => {
    item.refundMode = 'quantity';
    item.refundQuantity = 2;
    item.refundAmount = 3;
    item.resetStock = 5;
    item.refundPromotion = true;

    service.resetRefundData(item);

    expect(item.refundMode).toBe('none');
    expect(item.refundQuantity).toBe(0);
    expect(item.refundAmount).toBe(0);
    expect(item.resetStock).toBe(0);
    expect(item.refundPromotion).toBe(false);
});

// ---------------------------------------------------------------------------------------------------

test('Item with deducted promotion leads to reduced refund amount', () => {

    // we sold 3 items with 10 EUR per item
    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    // we had 3 items discounted with a total of 9 EUR
    item.shopware.promotion.quantity = 3;
    item.shopware.promotion.discount = 9;

    // now refund 1 item
    item.refundQuantity = 1;

    // mark to also deduct promotions
    // and call our event
    item.refundPromotion = true;
    service.onPromotionDeductionChanged(item);

    // we now return 1 item with 10 EUR minus the
    // promotion discount of 3 EUR per item.
    expect(item.refundAmount).toBe(10 - 3);
});

test('Item without deducted promotion leads to original refund amount', () => {

    // we sold 3 items with 10 EUR per item
    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    // we had 3 items discounted with a total of 9 EUR
    item.shopware.promotion.quantity = 3;
    item.shopware.promotion.discount = 9;

    // now refund 1 item
    item.refundQuantity = 1;

    // mark to also deduct promotions
    // and call our event
    item.refundPromotion = false;
    service.onPromotionDeductionChanged(item);

    // we now return 1 item with 10 EUR
    // we do not deduct the promotions in this case
    expect(item.refundAmount).toBe(10);
});

test('Item does not deduct promotion if a custom amount is already set', () => {

    // we sold 3 items with 10 EUR per item
    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;
    item.shopware.promotion.quantity = 3;
    item.shopware.promotion.discount = 9;

    // set custom amount
    item.refundAmount = 12;
    service.onAmountChanged(item);

    // now deduct the promotions automatically
    item.refundPromotion = true;
    service.onPromotionDeductionChanged(item);

    // we must not use any other value
    // except the one that we did set
    expect(item.refundAmount).toBe(12);
});

// ---------------------------------------------------------------------------------------------------

test('Item refund quantity can be set correctly', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refundQuantity = 1;
    service.onQuantityChanged(item);

    expect(item.refundQuantity).toBe(1);
});

test('Item refund amount automatically calculated when quantity is set', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    // set a quantity
    item.refundQuantity = 2;
    service.onQuantityChanged(item);

    expect(item.refundAmount).toBe(2 * 10);
});

test('Item quantity is not recalculated if we already entered an amount', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refundAmount = 12;
    service.onAmountChanged(item);

    item.refundQuantity = 3;
    service.onQuantityChanged(item);

    expect(item.refundAmount).toBe(12);
    expect(item.refundQuantity).toBe(3);
});

test('Item automatically calculates maximum refundable quantity', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refunded = 2;

    item.refundQuantity = 5;
    service.onQuantityChanged(item);

    expect(item.refundQuantity).toBe(1);
});

// ---------------------------------------------------------------------------------------------------

test('Item refund amount can be set correctly', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refundAmount = 20;
    service.onAmountChanged(item);

    expect(item.refundAmount).toBe(20);
});

test('Item calculates matching quantity on amount input', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refundAmount = 20;
    service.onAmountChanged(item);

    expect(item.refundQuantity).toBe(2);
});

test('Item does not calculate matching quantity on amount input if quantity already set', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    item.refundQuantity = 1;
    item.refundAmount = 20;
    service.onAmountChanged(item);

    expect(item.refundQuantity).toBe(1);
    expect(item.refundAmount).toBe(20);
});

test('Item with custom refund even if quantity is already set', () => {

    item.shopware.unitPrice = 10;
    item.shopware.quantity = 3;

    // set a quantity
    item.refundQuantity = 2;
    service.onQuantityChanged(item);

    // now set any amount
    // this needs to be reused
    // no additional calculation should overwrite it
    item.refundAmount = 8;
    service.onAmountChanged(item);

    expect(item.refundAmount).toBe(8);
});

