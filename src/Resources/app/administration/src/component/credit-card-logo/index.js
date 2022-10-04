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

    methods: {
        getImageNameForCreditcard(creditcard) {
            switch (creditcard) {
                case 'American Express':
                    return 'amex'
                case 'Carta Si':
                    return 'cartasi'
                case 'Carte Bleue':
                    return 'cartebancaire'
                case 'Maestro':
                    return 'maestro'
                case 'Mastercard':
                    return 'mastercard'
                case 'Visa':
                    return 'visa'
                default:
                    return 'creditcard'
            }
        },
    },

    computed: {
        creditCardComponentName() {
            const prefix = 'mollie-credit-card-logo-';
            const creditCardCompany = this.getImageNameForCreditcard(this.creditCardCompany);
            return `${prefix}${creditCardCompany}`;
        },
    },
});
