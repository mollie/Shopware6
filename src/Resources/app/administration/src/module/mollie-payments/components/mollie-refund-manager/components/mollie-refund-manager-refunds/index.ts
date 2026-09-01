import template from './mollie-refund-manager-refunds.html.twig';
import MollieRefundsGrid from '../../grids/MollieRefundsGrid';
import RefundCompositionFormatter from '../../services/RefundCompositionFormatter';

const { Component, Filter } = Shopware;

interface RefundManagerRefundsComponent {
    compositionFormatter: RefundCompositionFormatter;

    [key: string]: any;
}

const componentConfig: ThisType<RefundManagerRefundsComponent> = {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },
        refunds: {
            type: Array,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        isAclCancelAllowed: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            compositionFormatter: null,
        };
    },

    computed: {
        currencyFilter() {
            return Filter.getByName('currency');
        },

        dateFilter() {
            return Filter.getByName('date');
        },

        columns() {
            return new MollieRefundsGrid().buildColumns();
        },
    },

    created() {
        this.compositionFormatter = new RefundCompositionFormatter();
    },

    methods: {
        formatCurrency(value: number) {
            return this.currencyFilter(value, this.order.currency.isoCode, this.order.totalRounding.decimals);
        },

        /**
         * Gets the provided status key translated into a snippet of Shopware.
         */
        getRefundStatusName(statusKey: string) {
            return this.$tc(`mollie-payments.refunds.status.${statusKey}`);
        },

        /**
         * Gets the translated description of the provided status key.
         */
        getRefundStatusDescription(statusKey: string) {
            return this.$tc(`mollie-payments.refunds.status.description.${statusKey}`);
        },

        /**
         * Gets the status badge color (button variant) for the provided status key.
         */
        getRefundStatusBadge(statusKey: string) {
            return statusKey === 'refunded' ? 'success' : 'warning';
        },

        getRefundCompositions(item: any) {
            return this.compositionFormatter.format(
                item,
                this.order.currency.symbol,
                this.$tc('mollie-payments.refund-manager.refunds.grid.lblNoComposition'),
            );
        },

        isRefundCancelable(item: any) {
            return item.isPending || item.isQueued;
        },
    },
};

Component.register('mollie-refund-manager-refunds', componentConfig);
