import template from './mollie-refund-manager-summary.html.twig';
import RefundCalculator from '../../services/RefundCalculator';

const { Component, Filter } = Shopware;

interface RefundManagerSummaryComponent {
    calculator: RefundCalculator;

    [key: string]: any;
}

const componentConfig: ThisType<RefundManagerSummaryComponent> = {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        isRefunding: {
            type: Boolean,
            required: false,
            default: false,
        },
        isAclRefundAllowed: {
            type: Boolean,
            required: false,
            default: false,
        },
        configVerifyRefund: {
            type: Boolean,
            required: false,
            default: true,
        },
        roundingDiff: {
            type: Number,
            required: false,
            default: 0,
        },
        voucherAmount: {
            type: Number,
            required: false,
            default: 0,
        },
        pendingRefunds: {
            type: Number,
            required: false,
            default: 0,
        },
        refundedAmount: {
            type: Number,
            required: false,
            default: 0,
        },
        remainingAmount: {
            type: Number,
            required: false,
            default: 0,
        },
        refundAmount: {
            type: Number,
            required: false,
            default: 0,
        },
        refundDescription: {
            type: String,
            required: false,
            default: '',
        },
        refundInternalDescription: {
            type: String,
            required: false,
            default: '',
        },
        checkVerifyRefund: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialFullRefundVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialRoundingDiffVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialPartialAmountRefundVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialPartialQuantityVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialPartialPromotionsVisible: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialResetStock: {
            type: Boolean,
            required: false,
            default: false,
        },
        tutorialRefundShipping: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            calculator: null,
        };
    },

    computed: {
        currencyFilter() {
            return Filter.getByName('currency');
        },

        descriptionCharacterCountingTitle() {
            return this.$tc('mollie-payments.refund-manager.summary.lblDescription', 0, {
                characters: this.refundDescription.length,
            });
        },

        // Two-way bound fields are mirrored back to the parent via events,
        // because the project convention does not use v-model on custom components.
        localRefundAmount: {
            get() {
                return this.refundAmount;
            },
            set(value: number) {
                this.$emit('refund-amount-changed', value);
            },
        },

        localRefundDescription: {
            get() {
                return this.refundDescription;
            },
            set(value: string) {
                this.$emit('refund-description-changed', value);
            },
        },

        localRefundInternalDescription: {
            get() {
                return this.refundInternalDescription;
            },
            set(value: string) {
                this.$emit('refund-internal-description-changed', value);
            },
        },

        localCheckVerifyRefund: {
            get() {
                return this.checkVerifyRefund;
            },
            set(value: boolean) {
                this.$emit('verify-changed', value);
            },
        },
    },

    created() {
        this.calculator = new RefundCalculator();
    },

    methods: {
        formatCurrency(value: number) {
            return this.currencyFilter(value, this.order.currency.isoCode, this.order.totalRounding.decimals);
        },

        /**
         * Gets if the button to fix the refund amount is available.
         * Only available for small rounding differences between refund and remaining amount.
         */
        isButtonFixDiffAvailable() {
            return this.calculator.isFixDiffAvailable(this.refundAmount, this.remainingAmount);
        },
    },
};

Component.register('mollie-refund-manager-summary', componentConfig);
