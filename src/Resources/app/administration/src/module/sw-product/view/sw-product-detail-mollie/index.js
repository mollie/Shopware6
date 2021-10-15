import template from './sw-product-detail-mollie.html.twig';
import './sw-product-detail-mollie.scss'
import ProductAttributes from '../../../../core/models/ProductAttributes';

// eslint-disable-next-line no-undef
const {mapState} = Shopware.Component.getComponentHelper();

// eslint-disable-next-line no-undef
Shopware.Component.register('sw-product-detail-mollie', {
    template,
    metaInfo() {
        return {
            title: 'Mollie',
        };
    },

    computed: {

        ...mapState('swProductDetail', [
            'product',
        ]),

        selectedVoucherType() {

            const VALUE_NONE = '0';

            if (!this.product) {
                return VALUE_NONE;
            }

            if (!this.product.customFields) {
                return VALUE_NONE;
            }

            const mollieAttributes = new ProductAttributes(this.product.customFields);

            const voucherType = mollieAttributes.getVoucherType();

            // if we do not have a value
            // then always select NONE in the UI
            if (voucherType === '') {
                return VALUE_NONE;
            }

            return mollieAttributes.getVoucherType();
        },

        voucherTypes() {
            return [
                {key: 0, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_NONE')},
                {key: 1, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_ECO')},
                {key: 2, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_MEAL')},
                {key: 3, name: this.$tc('mollie-payments.vouchers.VOUCHER_TYPE_VALUE_VOUCHER')},
            ];
        },
    },

    methods: {

        onVoucherChanged(newValue) {

            if (!this.product) {
                return;
            }

            if (!this.product.customFields) {
                this.product.customFields = {};
            }

            const mollieAttributes = new ProductAttributes(this.product.customFields)
            mollieAttributes.setVoucherType(newValue);

            this.product.customFields.mollie_payments = mollieAttributes.toArray();
        },

    },

});
