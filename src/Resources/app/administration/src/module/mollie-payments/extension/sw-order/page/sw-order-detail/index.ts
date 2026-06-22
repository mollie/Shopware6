import template from './sw-order-detail.html.twig';
import getLatestTransaction from '../../getLatestTransaction';

const { Component } = Shopware;

interface SwOrderDetailOverride {
    [key: string]: any;
}

const overrideConfig: ThisType<SwOrderDetailOverride> = {
    template,

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('mollieSubscriptions');

            return criteria;
        },

        isMollieOrder() {
            const latest = getLatestTransaction(this.order?.transactions);

            return !!latest?.paymentMethod?.customFields?.mollie_payment_method_name;
        },
    },
};

Component.override('sw-order-detail', overrideConfig);
