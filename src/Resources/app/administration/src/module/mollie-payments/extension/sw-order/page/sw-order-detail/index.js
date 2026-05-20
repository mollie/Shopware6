// eslint-disable-next-line no-undef
const { Component } = Shopware;

Component.override('sw-order-detail', {
    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('mollieSubscriptions');

            return criteria;
        },
    },
});
