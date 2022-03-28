import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss'
import ProductAttributes from '../../../../core/models/ProductAttributes';
import StringUtils from '../../../../core/service/utils/string-utils.service';
import ProductService from '../../../../core/service/product/product.service';

// eslint-disable-next-line no-undef
const {mapState, mapGetters} = Shopware.Component.getComponentHelper();

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
        return {
            productEntity: null,
            // --------------------------------------
            parentVoucherType: '',
            parentIsSubscription: false,
            parentSubscriptionInterval: '',
            parentSubscriptionIntervalUnit: '',
            parentSubscriptionRepetition: '',
            parentSubscriptionRepetitionType: '',
            // --------------------------------------
            productVoucherType: '',
            productIsSubscription: false,
            productSubscriptionInterval: '',
            productSubscriptionIntervalUnit: '',
            productSubscriptionRepetition: '',
            productSubscriptionRepetitionType: '',
            // --------------------------------------
        }
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
        ]),

        ...mapGetters('context', [
            'isSystemDefaultLanguage',
        ]),

        ...mapState('context', {
            languageId: state => state.api.languageId,
            systemLanguageId: state => state.api.systemLanguageId,
        }),

        productId() {
            return this.$route.params.id;
        },

        /**
         *
         * @returns {[{name, key: number}, {name, key: number}, {name, key: number}, {name, key: number}]}
         */
        voucherTypes() {
            return [
                {key: 0, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_NONE')},
                {key: 1, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_ECO')},
                {key: 2, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_MEAL')},
                {key: 3, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_VOUCHER')},
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
                {value: 'days', label: this.$tc('mollie-subscription.days')},
                {value: 'weeks', label: this.$tc('mollie-subscription.weeks')},
                {value: 'months', label: this.$tc('mollie-subscription.months')},
            ];
        },

        /**
         *
         * @returns {[{label, value: string},{label, value: string}]}
         */
        subscriptionRepetitionTypes() {
            return [
                {value: 'times', label: this.$tc('mollie-subscription.times')},
                {value: 'infinite', label: this.$tc('mollie-subscription.infinite')},
            ];
        },

        /**
         *
         * @returns {product}
         */
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        /**
         *
         * @returns {ProductService}
         */
        productService() {
            return new ProductService();
        },

        /**
         *
         * @returns {StringUtils}
         */
        stringUtils() {
            return new StringUtils();
        },

        /**
         *
         * @returns {boolean}
         */
        hasParentProduct() {
            return (!this.stringUtils.isNullOrEmpty(this.product.parentId));
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

        /**
         *
         */
        mountedComponent() {
            this.readMollieData();
        },

        /**
         * @param newValue
         */
        onVoucherChanged(newValue) {
            this.productVoucherType = newValue;
            this.updateProductData();
        },

        /**
         * @param newValue
         */
        onIsSubscriptionChanged(newValue) {
            this.productIsSubscription = newValue;
            this.updateProductData();
        },

        /**
         * @param newValue
         */
        onSubscriptionIntervalChanged(newValue) {
            this.productSubscriptionInterval = newValue;
            this.updateProductData();
        },

        /**
         * @param newValue
         */
        onSubscriptionIntervalUnitChanged(newValue) {
            this.productSubscriptionIntervalUnit = newValue;
            this.updateProductData();
        },

        /**
         * @param newValue
         */
        onSubscriptionRepetitionChanged(newValue) {
            this.productSubscriptionRepetition = newValue;
            this.updateProductData();
        },

        /**
         * @param newValue
         */
        onSubscriptionRepetitionTypeChanged(newValue) {
            this.productSubscriptionRepetitionType = newValue;
            this.updateProductData();
        },

        /**
         *
         * @returns {boolean}
         */
        checkInheritance() {
            // read the latest data
            // this is due to race-conditions
            this.readMollieData();

            // if we have a value, then we have a
            // TRUE value for our inheritance
            if (!this.stringUtils.isNullOrEmpty(this.productVoucherType)) {
                return false;
            }

            return true;
        },

        /**
         *
         */
        removeInheritance() {
            // if we have a parent, use its value
            // otherwise just 0 "None".
            if (!this.stringUtils.isNullOrEmpty(this.parentVoucherType)) {
                this.productVoucherType = this.parentVoucherType;
            } else {
                this.productVoucherType = this.typeNONE;
            }

            this.updateProductData();
        },

        /**
         *
         */
        restoreInheritance() {
            this.productVoucherType = '';
            this.updateProductData();
        },

        /**
         *
         */
        readMollieData() {

            this.parentVoucherType = '';
            this.parentIsSubscription = false;
            this.parentSubscriptionInterval = '';
            this.parentSubscriptionIntervalUnit = '';
            this.parentSubscriptionRepetition = '';
            this.parentSubscriptionRepetitionType = '';

            this.productVoucherType = '';
            this.productIsSubscription = false;
            this.productSubscriptionInterval = '';
            this.productSubscriptionIntervalUnit = '';
            this.productSubscriptionRepetition = '';
            this.productSubscriptionRepetitionType = '';


            // if we do have a parent, then fetch that product
            // and read its voucher type for our local variable
            if (this.hasParentProduct) {

                if (!this.product) {
                    return;
                }

                // eslint-disable-next-line no-undef
                this.productRepository.get(this.product.parentId, Shopware.Context.api).then(parent => {

                    const parentAtts = new ProductAttributes(parent);

                    this.parentVoucherType = parentAtts.getVoucherType();
                    this.parentIsSubscription = parentAtts.isSubscriptionProduct();
                    this.parentSubscriptionInterval = parentAtts.getSubscriptionInterval();
                    this.parentSubscriptionIntervalUnit = parentAtts.getSubscriptionIntervalUnit();
                    this.parentSubscriptionRepetition = parentAtts.getSubscriptionRepetition();
                    this.parentSubscriptionRepetitionType = parentAtts.getSubscriptionRepetitionType();

                    // FALLBACK on EMPTY VALUES
                    // if we have a parent, and its nothing, that it should at least display NONE for vouchers
                    if (this.stringUtils.isNullOrEmpty(this.parentVoucherType)) {
                        this.parentVoucherType = this.typeNONE;
                    }
                });
            }

            // eslint-disable-next-line no-undef
            this.productRepository.get(this.productId, Shopware.Context.api).then(product => {

                const productAtts = new ProductAttributes(product);

                this.productVoucherType = productAtts.getVoucherType();
                this.productIsSubscription = productAtts.isSubscriptionProduct();
                this.productSubscriptionInterval = productAtts.getSubscriptionInterval();
                this.productSubscriptionIntervalUnit = productAtts.getSubscriptionIntervalUnit();
                this.productSubscriptionRepetition = productAtts.getSubscriptionRepetition();
                this.productSubscriptionRepetitionType = productAtts.getSubscriptionRepetitionType();
            });


            // FALLBACK on EMPTY VALUES
            // if we have no parent, and also not yet something assigned
            // then make sure we have a NONE value
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.productVoucherType)) {
                this.productVoucherType = this.typeNONE;
            }
        },

        /**
         *
         */
        updateProductData() {

            if (!this.product) {
                return;
            }

            const mollieAttributes = new ProductAttributes(this.product)


            if (this.productVoucherType !== '') {
                mollieAttributes.setVoucherType(this.productVoucherType);
            } else {
                mollieAttributes.clearVoucherType();
            }

            mollieAttributes.setSubscriptionProduct(this.productIsSubscription);

            if (this.productSubscriptionInterval !== '') {
                mollieAttributes.setSubscriptionInterval(this.productSubscriptionInterval);
            } else {
                mollieAttributes.clearSubscriptionInterval();
            }

            if (this.productSubscriptionIntervalUnit !== '') {
                mollieAttributes.setSubscriptionIntervalUnit(this.productSubscriptionIntervalUnit);
            } else {
                mollieAttributes.clearSubscriptionIntervalUnit();
            }

            if (this.productSubscriptionRepetition !== '') {
                mollieAttributes.setSubscriptionRepetition(this.productSubscriptionRepetition);
            } else {
                mollieAttributes.clearSubscriptionRepetition();
            }

            if (this.productSubscriptionRepetitionType !== '') {
                mollieAttributes.setSubscriptionRepetitionType(this.productSubscriptionRepetitionType);
            } else {
                mollieAttributes.clearSubscriptionRepetitionType();
            }


            // now update our product data
            this.productService.updateCustomFields(this.product, mollieAttributes);
        },
    },
});
