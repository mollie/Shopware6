import template from './sw-customer-base-info.html.twig';

// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-customer-base-info', {
    template,

    computed: {
        preferredIdealIssuer() {
            if (
                !!this.customer
                && !!this.customer.customFields
                && !!this.customer.customFields.mollie_payments
                && !!this.customer.customFields.mollie_payments.preferred_ideal_issuer
            ) {
                return this.customer.customFields.mollie_payments.preferred_ideal_issuer;
            }

            return null;
        },
    },
});