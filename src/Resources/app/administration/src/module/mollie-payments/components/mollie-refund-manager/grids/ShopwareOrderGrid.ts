import type { GridColumn } from './GridColumn';

export default class ShopwareOrderGrid {
    buildColumns(): GridColumn[] {
        const app = Shopware.Application.getApplicationRoot();

        return [
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.item'),
                property: 'shopware.label',
                align: 'left',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.productNumber'),
                property: 'shopware.productNumber',
                align: 'left',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.unitPrice'),
                property: 'shopware.unitPrice',
                width: '90px',
                align: 'right',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.quantity'),
                property: 'shopware.quantity',
                width: '50px',
                align: 'right',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.refunded'),
                property: 'refunded',
                width: '50px',
                align: 'right',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.refundQuantity'),
                property: 'inputQuantity',
                width: '140px',
                align: 'center',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.totalPrice'),
                property: 'shopware.totalPrice',
                width: '110px',
                align: 'right',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.refundAmount'),
                property: 'inputAmount',
                width: '150px',
                align: 'center',
            },
            {
                label: '',
                property: 'inputConsiderTax',
                align: 'center',
            },
            {
                label: '',
                property: 'inputConsiderPromotion',
                align: 'center',
            },
            {
                label: app.$tc('mollie-payments.refund-manager.cart.grid.columns.resetStock'),
                property: 'inputStock',
                width: '135px',
                align: 'center',
            },
        ];
    }
}
