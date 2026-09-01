import template from './mollie-payment-overview-card.html.twig';
import './mollie-payment-overview-card.scss';

const { Component } = Shopware;

interface PaymentOverviewCardComponent {
    paymentMethods: any[];

    [key: string]: any;
}

const componentConfig: ThisType<PaymentOverviewCardComponent> = {
    template,

    inject: ['acl'],

    emits: ['set-payment-active'],

    props: {
        paymentMethods: {
            type: Array,
            required: true,
        },
    },

    methods: {
        setPaymentMethodActive(paymentMethod: any, active: boolean) {
            if (paymentMethod.active === active) {
                return;
            }

            paymentMethod.active = active;

            this.$emit('set-payment-active', paymentMethod);
        },
    },
};

Component.register('mollie-payment-overview-card', componentConfig);
