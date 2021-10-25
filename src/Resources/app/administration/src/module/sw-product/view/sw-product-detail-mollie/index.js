import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss'
import ProductAttributes from '../../../../core/models/ProductAttributes';
import StringUtils from '../../../../core/service/utils/string-utils.service';
import ProductService from '../../../../core/service/product/product.service';

// eslint-disable-next-line no-undef
const {mapState} = Shopware.Component.getComponentHelper();

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
            parentVoucherType: '',
            productVoucherType: '',
        }
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {

        ...mapState('swProductDetail', [
            'product',
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

            if (!this.product) {
                return;
            }

            // if we do have a parent, then fetch that product
            // and read its voucher type for our local variable
            if (this.hasParentProduct) {
                // eslint-disable-next-line no-undef
                this.productRepository.get(this.product.parentId, Shopware.Context.api).then(parent => {
                    const parentAtts = new ProductAttributes(parent);
                    this.parentVoucherType = parentAtts.getVoucherType();

                    // if we have a parent, and its nothing, that it should
                    // at least display NONE
                    if (this.stringUtils.isNullOrEmpty(this.parentVoucherType)) {
                        this.parentVoucherType = this.typeNONE;
                    }
                });
            }

            const mollieAttributes = new ProductAttributes(this.product);

            this.productVoucherType = mollieAttributes.getVoucherType();

            // if we have no parent, and also not yet something assigned
            // then make sure we have a NONE value
            if (!this.hasParentProduct && this.stringUtils.isNullOrEmpty(this.productVoucherType)) {
                this.productVoucherType = this.typeNONE;
            }
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
