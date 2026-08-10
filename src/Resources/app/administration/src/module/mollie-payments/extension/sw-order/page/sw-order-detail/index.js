import template from './sw-order-detail.html.twig';
import OrderAttributes from '../../../../../../core/models/OrderAttributes';

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
            const attributes = new OrderAttributes(this.order);

            return attributes.isMollieOrder();
        },
    },
});
