import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss'
import ProductAttributes from '../../../../core/models/ProductAttributes';
import StringUtils from '../../../../core/service/utils/string-utils.service';
import ProductService from '../../../../core/service/product/product.service';

// eslint-disable-next-line no-undef
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line no-undef
Shopware.Component.register('sw-product-detail-mollie', {

    template,

    inject: ['repositoryFactory'],

    metaInfo() {
        return {
            title: 'Mollie',
        };
    },

    props: {
        productId: {
            required: true,
            type: String
        },
    },

    data() {
        return {
            productEntity: null,
            parentVoucherType: '',
            productVoucherType: '',
            mollieSubscriptionProduct: false,
            mollieSubscriptionIntervalAmount: '',
            mollieSubscriptionIntervalType: '',
            mollieSubscriptionRepetitionAmount: '',
            mollieSubscriptionRepetitionType: '',
        }
    },

    created() {
        this.productId = this.$route.params.id
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
         * @returns {[{name, key: number}, {name, key: number}, {name, key: number}]}
         */
        intervalTypes() {
            return [
                {key: 'days', name: this.$tc('mollie-subscription.days')},
                {key: 'weeks', name: this.$tc('mollie-subscription.weeks')},
                {key: 'months', name: this.$tc('mollie-subscription.months')},
            ];
        },

        /**
         *
         * @returns {[{name, key: number}, {name, key: number}]}
         */
        repetitionTypes() {
            return [
                {key: 'times', name: this.$tc('mollie-subscription.times')},
                {key: 'infinite', name: this.$tc('mollie-subscription.infinite')},
            ];
        },

        /**
         *
         * @returns {string}
         */
        typeNONE() {
            return '0';
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
         *
         * @param newValue
         */
        onVoucherChanged(newValue) {
            this.updateData(newValue);
        },

        /**
         * @param attr
         * @param newValue
         */
        onChanged(newValue, attr) {
            this.updateSubscriptionData(attr, newValue);
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
                this.updateData(this.parentVoucherType);
            } else {
                this.updateData(this.typeNONE);
            }
        },

        /**
         *
         */
        restoreInheritance() {
            this.updateData('');
        },

        /**
         *
         */
        readMollieData() {

            this.parentVoucherType = '';
            this.productVoucherType = '';
            this.mollieSubscriptionProduct = '';
            this.mollieSubscriptionIntervalAmount = '';
            this.mollieSubscriptionIntervalType = '';
            this.mollieSubscriptionRepetitionAmount = '';
            this.mollieSubscriptionRepetitionType = '';

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
                    this.mollieSubscriptionProduct = parentAtts.getMollieSubscriptionProduct();
                    this.mollieSubscriptionIntervalAmount = parentAtts.getMollieSubscriptionIntervalAmount();
                    this.mollieSubscriptionIntervalType = parentAtts.getMollieSubscriptionIntervalType();
                    this.mollieSubscriptionRepetitionAmount = parentAtts.getMollieSubscriptionRepetitionAmount();
                    this.mollieSubscriptionRepetitionType = parentAtts.getMollieSubscriptionRepetitionType();

                    // if we have a parent, and its nothing, that it should
                    // at least display NONE
                    if (this.stringUtils.isNullOrEmpty(this.parentVoucherType)) {
                        this.parentVoucherType = this.typeNONE;
                    }
                    if (this.stringUtils.isNullOrEmpty(this.mollieSubscriptionProduct)) {
                        this.mollieSubscriptionProduct = false;
                    }
                    if (this.stringUtils.isNullOrEmpty(this.mollieSubscriptionIntervalAmount )) {
                        this.mollieSubscriptionIntervalAmount = '';
                    }
                    if (this.stringUtils.isNullOrEmpty(this.mollieSubscriptionIntervalType)) {
                        this.mollieSubscriptionIntervalType = '';
                    }
                    if (this.stringUtils.isNullOrEmpty(this.mollieSubscriptionRepetitionAmount)) {
                        this.mollieSubscriptionRepetitionAmount = '';
                    }
                    if (this.stringUtils.isNullOrEmpty(this.mollieSubscriptionRepetitionType )) {
                        this.mollieSubscriptionRepetitionType  = '';
                    }
                });
            }

            this.productRepository.get(this.productId, Shopware.Context.api).then(parent => {
                const mollieAttributes = new ProductAttributes(parent);

                this.productVoucherType = mollieAttributes.getVoucherType();
                this.mollieSubscriptionProduct = mollieAttributes.getMollieSubscriptionProduct();
                this.mollieSubscriptionIntervalAmount = mollieAttributes.getMollieSubscriptionIntervalAmount();
                this.mollieSubscriptionIntervalType = mollieAttributes.getMollieSubscriptionIntervalType();
                this.mollieSubscriptionRepetitionAmount = mollieAttributes.getMollieSubscriptionRepetitionAmount();
                this.mollieSubscriptionRepetitionType = mollieAttributes.getMollieSubscriptionRepetitionType();
            });


            // if we have no parent, and also not yet something assigned
            // then make sure we have a NONE value
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.productVoucherType)) {
                this.productVoucherType = this.typeNONE;
            }
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.mollieSubscriptionProduct)) {
                this.mollieSubscriptionProduct = false;
            }
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.mollieSubscriptionIntervalAmount)) {
                this.mollieSubscriptionIntervalAmount = '';
            }
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.mollieSubscriptionIntervalType)) {
                this.mollieSubscriptionIntervalType = '';
            }
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.mollieSubscriptionRepetitionAmount)) {
                this.mollieSubscriptionRepetitionAmount = '';
            }
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.mollieSubscriptionRepetitionType)) {
                this.mollieSubscriptionRepetitionType = '';
            }
        },

        /**
         *
         */
        updateSubscriptionData(attr, newValue) {
            if (!this.product) {
                return;
            }

            const mollieAttributes = new ProductAttributes(this.product)

            switch (attr) {
                case 'mollieSubscriptionProduct':
                    mollieAttributes.setMollieSubscriptionProduct(newValue);
                    break;
                case 'intervalAmount':
                    if (newValue !== '') {
                        mollieAttributes.setMollieSubscriptionIntervalAmount(newValue);
                    } else {
                        mollieAttributes.clearMollieSubscriptionIntervalAmount();
                    }
                    break;
                case 'intervalType':
                    if (newValue !== '') {
                        mollieAttributes.setMollieSubscriptionIntervalType(newValue);
                    } else {
                        mollieAttributes.clearMollieSubscriptionIntervalType();
                    }
                    break;
                case 'repetitionAmount':
                    if (newValue !== '') {
                        mollieAttributes.setMollieSubscriptionRepetitionAmount(newValue);
                    } else {
                        mollieAttributes.clearMollieSubscriptionRepetitionAmount();
                    }
                    break;
                case 'repetitionType' :
                    if (newValue !== '') {
                        mollieAttributes.setMollieSubscriptionRepetitionType(newValue);
                    } else {
                        mollieAttributes.clearMollieSubscriptionRepetitionType();
                    }
                    break;
            }

            // now update our product data
            this.productService.updateCustomFieldsSubscription(this.product, mollieAttributes);
        },

        /**
         *
         */
        updateData(newProductVoucherType) {

            this.productVoucherType = newProductVoucherType;

            if (!this.product) {
                return;
            }

            const mollieAttributes = new ProductAttributes(this.product)

            if (newProductVoucherType !== '') {
                mollieAttributes.setVoucherType(newProductVoucherType);
            } else {
                mollieAttributes.clearVoucherType();
            }

            // now update our product data
            this.productService.updateCustomFields(this.product, mollieAttributes);
        },
    },

});
