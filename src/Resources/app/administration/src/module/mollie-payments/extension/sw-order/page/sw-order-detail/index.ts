import template from './sw-order-detail.html.twig';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

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
            return new OrderAttributes(this.order).isMollieOrder();
        },
    },
};

Component.override('sw-order-detail', overrideConfig);
