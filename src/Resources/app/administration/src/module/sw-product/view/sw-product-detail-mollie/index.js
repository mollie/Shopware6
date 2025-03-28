import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss';

// eslint-disable-next-line no-undef
Shopware.Component.register('sw-product-detail-mollie', {
    template,

    inject: ['repositoryFactory'],

    metaInfo() {
        return {
            title: 'Mollie',
        };
    },

    data() {
        return {};
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

    computed: {
        productId() {
            return this.$route.params.id;
        },

        product() {
            // eslint-disable-next-line no-undef
            let product = Shopware.State.get('swProductDetail');
            if (product === undefined) {
                product = Shopware.Store.get('swProductDetail');
            }
            return product.product;
        },

        parentProduct() {
            // eslint-disable-next-line no-undef
            let product = Shopware.State.get('swProductDetail');
            if (product === undefined) {
                product = Shopware.Store.get('swProductDetail');
            }
            return product.parentProduct;
        },

        isLoading() {
            // eslint-disable-next-line no-undef
            let product = Shopware.State.get('swProductDetail');
            if (product === undefined) {
                product = Shopware.Store.get('swProductDetail');
            }

            return product.isLoading;
        },

        context() {
            // eslint-disable-next-line no-undef
            let context = Shopware.State.get('context');
            if (context === undefined) {
                context = Shopware.Store.get('context');
            }
            return context;
        },

        languageId() {
            return this.context.languageId;
        },

        systemLanguageId() {
            return this.context.systemLanguageId;
        },

        isSystemDefaultLanguage() {
            return this.context.isSystemDefaultLanguage;
        },

        /**
         *
         * @returns {[{name, key: number}, {name, key: number}, {name, key: number}, {name, key: number}]}
         */
        voucherTypes() {
            return [
                { key: 0, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_NONE') },
                { key: 1, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_ECO') },
                { key: 2, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_MEAL') },
                { key: 3, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_VOUCHER') },
            ];
        },

        /**
         *
         * @returns {string}
         */
        voucherTypeNONE() {
            return '0';
        },

        /**
         *
         * @returns {[{label, value: string},{label, value: string},{label, value: string}]}
         */
        subscriptionIntervalTypes() {
            return [
                { value: 'days', label: this.$tc('mollie-payments.subscriptions.TYPE_DAYS') },
                { value: 'weeks', label: this.$tc('mollie-payments.subscriptions.TYPE_WEEKS') },
                { value: 'months', label: this.$tc('mollie-payments.subscriptions.TYPE_MONTHS') },
            ];
        },

        /**
         *
         * @returns {boolean}
         */
        isDefaultLanguage() {
            return this.languageId === this.systemLanguageId;
        },
    },

    methods: {
        initFields() {
            if (this.product) {
                if (!this.product.customFields) {
                    this.product.customFields = {};
                }
            }
            if (this.parentProduct) {
                if (!this.parentProduct.customFields) {
                    this.parentProduct.customFields = {};
                }
            }
        },
    },
});
