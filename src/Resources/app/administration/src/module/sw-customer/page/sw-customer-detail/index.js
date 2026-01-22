import template from './sw-customer-detail.html.twig';

// eslint-disable-next-line no-undef
Shopware.Component.override('sw-customer-detail', {
    template,
    computed: {
        subscriptionRoute() {
            return {
                name: 'sw.customer.detail.mollie-subscriptions',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },
        hasMollieData() {
            if (this.customer === null) {
                return false;
            }

            return this.customer.customFields?.mollie_payments?.customer_ids !== undefined;
        },
    },
});
