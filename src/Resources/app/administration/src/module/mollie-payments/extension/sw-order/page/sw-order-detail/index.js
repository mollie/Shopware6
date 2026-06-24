import template from './sw-order-detail.html.twig';

// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-order-detail', {
    template,

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('mollieSubscriptions');

            return criteria;
        },

        isMollieOrder() {
            const order = this.order;

            if (!order || !order.transactions) {
                return false;
            }

            const transactions = order.transactions;
            let latest = typeof transactions.first === 'function' ? transactions.first() : transactions[0];

            if (!latest) {
                return false;
            }

            transactions.forEach(function (t) {
                if (t.createdAt > latest.createdAt) {
                    latest = t;
                }
            });

            return !!latest?.paymentMethod?.customFields?.mollie_payment_method_name;
        },
    },
});
