import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss';

const { Component } = Shopware;

interface ProductDetailMollieView {
    [key: string]: any;
}

const componentConfig: ThisType<ProductDetailMollieView> = {
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
            return this._resolveStore('swProductDetail').product;
        },

        parentProduct() {
            return this._resolveStore('swProductDetail').parentProduct;
        },

        isLoading() {
            return this._resolveStore('swProductDetail').isLoading;
        },

        context() {
            return this._resolveStore('context');
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

        voucherTypes() {
            return [
                { value: 1, label: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_ECO') },
                { value: 2, label: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_MEAL') },
                { value: 3, label: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_VOUCHER') },
            ];
        },

        voucherTypeNONE() {
            return '0';
        },

        subscriptionIntervalTypes() {
            return [
                { value: 'days', label: this.$tc('mollie-payments.subscriptions.TYPE_DAYS') },
                { value: 'weeks', label: this.$tc('mollie-payments.subscriptions.TYPE_WEEKS') },
                { value: 'months', label: this.$tc('mollie-payments.subscriptions.TYPE_MONTHS') },
            ];
        },

        isDefaultLanguage() {
            return this.languageId === this.systemLanguageId;
        },
    },

    methods: {
        /**
         * Resolves a Shopware store module across versions: Vuex State (<6.7)
         * with a fallback to the Pinia Store (>=6.7). Optional chaining keeps it
         * safe when State has been removed.
         */
        _resolveStore(name: string) {
            return Shopware.State?.get?.(name) ?? Shopware.Store?.get?.(name);
        },

        initFields() {
            this._normalizeVoucherType(this.product);
            this._normalizeVoucherType(this.parentProduct);
        },

        _normalizeVoucherType(entity: any) {
            if (!entity) {
                return;
            }

            if (!entity.customFields) {
                entity.customFields = {};
            }

            const voucherType = entity.customFields.mollie_payments_product_voucher_type;

            if (voucherType && !Array.isArray(voucherType)) {
                entity.customFields.mollie_payments_product_voucher_type = [voucherType];
            }
        },
    },
};

Component.register('sw-product-detail-mollie', componentConfig);
