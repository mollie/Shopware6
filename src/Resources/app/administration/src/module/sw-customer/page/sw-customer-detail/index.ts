import template from './sw-customer-detail.html.twig';

const { Component } = Shopware;

interface SwCustomerDetailOverride {
    [key: string]: any;
}

const overrideConfig: ThisType<SwCustomerDetailOverride> = {
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
};

Component.override('sw-customer-detail', overrideConfig);
