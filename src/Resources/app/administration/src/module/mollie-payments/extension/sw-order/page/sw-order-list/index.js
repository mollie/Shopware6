import template from './sw-order-list.html.twig';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

// eslint-disable-next-line no-undef
const {Component} = Shopware;

Component.override('sw-order-list', {
    template,

    methods: {

        /**
         *
         */
        getOrderColumns() {
            const cols = this.$super('getOrderColumns');

            // we add a custom mollie column
            cols.push({
                property: 'mollie',
                label: 'mollie-payments.sw-order-list.columns.mollie',
                allowResize: true,
                primary: false, // make sure the merchant can hide it
            });

            return cols;
        },

        /**
         *
         * @param order
         * @returns {boolean}
         */
        isMollie(order) {
            const attributes = new OrderAttributes(order);

            if (attributes.getOrderId() !== '') {
                return true;
            }

            if (attributes.getPaymentId() !== '') {
                return true;
            }

            return false;
        },

        /**
         *
         * @param order
         * @returns {string}
         */
        getMollieId(order) {
            const attributes = new OrderAttributes(order);

            if (attributes.getOrderId() !== '') {
                return attributes.getOrderId();
            }

            if (attributes.getPaymentId() !== '') {
                return attributes.getPaymentId();
            }

            return '';
        },

        /**
         *
         * @param order
         * @returns {boolean}
         */
        isMollieSubscription(order) {
            const attributes = new OrderAttributes(order);
            return (attributes.getSwSubscriptionId() !== '');
        },
    },

});
