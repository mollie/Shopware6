import template from './credit-card-logo.html.twig';
import './credit-card-logo.scss';

// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.register('mollie-credit-card-logo', {
    template,

    props: {
        creditCardCompany: {
            type: String,
            required: true,
            default: '',
        },
    },

    computed: {
        creditCardComponentName() {
            const prefix = 'mollie-credit-card-logo-';
            const creditCardCompany = this.creditCardCompany.toLowerCase();
            return `${prefix}${creditCardCompany}`;
        },
    },
});
