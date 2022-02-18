import template from './sw-product-mollie-subscription-config.html.twig';

// eslint-disable-next-line no-undef
const { Component } = Shopware;
// eslint-disable-next-line no-undef
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-mollie-subscription-config', {
    template,

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading',
        ]),

        ...mapState('context', {
            languageId: state => state.api.languageId,
            systemLanguageId: state => state.api.systemLanguageId,
        }),

        intervalTypes() {
            return [
                {value: 'days', label: this.$tc('mollie-subscription.days')},
                {value: 'weeks', label: this.$tc('mollie-subscription.weeks')},
                {value: 'months', label: this.$tc('mollie-subscription.months')},
            ];
        },

        repetitionTypes() {
            return [
                {value: 'times', label: this.$tc('mollie-subscription.times')},
                {value: 'infinite', label: this.$tc('mollie-subscription.infinite')},
            ];
        },

        isDefaultLanguage() {
            return this.languageId === this.systemLanguageId;
        },
    },

    watch: {
        product() {
            this.initFields();
        },
        parentProduct() {
            this.initFields();
        },
    },

    created() {
        this.initFields();
    },

    methods: {
        initFields() {
            if (this.product) {
                if (!this.product.customFields) {
                    this.$set(this.product, 'customFields', {
                        mollie_subscription: {},
                    });
                }
                if (!this.product.customFields.mollie_subscription) {
                    this.$set(this.product.customFields, 'mollie_subscription', {});
                }
            }
            if (this.parentProduct) {
                if (!this.parentProduct.customFields) {
                    this.$set(this.parentProduct, 'customFields', {
                        mollie_subscription: {},
                    });
                }
                if (!this.parentProduct.customFields.mollie_subscription) {
                    this.$set(this.parentProduct.customFields, 'mollie_subscription', {});
                }
            }
        },
    },
});
